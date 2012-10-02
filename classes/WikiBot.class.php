<?php
/**
 *  Title:      WikiBot.class.php
 *  Author(s):  Brian McNeil [[n:Brian McNeil]]
 *  Description:
 *      Class(es) to handle bot interactions with MediaWiki
 *
 *
 **/

require_once(CLASSPATH.'HTTPcurl.class.php');

/**
 * This is the base class for interacting with a MediaWiki install
 * @author Brian McNeil
 **/
class WikiBot {
    // For safety, should user mangle data for wiki, the test
    // wiki is set as a default and the path to the API is as-per
    // the usual default for a 'vanilla' MediaWiki install.
    const DEFAULT_wiki  = 'http://test.wikipedia.org';
    const DEFAULT_api   = '/w/api.php';
    const API_qry       = '?action=query&format=php';

    private $bot;

    /**
     * Constructor, create an instance of the class for a particular wiki and user/pass
     * @param $wiki_url The "base" URL for the wiki (eg http://test.wikipedia.org)
     * @param $wiki_api Optional path to the wiki's api.php. Defaults to standard for WMF installs
     * @param $ht_user  An optional username if HTTP-Auth is in-use
     * @param $ht_pass  An optional password for HTTP-Auth (not same as user/pass for wiki login)
     * @return          True/False depending on success.
     **/
    function __construct($wiki_url=self::DEFAULT_wiki, $wiki_api=self::DEFAULT_api, $ht_user=null, $ht_pass=null) {
        $r  = array();

        // Create a cURL instance for exclusive use by the bot
        $r  = $this->init_cURL( $wiki_url, $wiki_api);
        if ($r == false) {
            return false;
        }
        // If HTTP-Auth used, store those details
        if ($ht_user !== null) {
            $r['cURL']->HTTP_auth( $ht_user, $ht_pass);
        }
        // Revision ID/Page will be tracked to allow bots to detect
        // edit conflicts.
        $r['revid']     = null;
        $r['pagetitle'] = null;
        $r['rev_time']  = null;
        $this->bot  = $r;
        return true;
    }

    /**
     * A generalised get function which accesses the bot array
     * @param $var      Variable being sought
     * @return          The value from the variable, or null
     **/
    public function __get( $var ) {
        // Explicitly block access to the cURL object
        if ( $var == 'cURL' ) {
            throw new Exception ("Access to cURL details not permitted");
            return false;
        }
        // Special case - bot username
        if ( $var == 'user' ) {
            if (isset($this->bot['credentials']))
                return $this->bot['credentials']['lgname'];
        }
        // No trying anything 'cute'; only accept string
        if (!is_string($var)) {
            throw new Exception("Invalid variable access attempt, must be string containing variable name");
            return false;
        }
        // If we've got a relevant variable, return it
        if (isset($this->bot[$var]))
            return $this->bot[$var];

        return null;


    }

    /**
     * Configuration function, creates, and returns, the cURL instance
     * @return      false if fails, otherwise an array holding
     *              the cURL instance and the wiki's API URL
     **/
    private function init_cURL( $wiki, $api ) {
        $r  = array();
        $r['cURL']  = new HTTPcurl();

        if ( !$r['cURL'] ) {
            return false;
        }
        $r['URL']       = $wiki.$api;
        $r['token']     = null;
        $r['timestamp'] = null;
        $r['cURL']->param['quiet']  = false; // try to make noisy
        return $r;
    }

    /**
     * Destructor; frees up the bot instance
     * @return      void
     **/
    function __destruct() {
        unset($this->bot);
    }

    /**
     * 'Raw' query function; sends a query to the target MediaWiki instance
     *  with the assumption the relevant API string is in-place.
     * @param $query    Passed-in query string (eg '&prop=revisions&title=Foo')
     * @param $postdata Optional data to go by POST method
     * @return          False if fails, or unserialized result data
     **/
    private function query( $query, $postdata = null ) {
        $r  = null;
        $wURL   = $this->bot['URL'];
        if ($postdata == null ) {
            $r  = $this->bot['cURL']->http_get($wURL.$query);
        } else {
            $r  = $this->bot['cURL']->http_post($wURL.$query, $postdata);
        }
        if (!$r)
            return false;
        return unserialize($r);
    }

