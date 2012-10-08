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
        $r['error']     = null;
        $r['errcode']   = null;
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
            $this->bot['error']     = "Access to cURL details not permitted";
            $this->bot['errcode']   = 'fatal';
            return false;
        }
        // Special case - bot username
        if ( $var == 'user' ) {
            if (isset($this->bot['credentials']))
                return $this->bot['credentials']['lgname'];
        }
        // No trying anything 'cute'; only accept string
        if (!is_string($var)) {
            $this->bot['error']     = "Invalid variable access attempt, must be string containing variable name";
            $this->bot['errcode']   = 'warn';
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
        $wURL   = $this->URL;
        if ($postdata == null ) {
            $r  = $this->bot['cURL']->http_get($wURL.$query);
        } else {
            $r  = $this->bot['cURL']->http_post($wURL.$query, $postdata);
        }
        if (!$r) {
            $this->bot['error']     = "Error with cURL library";
            $this->bot['errcode']   = 'fatal';
            return false;
        }
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
                $this->bot['error']     = "Login failed; no credentials supplied";
                $this->bot['errcode']   = 'fatal';
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
            $this->bot['error']     = "Login failed; no result returned";
            $this->bot['errcode']   = 'fatal';
            return false;   // It failed to give a result at-all
        }
        if (isset($r['login']['result'])) {
            if ($r['login']['result'] !== 'Success') {
                // The login failed, probably incorrect credentials
                $this->bot['error']     = "Login failed; returned:".$r['login']['result'];
                $this->bot['errcode']   = 'fatal';
                return false;
            } else {
                return $r;
            }
        } else {
                $this->bot['error']     = "Login failed; no result returned";
                $this->bot['errcode']   = 'fatal';
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
     * @param $gettoken If an edit token is required, also results in the
     *                  page's timestamp and revid being saved.
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
        if (!$r) {
            $this->bot['error']     = "No data returned by MediaWiki API";
            $this->bot['errcode']   = 'fatal';
            return false;
        }

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
        $this->bot['error']     = "Unknown error fetching wiki page";
        $this->bot['errcode']   = 'warning';
        return false;
    }

    /**
     * 'Swiss-Army Knife' page write function.
     * This function will handle all page write permutations, and generally be
     * called from other functions which handle adding a new section, updating an
     * existing section, or preventing overwrite of existing pages.
     * @param $title    Title of page being accessed/written
     * @param $content  Content of page, or section, to write
     * @param $summary  Edit summary, or new section name
     * @param $bot      Default true, defines if a bot edit
     * @param $minor    Default false, defines if a minor edit
     * @param $new      Optional; if not specified, will write page regardless
     *                  If true, must be a new page being written; if false
     *                  must *not* be a new page being written.
     * @param $sec_num  Optional; the section number being edited/written.
     * @param $sec_new  Default false; if set to true, must be a new page section
     *                  being added
     * @param $ign_conf Default false; ignore edit conflicts
     * @param $r_time   This is only required where handling edit conflicts *and*
     *                  the page being written was not the last page retrieved. When
     *                  this is the case, the timestamp from the page retrieved must
     *                  be provided to allow the API to detect edit conflicts.
     * @return          False if fails, otherwise the data returned by the API.
     **/
    function write_page( $title, $content, $summary = null, $bot = true, $minor = false,
                        $new = null, $sec_num = null, $sec_new = false,
                        $ign_conf = false, $r_time = null) {
        $q      = '?action=edit&format=php';
        $post   = array(
                'title'                 => $title,
                'summary'               => $summary,
                ($bot?'bot':'notbot')   => true,
                ($minor?'minor':'notminor') => true
                );
        // Grab timestamp, even if not going to use it later.
        $e_timestamp    = $this->rev_time;
        if ( $r_time !== null ) {
            $e_timestamp    = $r_time;
        } elseif ( $new !== true) {
            // Null timestamp, must be editing last-page retrieved if $new not true
            if ( $this->pagetitle !== $title ) {
                $this->bot['error']     = "Cannot update a page that not previously retrieved\r\n" ;
                $this->bot['errcode']   = 'warning';
                return false;
            }
        }

        if ( $new == true ) {
            $post['createonly'] = true;
        } elseif ( $new == false ) {
            $post['nocreate']   = true;
        }

        // Handle writing new section, or updating a section
        if ( $sec_new == true ) {
            $post['section']        = 'new';
            $post['sectiontitle']   = $summary;
        } elseif ( $sec_num ) {
            $post['section']    = $sec_num;
        }
        if ( $ign_conf == true ) {
            $post['recreate']   = true;
        } else {
            $post['basetimestamp']  = $e_timestamp;
//            $post['starttimestamp'] = $this->timestamp;
        }

        $post['text']   = $content;
        $post['token']  = $this->token;

        $result = $this->query( $q, $post );
        if ( isset($result['error']) ) {
            $this->bot['error']     = $result['error']['info'];
            $this->bot['errcode']   = $result['error']['code'];
            return false;
        }
        return $result;
    }
}
?>
