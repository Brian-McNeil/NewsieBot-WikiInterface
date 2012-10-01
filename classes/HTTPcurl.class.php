<?php
/**
 *      HTTPcurl.classes.php
 *
 *      Class for using Curl with cookie storage and simplified access to
 *      POST or GET page content.
 **/

// Need a temporary dir; could be local, or sys-wide.
if ( !defined('curlTMPstor' ) {
    define( "curlTMPstor", '/tmp/' );
}

class HTTPcurl {

    static $version             = "HTTPcurl.class v0.1";
    const   connect_timeout     = 10;
    const   response_timeout    = 60;
    const   max_connects        = 100;
    const   max_redirects       = 10;

    private $c_data;    // The cURL instance is kept private
    public $param;      // Public parameters, generally should not be
                        // set directly.
    function __construct() {
        $this->param    = $this->init_public_parameters();
        $curl_obj       = $this->init_curl_parameters();

        if ( $curl_obj !== false ) {
            $this->c_data   = $curl_obj;
        } else {
            return false;
        }
    }

    function __destruct() {
        curl_close( $this->c_data['chan'] );
        @unlink( $this->param['jar_name'] );
        unset($this->param);
        unset($this->c_data);
    }

    private function init_public_parameters() {
        $rp = array();
        $rp['jar_id']   = dechex(rand(0, 999999999));
        $rp['post_followredir'] = false;
        $rp['get_followredir']  = true;
        $rp['useragent']        = self::$version;
        $rp['quiet']            = true;
        $rp['timeout_connect']  = self::connect_timeout;
        $rp['timeout_response'] = self::response_timeout;
        $rp['jar_name']         = constant("curlTMPstor").'cookies-'.$rp['jar_id'].'.dat';

        return $rp;
    }

    private function init_curl_parameters() {
        $rc = array();
        $rc['chan']  = curl_init();
        if ( $rc['chan'] == false ) {
            throw new exception("Failed to initialise curl for access to web");
            return false;
        }
        $rc['token_jar']        = array(); // These are 'extra' cookies, such as login tokens
        $rc['cookie_string']    = false;

        if ( !set_curl_params() ) {
            throw new exception("Failed to set normal curl parameters");
            return false;
        }

        return $rc;
    }

    public function set_curl_params( $parm_arr = null ) {
        if ($parm_arr == null) {
            curl_setopt($this->c_data['chan'], CURLOPT_COOKIEJAR, $this->param['jar_name']);
            curl_setopt($this->c_data['chan'], CURLOPT_COOKIEFILE, $this->param['jar_name']);
            curl_setopt($this->c_data['chan'], CURLOPT_MAXCONNECTS, self::max_connects);
            curl_setopt($this->c_data['chan'], CURLOPT_MAXREDIRS, self::max_redirects);
            curl_setopt($this->c_data['chan'], CURLOPT_CLOSEPOLICY, CURLCLOSEPOLICY_LEAST_RECENTLY_USER);
            curl_setopt($this->c_data['chan'], CURLOPT_CONNECTTIMEOUT, $this->param['timeout_connect']);
            curl_setopt($this->c_data['chan'], CURLOPT_TIMEOUT, $this->param['timeout_response']);
        } else {
            foreach ( $parm_arr as $opt => $val ) {
                curl_setopt($this->c_data['chan'], $opt, $val);
            }
        }
        if (!$this->c_data['cookie_string'] ) {
            curl_setopt($this->c_data['chan'], CURLOPT_COOKIE, $this->c_date['cookie_string']);
        }
    }

    public HTTP_auth( $username, $password ) {
        $c_pars = array(
                CURLOPT_HTTPAUTH        => CURLAUTH_BASIC,
                CURLOPT_USRERPWD        => $username.":".$password
            );
        set_curl_params( $c_pars );
    }
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
        set_curl_params( $c_pars );
        $r_data     = curl_exec($this->c_data['chan']);

        if (!$this->param['quiet'] )
            echo "POST: $url (".(microtime(1) - $stime)." sec) ".strlen($data)." bytes\r\n";
        return $r_data;
    }

    public function http_get($url) {
        $stime = microtime(1);
        $c_pars = array(
                CURLOPT_URL             => $url,
                CURLOPT_FOLLOWLOCATION  => $this->param['get_followredir'],
                CURLOPT_HEADER          => 0,
                CURLOPT_RETURNTRANSFER  => true,
                CURLOPT_HTTPGET         => true
            );

        set_curl_params( $c_pars );
        $r_data     = curl_exec($this->c_data['chan']);

        if (!$this->param['quiet'] )
            echo "GET: $url (".(microtime(1) - $stime)." sec) ".strlen($data)." bytes\r\n";
        return $r_data;
    }

    public function http_extracookies( $extra_cookie = null ) {
        $cookies_curr   = $this->c_data['token_jar'];
        foreach ( $extra_cookie as $name => $val ) {
            $cookies_curr[$name]    = $val;
        }
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
    }
}
?>