    /**
     * API query function; sends a query to the target MediaWiki using the API
     * @param $query    Passed-in query string (eg '&prop=revisions&title=Foo')
     * @param $postdata Optional data to go by POST method
     * @return          False if fails, or unserialized result data
     **/
    function query_api( $query, $postdata = null ) {

        $q  = self::API_qry.$query;
        return $this->query($q, $postdata);
    }

    /**
     * Wiki login function
     * @param $user     Username to log in with
     * @param $pass     Password for the user
     * @return          False if fails, or array of data from the API if succeeds
     **/
    function login( $user = null, $pass = null ) {
        $q          = '?action=login&format=php';

        // If the username is passed in, then we use what we're given
        if ($user !== null) {
            // Save the credentials we got before trying to use them
            $postdata   = array(
                        'lgname'        => $user,
                        'lgpassword'    => $pass
                        );
            $this->bot['credentials']   = $postdata;
        } else {
            // Otherwise, try to retrieve saved credentials
            if (isset($this->bot['credentials'])) {
                $postdata   = $this->bot['credentials'];
            } else {
                throw new Exception ("Login failed; no credentials supplied");
                return false;   // Fail, don't have any saved credentials
            }
        }
        // Start trying to log in...
        $r  = $this->query( $q, $postdata );
        if (isset($r['login']['result'])) {
            // Token required in more-recent MediaWiki versions
            if ($r['login']['result'] == 'NeedToken') {
                $postdata['lgtoken']    = $r['login']['token'];
                $r  = $this->query( $q, $postdata );
            }
        } else {
            throw new Exception ("Login failed; no result returned");
            return false;   // It failed to give a result at-all
        }
        if (isset($r['login']['result'])) {
            if ($r['login']['result'] !== 'Success') {
                // The login failed, probably incorrect credentials
                throw new Exception ("Login failed; returned:".$r['login']['result']);
                return false;
            } else {
                return $r;
            }
        } else {
                throw new Exception ("Login failed; no result returned");
                return false;   // Again, didn't get returned a result.
        }
    }

    /**
     *  Logout function
     * @return          Falls out, thus returning null
     **/
     function logout() {
         $this->query( '?action=logout&format=php' );
     }

     /**
     * General 'page-fetching' function
     * @param $page     The title of the required page
     * @param $gettoken
     * @param $revid    The revision ID (optional) to be fetched
     * @return          False if fails, or wikitext of desired page
     **/
    function get_page( $page, $gettoken = false, $revid = null ) {
        // If asked for an edit token when fetching page, query differs
        if ($gettoken !== false) {
            $q  = '&prop=revisions|info&intoken=edit';
        } else {
            $q  = '&prop=revisions';
        }
        $q      .= '&titles='.urlencode($page).'&rvlimit=1&rvprop=content|timestamp|ids';

        // If asking for specific version, select such
        if ($revid !== null )
            $q  .= '&rvstartid='.$revid;

        $r  = $this->query_api( $q );
        if (!$r)
            return false;

        foreach ($r['query']['pages'] as $t_page) {
            // Now, stash page fetched and the revision ID.
            $this->bot['pagetitle'] = $page;
            $this->bot['rev_time']  = $t_page['revisions'][0]['timestamp'];
            $this->bot['revid']     = $t_page['revisions'][0]['revid'];

            // Save details of the edit token and the 'edit' start timestamp
            if ($edit_token !== false ) {
                $this->bot['token']     = $t_page['edittoken'];
                $this->bot['timestamp'] = $t_page['starttimestamp'];
            }
            // Return the wiki-markup page content
            return $t_page['revisions'][0]['*'];
        }
        // If we hit here, we've not got a page back
        return false;
    }
}
?>
