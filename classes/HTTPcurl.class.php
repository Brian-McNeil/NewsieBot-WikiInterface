<?php
/**
 *  Title:      HTTPcurl.classes.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Version:    0.1.0-0
 *  Date:       October 13, 2012
 *  Description:
 *      cURL wrapper class
 *
 *      Copyright: CC-BY-2.5 (See Creative Commons website for full terms)
 *
 *  History
 *      0.1.0-0    2012-10-13   Brian McNeil
 *                              Create: Class to use as a wrapper for cURL
 *                              with the MediaWiki API.
 **/

// Need a temporary dir; could be local, or sys-wide.
// A user-local directory may-well be preferable for improved security.
if ( !defined('curlTMPstor') ) {
    define( "curlTMPstor", '/tmp/' );
}
if ( !defined('CRLF') ) {
    define( 'CRLF', "\r\n" );
}

class HTTPcurl {

    static $version             = "HTTPcurl.class v0.1.0-0";
    const   connect_timeout     = 10;
    const   response_timeout    = 60; // This will need bumped up a great deal if uploading large files
    const   max_connects        = 100;
    const   max_redirects       = 10;

    private $c_data;    // The cURL instance is kept private
    public $param;      // Public parameters, generally should not be
                        // set directly.
    /**
     *  Constructor function
     * @param $agent_str    Optional text to append to the user agent
     * @return              True/False for success or failure
     **/
    function __construct( $agent_str = null ) {
        $this->param    = $this->init_public_parameters();
        $curl_obj       = $this->init_curl_parameters();

        if ( $agent_str !== null )
            $this->param['useragent']   = self::$version." - ".$agent_str;

        if ( $curl_obj !== false ) {
            $this->c_data   = $curl_obj;
        } else {
            return false;
        }
        if ( !$this->set_curl_params() ) {
            throw new exception("Failed to set normal cURL parameters");
            return false;
        }
        return true;
    }

    /**
     *  Destructor, cleanup.
     * @return  void
     **/
    function __destruct() {
        curl_close( $this->c_data['chan'] );
        @unlink( $this->param['jar_name'] );
        unset($this->param);
        unset($this->c_data);
    }

    /**
     *  Configure the standard parameters
     * @return      Array of parameters
     **/
    private function init_public_parameters() {
        $rp = array();
        $rp['jar_id']   = dechex(rand(0, 999999999));
        $rp['post_followredir'] = false;
        $rp['get_followredir']  = true;
        $rp['useragent']        = self::$version." User:NewsieBot";
        $rp['quiet']            = true;
        $rp['timeout_connect']  = self::connect_timeout;
        $rp['timeout_response'] = self::response_timeout;
        $rp['jar_name']         = constant("curlTMPstor").'cookies-'.$rp['jar_id'].'.dat';

        return $rp;
    }

    /**
     *  Configure the cURL parameters.
     * @return      False if fails, or a an array holding cURL object
     *              and cookie/token transients (initially null)
     **/
    private function init_curl_parameters() {
        $rc = array();
        $rc['chan']  = curl_init();
        if ( $rc['chan'] == false ) {
            throw new exception("Failed to initialise cURL for access to web");
            return false;
        }
        $rc['token_jar']        = array(); // These are 'extra' cookies, such as login tokens
        $rc['cookie_string']    = false; // Also keep a 'simple' string rather than repeatedly rebuild

        return $rc;
    }

