<?php
/**
 *  Title:      WikiBot.class.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Version:    0.0.3-0
 *  Date:       October 13, 2012
 *  Description:
 *      Basic class for interaction with MediaWiki. Handles login/out,
 *      fetching pages, and writing pages.
 *
 *      Copyright: CC-BY-2.5 (See Creative Commons website for full terms)
 *
 *  History
 *      0.0.3-0    2012-10-13   Brian McNeil
 *                              Document now at most-basic functions.
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
    const API_parse     = '?action=parse&format=php';

    private $bot;

    /**
     * Constructor, create an instance of the class for a particular wiki and user/pass
     * @param $wiki_url The "base" URL for the wiki (eg http://test.wikipedia.org)
     * @param $wiki_api Optional path to the wiki's api.php. Defaults to standard for WMF installs
     * @param $ht_user  An optional username if HTTP-Auth is in-use
     * @param $ht_pass  An optional password for HTTP-Auth (not same as user/pass for wiki login)
     * @param $quiet    Default true; optional parameter to make the class output tracing comments/messages
     * @return          True/False depending on success.
     **/
    function __construct($wiki_url=self::DEFAULT_wiki, $wiki_api=self::DEFAULT_api, $ht_user=null, $ht_pass=null, $quiet=true ) {
        $r  = array();

        // Create a cURL instance for exclusive use by the bot
        $r  = $this->init_cURL( $wiki_url, $wiki_api);
        if ($r == false) {
            return false;
        }
        // If HTTP-Auth used, store those details
        if ($ht_user !== null) {
            $r['cURL']->HTTP_auth( $ht_user, $ht_pass );
        }
        // Revision ID/Page will be tracked to allow bots to detect
        // edit conflicts.
        $r['revid']     = null;
        $r['pagetitle'] = null;
        $r['rev_time']  = null;
        // Thow around some error stuff
        $r['error']     = null;
        $r['errcode']   = null;
        // Default behaviours - These reset on every page write!
        $r['bot']               = true; // We're a bot
        $r['minor']             = false; // We don't make minor edits
        $r['conflict']          = true; // Respect edit conflicts
        $r['newpage']           = false; // Not writing new page unless say so
        $r['readtime']          = 0; // Set this to time page retrieved if not
                                     // writing most-recently-read page.
        // Keep quiet unless told otherwise
        $r['quiet']     = $quiet;
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
            $this->bot['errcode']   = 'warning';
            return false;
        }
        // If we've got a relevant variable, return it
        if (isset($this->bot[$var]))
            return $this->bot[$var];

        return null;
    }

    /**
     * A limited-scope 'set' function.
     * @param $var      'bot' variable to set
     * @param $value    Value to assign to variable
     * @return          True if successful, false if fails
     **/
     public function __set( $var, $value ) {
         if ( $this->quiet == false )
             echo "Request to set variable: $var to '$value'\r\n";
         if ( $var == 'cURL' || $var == 'credentials' ) {
             $this->error   = "Setting protected variable outside object creation not permitted.";
             $this->errcode = 'fail';
             return false;
         }

         if ( isset( $this->bot[$var] ) ) {
             $this->bot[$var]   = $value;
             return true;
         } else {
             $this->error   = "Request to set undefined object variable for WikiBot class.";
             $this->errcode = 'warning';
             return false;
         }

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
        if ( $this->quiet == false )    echo "Doing query: $q \r\n";
        $r  = null;
        $wURL   = $this->URL;
        if ($postdata == null ) {
            if ( $this->quiet == false )    echo "    Request type: GET\r\n";
            $r  = $this->bot['cURL']->http_get($wURL.$query);
        } else {
            if ( $this->quiet == false )    echo "    Request type: POST\r\n";
            $r  = $this->bot['cURL']->http_post($wURL.$query, $postdata);
        }
        if (!$r) {
            $this->bot['error']     = "Error with cURL library";
            $this->bot['errcode']   = 'fatal';
            return false;
        }
        return unserialize($r);
    }

    private function content( $query, $postdata = null ) {
        if ( $this->quiet == false )    echo "Doing content retrieval: $query \r\n";
        $r  = null;
        $wURL   = $this->URL;
        if ($postdata == null ) {
            if ( $this->quiet == false )    echo "    Request type: GET\r\n";
            $r  = $this->bot['cURL']->http_get($wURL.$query);
        } else {
            if ( $this->quiet == false )    echo "    Request type: POST\r\n";
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
        if ( $this->quiet == false )    echo "API query of: $query \r\n";

        $q  = self::API_qry.$query;
        return $this->query($q, $postdata);
    }

    function query_content( $query, $postdata = null ) {
        if ( $this->quiet == false )    echo "Content request of: $query \r\n";

        $q  = self::API_parse.$query;
        return $this->query($q, $postdata);
    }

    /**
     * Wiki login function
     * @param $user     Username to log in with
     * @param $pass     Password for the user
     * @return          False if fails, or array of data from the API if succeeds
     **/
    function login( $user = null, $pass = null ) {
        if ( $this->quiet == false )    echo "Logging in, user: $user \r\n";
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
            if ( $this->quiet == false )    echo "    Trying to retrieve saved credentials\r\n";
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
         if ( $this->quiet == false )    echo "Logging out\r\n";
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
        if ( $this->quiet == false )    echo "Fetching page: $page \r\n";
        // If asked for an edit token when fetching page, query differs
        if ( $gettoken ) {
            if ( $this->quiet == false )    echo "    Asking edit token\r\n";
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
            if ($get_token !== false ) {
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
     * Page write function.
     *  This function will handle most page write permutations; certain options can be
     *  'tweaked' by setting extra variables (eg: $bot->conflict = false to ignore edit conflicts)
     *  Those variable options are reset to: mark edits as bot, not minor, respect edit conflicts,
     *      not writing new pages. It is also assumed (unless specified in $bot->readtime) that
     *      the page being written was the last read. If not, $bot->readtime must contain the
     *      timestamp of the retrieved revision.
     * @param $title    Title of page being accessed/written
     * @param $content  Content of page, or section, to write
     * @param $summary  Edit summary, or new section name
     * @param $section  A numeric string for section number to edit or 'new' to append new
     * @return          False if fails, otherwise the data returned by the API.
     **/
    function write_page( $title, $content, $summary = null, $section = null ) {
        if ( $this->quiet == false )    echo "Writing to page:$title \r\n";
        $q      = '?action=edit&format=php';
        $post   = array(
                'title'                             => $title,
                'summary'                           => $summary,
                ($this->bot?'bot':'notbot')         => true,
                ($this->minor?'minor':'notminor')   => true
                );

        // Grab timestamp, even if not going to use it later.
        $e_timestamp    = $this->rev_time;
        if ( $this->readtime !== 0 ) {
            $e_timestamp    = $r_time;
        } elseif ( !$this->newpage ) {
            // Null timestamp, must be editing last-page retrieved if $new not true
            if ( $this->pagetitle !== $title ) {
                $this->bot['error']     = "Cannot update a page that not previously retrieved\r\n" ;
                $this->bot['errcode']   = 'warning';
                return false;
            }
        }

        if ( $this->conflict ) {
            $post['basetimestamp']  = $e_timestamp; // Try catch edit conflicts
        } else {
            $post['recreate']   = true;             // Or overwrite anything
        }

        // Handle writing new section, or updating a section
        if ( $section !== null ) {
            if ( $section == 'new' ) { // Appending a new section
                $post['section']        = 'new';
            } else {
                $post['section']        = $section; // This assumes the variable holds a string integer
            }
            $post['sectiontitle']   = $summary;
        }

        $post['text']   = $content;
        $post['token']  = $this->token;

        // Default behaviours - These reset on every page write!
        $this->bot['bot']       = true; // We're a bot
        $this->bot['minor']     = false; // We don't make minor edits
        $this->bot['conflict']  = true; // Respect edit conflicts
        $this->bot['newpage']   = false; // Not writing new page unless say so
        $this->bot['readtime']  = 0; // Set this to time page retrieved if not
                                        // writing most-recently-read page.

                                        $result = $this->query( $q, $post );
        if ( isset($result['error']) ) {
            $this->bot['error']     = $result['error']['info'];
            $this->bot['errcode']   = $result['error']['code'];
            return false;
        }
        return $result;
    }

    /**
     * Function to retrieve a page's table of contents (index)
     * @param $page     The title of the required page
     * @param $revid    Optional, the revision ID to fetch the TOC of
     * @return          False if fails, or the TOC in an array.
     *                  Note failure only if no page.
     **/
    function get_toc( $page, $revid = null ) {
        $toc    = array();
        $toc[] = array(
            'index'     => '0',
            'heading'   => '',
            'level'     => '0',
            'page'      => $page,
            'number'    => '0'
            );
        if ( $this->quiet == false )    echo "Fetching TOC for: $page \r\n";

        $q  = '&prop=sections&page='.urlencode($page);
        if ( $revid !== null )
            $q  .= '&rvstartid='.$revid;

        $r  = $this->query_content( $q );
        if ( isset($r['error']) ) { // Error getting any page data
            $this->bot['error']     = $r['error']['string'];
            $this->bot['errcode']   = 'error';
            return false;
        }
        $toc_elem   = $r['parse']['sections'];

        if ( empty($toc_elem) ) { // Empty, does that mean page doesn't exist?
            if ( $this->get_page( $page ) == false) {
                $this->bot['error']     = "Requested TOC for nonexistent page";
                $this->bot['errcode']   = 'warning';
                return false;
            }
        }
        foreach ($toc_elem as $line ) {
            $toc[]  = array(
                'index'     => $line['index'],
                'heading'   => $line['line'],
                'level'     => $line['level'],
                'page'      => $line['fromtitle'],
                'number'    => $line['number']
                );
        }
        return $toc;
    }
}
?>