    /**
     *  Set cURL parameters, either to defaults, or based on a passed-in array
     * @param $parm_array   Optional array of cURL parameters to set
     * @return              True/False for success/failure
     **/
    public function set_curl_params( $parm_arr = null ) {
        $cURL   = $this->c_data['chan'];
        try {
            if ($parm_arr == null) {
                curl_setopt($cURL, CURLOPT_COOKIEJAR, $this->param['jar_name']);
                curl_setopt($cURL, CURLOPT_COOKIEFILE, $this->param['jar_name']);
                curl_setopt($cURL, CURLOPT_MAXCONNECTS, self::max_connects);
                curl_setopt($cURL, CURLOPT_MAXREDIRS, self::max_redirects);
                curl_setopt($cURL, CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_LEAST_RECENTLY_USER);
                curl_setopt($cURL, CURLOPT_CONNECTTIMEOUT, $this->param['timeout_connect']);
                curl_setopt($cURL, CURLOPT_TIMEOUT, $this->param['timeout_response']);
                curl_setopt($cURL, CURLOPT_USERAGENT, $this->param['useragent']);
            } else {
                foreach ( $parm_arr as $opt => $val ) {
                    curl_setopt($cURL, $opt, $val);
                }
            }
            if (!$this->c_data['cookie_string'] ) {
                curl_setopt($cURL, CURLOPT_COOKIE, $this->c_date['cookie_string']);
            }
        } catch (Exception $e) {
            echo 'Error setting cURL parameters\r\n';
            return false;
        }
        return true;
    }

    /**
     *  Function to store HTTP-Auth user/pass
     * @param $username     Username for Auth
     * @param $password     Password for Auth
     * @return              Returns void
     **/
    public function HTTP_auth( $username, $password ) {
        $c_pars = array(
                CURLOPT_HTTPAUTH        => CURLAUTH_BASIC,
                CURLOPT_USRERPWD        => $username.":".$password
            );
        $this->set_curl_params( $c_pars );
    }

    /**
     *  POST a request via cURL
     * @param $url          URL for the request
     * @param $data         Data to be sent via POST method
     * @return              Returns the data provided by cURL
     **/
    public function http_post($url, $data) {
        $stime = microtime(1);
        $c_pars = array(
                CURLOPT_URL             => $url,
                CURLOPT_FOLLOWLOCATION  => $this->param['post_followredir'],
                CURLOPT_HTTPHEADER      => array('Expect:'),
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_POST            => true,
                CURLOPT_POSTFIELDS      => $data
            );
        $this->set_curl_params( $c_pars );
        $data     = curl_exec($this->c_data['chan']);

        if (!$this->param['quiet'] )
            echo "POST: $url (".(microtime(1) - $stime)." sec) ".strlen($data)." bytes\r\n";
        return $data;
    }

    /**
     *  Perform a GET query
     * @param $url          The URL for the GET
     * @return              Data returned from cURL
     **/
    public function http_get($url) {
        $stime = microtime(1);
        $c_pars = array(
                CURLOPT_URL             => $url,
                CURLOPT_FOLLOWLOCATION  => $this->param['get_followredir'],
                CURLOPT_HEADER          => false,
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_HTTPGET         => true
            );

        $this->set_curl_params( $c_pars );
        $r_data     = curl_exec($this->c_data['chan']);

        if (!$this->param['quiet'] )
            echo "GET: $url (".(microtime(1) - $stime)." sec) ".strlen($data)." bytes\r\n";
        return $r_data;
    }

    /**
     *  Extra cookies function. Populate transient cookie jar
     * @param $extra_cookie Array of data for use as cookies
     * @return              True
     **/
    public function http_extracookies( $extra_cookie = null ) {

        // If passed null, clear saved cookies and bail out
        if ( $extra_cookie == null ) {
            $this->c_data['token_jar']      = null;
            $this->c_data['cookie_string']  = null;
            return true;
        }
        // Pull current cookies, and add to them
        $cookies_curr   = $this->c_data['token_jar'];
        foreach ( $extra_cookie as $name => $val ) {
            $cookies_curr[$name]    = $val;
        }
        // Convert cookies to a semicolon-delimited string for future use
        $this->c_data['token_jar']  = $cookies_curr;
        $cookies_str    = false;
        foreach ( $cookies_curr as $name => $val ) {
            if (!$cookies_str) {
                $cookies_str    = "$name=$value";
            } else {
                $cookies_str    .= "; $name=$value";
            }
        }
        $this->c_data['cookie_string']  = $cookies_str;
        return true;
    }
}
?>
