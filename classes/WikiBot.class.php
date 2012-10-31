<?php
/**
 *  Title:      WikiBot.class.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Version:    0.1.0-0
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
 *      0.0.4-0    2012-10-13   Brian McNeil
 *                              Abstract debug output, error handling,
 *                              improve get_toc performance by using MW array fmt
 *      0.1.0-0    2012-10-20   Brian McNeil
 *                              Now stable, tidy, order methods/functions,
 *                              add explicit write_section() method,
 *                              pull the config class here if not already done.
 **/

require_once(CLASSPATH.'config.class.php');
require_once(CLASSPATH.'HTTPcurl.class.php');

/**
 * This is the base class for interacting with a MediaWiki install
 * @author Brian McNeil
 **/
class WikiBot {

    const Version    = "WikiBot.class v0.1.0-0";
    // For safety, should user mangle data for wiki, the test
    // wiki is set as a default and the path to the API is as-per
    // the usual default for a 'vanilla' MediaWiki install.
    const DEFAULT_wiki  = 'http://test.wikipedia.org';
    const DEFAULT_api   = '/w/api.php';
    const API_qry       = '?action=query&format=php';
    const API_parse     = '?action=parse&format=php';
    const Main_Page     = 'Main Page';

    const NS_Media      = 'Media';
    const NS_Special    = 'Special';
    const NS_Main       = '';
    const NS_Talk       = 'Talk';
    const NS_User       = 'User';
    const NS_User_talk  = 'User talk';
    const NS_Project    = 'Project';
    const NS_Project_talk = 'Project talk';
    const NS_Template   = 'Template';
    const NS_Category   = 'Category';

    const ERR_fatal     = 'fatal';
    const ERR_error     = 'error';
    const ERR_warn      = 'warning';
    const ERR_info      = 'info';
    const ERR_success   = 'success';

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
        $r['revid']         = '';
        $r['pagetitle']     = '';
        $r['rev_time']      = 0;
        // Limit of items to return in lists (rvlimit, etc)
        $r['list_limit']    = '500';
        // Default behaviours - These reset on every page write!
        $r['botproc']           = true; // We're a bot
        $r['minor']             = false; // We don't make minor edits
        $r['conflict']          = true; // Respect edit conflicts
        $r['newpage']           = false; // Not writing new page unless say so
        $r['readtime']          = 0; // Set this to time page retrieved if not
                                     // writing most-recently-read page.
        // Keep quiet unless told otherwise, default run message
        $r['quiet']     = $quiet;

        if ( defined( 'WikiBot_Name' ) ) {
            $rmsg   = CRLF.'# Run of '.WikiBot_Name.' using ';
        } else {
            $rmsg   = CRLF.'# Unnamed bot using ';
        }
        $r['runmsg']    = $rmsg.self::Version
                        .CRLF.'#:: Start: '.gmdate( 'Y-m-d H:i:m' );
        $this->bot  = $r;
        return self::ERR_ret( self::ERR_success, "Initialised bot class" );
    }

    /**
     * Configuration function, creates, and returns, the cURL instance
     * @return      false if fails, otherwise an array holding
     *              the cURL instance and the wiki's API URL
     **/
    private function init_cURL( $wiki, $api ) {
        $r  = array();
        if ( defined('WikiBot_Name') ) {
            $useragent  = "Bot ".WikiBot_Name." using ".self::Version;
        } else {
            $useragent  = "Unnamed bot using ".self::Version;
        }
        $r['cURL']  = new HTTPcurl( $useragent );

        if ( !$r['cURL'] ) {
            return false;
        }
        $r['URL']       = $wiki.$api;
        $r['token']     = '';
        $r['timestamp'] = 0;
        $r['cURL']->param['quiet']  = false; // try to make noisy
        return $r;
    }

    /**
     * Destructor; frees up the bot instance
     * @return      void
     **/
    function __destruct() {
        self::DBGecho( "END: Tearing down WikiBot instance" );
        unset($this->bot);
    }

    /**
     * A generalised get function which accesses the bot array
     * @param $var      Variable being sought
     * @return          The value from the variable, or null
     **/
    public function __get( $var ) {
        // Explicitly block access to the cURL object
        if ( $var == 'cURL' ) {
            return self::ERR_ret( self::ERR_error, "Access to cURL details not permitted" );
        }
        // Special case - bot username
        if ( $var == 'user' ) {
            if (isset($this->bot['credentials']))
                return $this->bot['credentials']['lgname'];
        }
        // No trying anything 'cute'; only accept string
        if (!is_string($var)) {
            return self::ERR_ret( self::ERR_warn, "Invalid variable access attempt, must be string containing variable name" );
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
         self::DBGecho( "START:: __set('$var','$value')" );
         if ( $var == 'cURL' || $var == 'credentials' ) {
            return self::ERR_ret( self::ERR_error, "Setting protected variable outside object creation not permitted." );
         }
         if ( $var == 'cURL_request_timeout' ) {
             // Special case, pass request timeout to cURL
             return $this->bot['cURL']->req_timeout( $seconds );
         }
         // Special handling for runmsg; append the message as a new line
         // to be written to the bot's logging page.
         if ( $var == 'runmsg' ) {
             if ( strpos( '*:#', substr($value, 0, 1) ) === false )
                 $value = ' '.$value;
             $this->bot['runmsg']   = $this->bot['runmsg'].CRLF.'#:'.$value;
             return true;
         }
         // Another special case; Rrunmsg is "reset runmsg" this being used
         // by child classes to identify themselves in the log.
         if ( $var == 'Rrunmsg' ) {
             $this->bot['runmsg']   = $value;
             return true;
         }
         if ( isset( $this->bot[$var] ) ) {
             $this->bot[$var]   = $value;
             return true;
         } else {
             return self::ERR_ret( self::ERR_warn, "Request to set undefined object variable '$var' for WikiBot class." );
         }
    }

    private function get_namespaces_local() {
        self::DBGecho( "START: get_namespaces_local()" );
        if (isset($this->bot['ns_local']) == false) {
            $ns_list    = self::ns_list;
            array_walk( $ns_list,
                    function( &$val, $key ) {
                        $val    = $val['ns'];
                    } );
            $this->bot['ns_local']  = $ns_list;
        }
        return self::ns_local;
    }

    private function get_namespaces_canonical() {
        self::DBGecho( "START: get_namespaces_canonical()" );
        if (isset($this->bot['ns_canonical']) == false) {
            $ns_list    = self::ns_list;
            array_walk( $ns_list,
                    function( &$val, $key ) {
                        $val    = $val['canonical'];
                    } );
            $this->bot['ns_canonical']  = $ns_list;
        }
        return self::ns_canonical;
    }

    /**
     *  Function to query API about site's namespaces
     * @return  Returns an array of namespace data
     **/
    private function get_namespaces_DETAIL() {
        self::DBGecho( "START: get_namespaces_DETAIL()" );
        if (isset($this->bot['ns_list']) == false) {
            $q  = '&meta=siteinfo&siprop=namespaces';
            $r  = query_api( $q );
            $this->bot['ns_list'] = $r['query']['namespaces'];
        }
        return $this->namespaces;
    }

    /**
     * Abstract debug output
     * @param $string   String to prinr if debug on
     * @return          void
     **/
    function DBGecho( $string = '' ) {
        if ( $this->quiet === false )    echo $string.CRLF;
    }

    /**
     * Basic error logging within the class
     * @param $code     Error code (eg self::ERR_warn)
     * @param $text     Text of error message to stash
     * @return          false if code is ERR_fatal or ERR_error,
     *                  void if ERR_warn, else true
     **/
    function ERR_ret( $code = '', $text = '' ) {
        $this->bot['error']    = $text;
        $this->bot['errcode']  = $code;

        if ( $code == self::ERR_fatal || $code == self::ERR_error )
            return false;
        if ( $code == self::ERR_info || $code == self::ERR_success )
            return true;

    }

    /**
     * API query function; sends a query to the target MediaWiki using the API
     * @param $query    Passed-in query string (eg '&prop=revisions&title=Foo')
     * @param $postdata Optional data to go by POST method
     * @return          False if fails, or unserialized result data
     **/
    function query_api( $query, $postdata = null ) {
        self::DBGecho( "START: query_api('$query')" );
        $q  = self::API_qry.$query;
        return self::query( $q, $postdata );
    }

    /**
     * API parser function; requests the API parse a content request
     * @param $query    Passed-in query string
     * @param $postdata Optional data to go by POST method
     * @return          False if fails, or unserialized result data
     **/
    function query_content( $query, $postdata = null ) {
        self::DBGecho( "START: query_content('$query')") ;
        $q  = self::API_parse.$query;
        return self::query( $q, $postdata );
    }

    /**
     * 'Raw' query function; sends a query to the target MediaWiki instance
     *  with the assumption the relevant API string is in-place.
     * @param $query    Passed-in query string (eg '&prop=revisions&title=Foo')
     * @param $postdata Optional data to go by POST method
     * @return          False if fails, or unserialized result data
     **/
    function query( $query, $postdata = null ) {
        self::DBGecho( "START: query('$query')" );
        $r  = null;
        $wURL   = $this->URL;
        if ($postdata == null ) {
            self::DBGecho( "    Request type: GET" );
            $r  = $this->bot['cURL']->HTTP_get( $wURL.$query );
        } else {
            self::DBGecho( "    Request type: POST" );
            $r  = $this->bot['cURL']->HTTP_post( $wURL.$query, $postdata );
        }
        if ( !$r ) {
            return self::ERR_ret( self::ERR_fatal, "Error with cURL initialization" );
        }
        return unserialize($r);
    }

    /**
     * Function to try and log the bot's actions
     * @return      Can be discarded
     **/
    private function log_actions() {
        self::DBGecho( "START: log_actions()" );
        $pg         = self::get_page( Bot_LOG, true );
        if ( $pg === false )
            return false;   // Can't load log page? Bail out.
        $content    = $this->bot['runmsg']
                    .CRLF."#:: End: ".gmdate( 'Y-m-d H:i:m' )
                    .$pg;
        $lines = substr_count( $content, "# " );
        if ( $lines > Bot_LGMAX ) {
            // Have more items logged than limit, work back to limit
            while ( $lines-- > Bot_LGMAX && $ptr = strrpos( $content, "# " ) ) {
                $content         = substr( $content, 0, $ptr );
            }
        }
        $this->minor    = true;
        $this->conflict = false;
        $ed_summ    = "Logging work";
        if ( defined( 'WikiBot_Name' ) ) {
            $ed_summ    .= ' of '.WikiBot_Name;
        } else {
            $ed_summ    .= ' of unnamed bot';
        }
        return self::write_page( Bot_LOG, $content, $ed_summ );
    }

    /***********************************
     ** MAIN PUBLIC METHODS/FUNCTIONS **
     ***********************************/

    /**
     * Wiki login function
     * @param $user     Username to log in with
     * @param $pass     Password for the user
     * @return          False if fails, or array of data from the API if succeeds
     **/
    public function login( $user = null, $pass = null ) {
        self::DBGecho( "START: login('$user')" );
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
            self::DBGecho( "    Trying to retrieve saved credentials" );
            // Otherwise, try to retrieve saved credentials
            if (isset($this->bot['credentials'])) {
                $postdata   = $this->bot['credentials'];
            } else {
                return self::ERR_ret( self::ERR_error, "Login failed; no credentials supplied" );
            }
        }
        // Start trying to log in...
        $r  = self::query( $q, $postdata );
        if (isset($r['login']['result'])) {
            // Token required in more-recent MediaWiki versions
            if ($r['login']['result'] == 'NeedToken') {
                $postdata['lgtoken']    = $r['login']['token'];
                $r  = self::query( $q, $postdata );
            }
        } else {
            return self::ERR_ret( self::ERR_fatal, "Login failed; no result returned" );
        }
        if (isset($r['login']['result'])) {
            if ($r['login']['result'] !== 'Success') {
                // The login failed, probably incorrect credentials
                return self::ERR_ret( self::ERR_error, "Login failed; returned:".$r['login']['result'] );
            } else {
                return $r;
            }
        } else {
            return self::ERR_ret( self::ERR_fatal, "Login failed; no result returned" );
        }

        // Should never execute, but if does, ensure is a failure result
        return self::ERR_ret( self::ERR_error, "Unknown error logging ine" );
    }

    /**
     *  Logout function
     * @return          Falls out, thus returning null
     **/
     public function logout() {
         self::DBGecho( "START: logout()" );
         if (Bot_LOG !== false ) {
             self::log_actions();
         }
         self::query( '?action=logout&format=php' );
     }

    /**
     *  Function to get, and return, a current edit token
     * @return  The edit token obtained by pulling the main page
     **/
    public function get_edittoken() {
        self::get_page( self::Main_Page, true );
        return $this->token;
    }

    /**
     * @param $page     Name of page checking can edit
     * @param $user     Name of bot checking can edit; optional, defaults self
     * @param $content  Content of page checking can edit; optional
     * @return          True/False - indicator if can edit
     **/
    public function edit_permitted( $page, $user = null, $content = null ) {
        self::DBGecho( "START: edit_permitted('$page','$user','$content' )" );
        if ( $content === null )
            $content    = self::get_page( $page );
        if ( $user === null )
            $user   = self::user();
        if (preg_match('/\{\{(nobots|bots\|allow=none|bots\|deny=all|bots\|optout=all|bots\|deny=.*?'.preg_quote($user,'/').'.*?)\}\}/iS',$content))
            return false;

    // Pass the above, can edit page.
    return true;
    }

    /**
     * Page section fetching function
     *  This simply calls the get_page method with parameters reordered
     * @param $page     The title of the required page
     * @param $section  The page's section number
     * @param $gettoken If an edit token is required, also results in the
     *                  page's timestamp and revid being saved.
     * @param $revid    The revision ID (optional) to be fetched
     * @return          False if fails, or wikitext of desired page
     **/
    public function get_section( $page, $section, $gettoken = false, $revid = null ) {
        return self::get_page( $page, $gettoken, $revid, $section );
    }

    /**
     * General 'page-fetching' function
     * @param $page     The title of the required page
     * @param $gettoken If an edit token is required, also results in the
     *                  page's timestamp and revid being saved.
     * @param $revid    The revision ID (optional) to be fetched
     * @param $section  When a section of a page is required, pass section number
     * @return          False if fails, or wikitext of desired page
     **/
    public function get_page( $page, $gettoken = false, $revid = null, $section = null ) {
        if ( $section === null ) {
            self::DBGecho( "START: get_page('$page')" );
        } else {
            self::DBGecho( "START: get_section('$page','$section')" );
        }

        // If asked for an edit token when fetching page, query differs
        if ( $gettoken ) {
            self::DBGecho( "    Asking edit token" );
            $q  = '&prop=revisions|info&intoken=edit';
        } else {
            $q  = '&prop=revisions';
        }
        $q      .= '&titles='.urlencode($page).'&rvlimit=1&rvprop=content|timestamp|ids';

        // If asking for specific version, select such
        if ($revid !== null )
            $q  .= '&rvstartid='.$revid;

        // If asking for a section, pass to API
        if ( $section !== null )
            $q  .= '&rvsection='.$section;

        $r  = self::query_api( $q );
        if (!$r) {
            return self::ERR_ret( self::ERR_fatal, "No data returned by MediaWiki API" );
        }

        foreach ( $r['query']['pages'] as $t_page ) {
            // Now, stash page fetched and the revision ID.
            $this->pagetitle    = $page;
            $this->rev_time     = $t_page['revisions'][0]['timestamp'];
            $this->revid        = $t_page['revisions'][0]['revid'];

            // Save details of the edit token and the 'edit' start timestamp
            if ( $gettoken !== false ) {
                $this->token        = $t_page['edittoken'];
                $this->timestamp    = $t_page['starttimestamp'];
            }
            // Return the wiki-markup page content
            return $t_page['revisions'][0]['*'];
        }
        // If we hit here, we've not got a page back
        return self::ERR_ret( self::ERR_error, "Unknown error fetching wiki page" );
    }

    /**
     * Write page section function.
     *  This function as-per write_page, but with different parameter order and assumes a
     *  new section is being written as-default.
     *
     * @param $title    Title of page being accessed/written
     * @param $content  Content of page, or section, to write
     * @param $section  A numeric string for section number to edit or 'new' to append new
     * @param $summary  Edit summary, or new section name
     * @return          False if fails, otherwise the data returned by the API.
     **/
    public function write_section( $title, $content, $section = 'new', $summary = null ) {
        return self::write_page( $title, $content, $summary, $section );
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
    public function write_page( $title, $content, $summary = null, $section = null ) {
        if ( $section === null ) {
            self::DBGecho( "START write_page('$title')" );
        } else {
            self::DBGecho( "START: write_section('$title', '$section')" );
        }
        $q      = '?action=edit&format=php';
        $post   = array(
                'title'                             => $title,
                'summary'                           => $summary,
                ($this->botproc?'bot':'notbot')     => true,
                ($this->minor?'minor':'notminor')   => true
                );

        // Grab timestamp, even if not going to use it later.
        $e_timestamp    = $this->rev_time;
        if ( $this->readtime !== 0 ) {
            $e_timestamp    = $this->readtime;
        } elseif ( !$this->newpage ) {
            // Null timestamp, must be editing last-page retrieved if $new not true
            if ( $this->pagetitle !== $title ) {
                return self::ERR_ret( self::ERR_warn, "Cannot update a page that not previously retrieved" );
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

        $post['text']           = $content;
        $post['token']          = $this->token;
        $post['starttimestamp'] = $this->timestamp;

        // Default behaviours - These reset on every page write!
        $this->botproc  = true; // We're a bot
        $this->minor    = false; // We don't make minor edits
        $this->conflict = true; // Respect edit conflicts
        $this->newpage  = false; // Not writing new page unless say so
        $this->readtime = 0;

        $r = self::query( $q, $post );
        if ( isset($r['error']) ) {
            return self::ERR_ret( self::ERR_error, "API error, info:"
                .$r['error']['info']." Result:".$r['error']['code'] );
        }
        return $r;
    }

    /**
     * Function to get a page's unique ID.
     * @param $page Title of the page to retrieve ID of
     * @return      Returns page ID or an error
     **/
    public function get_pageid( $page ) {
        self::DBGecho( "START: get_pageid('$page')" );
        if ( $this->pagetitle == $page )
            return $this->revid; // Can avoid API call as page retrieved

        $q  = '&prop=revisions&titles='
            .urlencode( $page ).'&rvlimit=1&rvprop=ids'; //was content, asking ids for short result
        $r  = self::query_api( $q );
        if ( isset( $r['error'] ) ) {
            return self::ERR_ret( self::ERR_error, "API returned error, info:"
                .$r['error']['info']." Result ".$r['error']['code'] );
        }
        foreach ( $r['query']['pages'] as $info ) {
            return $info['pageid'];
        }
        // Shouldn't end up here, but...
        return self::ERR_ret( self::ERR_error, "Unknown error retrieving page ID" );
    }

    /**
     * Function to retrieve a page's table of contents (index)
     * @param $page     The title of the required page
     * @param $revid    Optional, the revision ID to fetch the TOC of
     * @return          False if fails, or the TOC in an array.
     *                  Note failure only if no page.
     **/
    public function get_toc( $page, $revid = null ) {
        $toc    = array();
        $toc[] = array( // This is the element layout as-per MediaWiki
            'toclevel'      => 0,
            'level'         => '0',
            'line'          => '',
            'number'        => '0',
            'index'         => '0',
            'fromtitle'     => $page,
            'byteoffset'    => 0,
            'anchor'        => ''
            );
        self::DBGecho( "START get_toc('$page')" );

        $q  = '&prop=sections&page='.urlencode($page);
        if ( $revid !== null )
            $q  .= '&rvstartid='.$revid;

        $r  = self::query_content( $q );
        if ( isset($r['error']) ) { // Error getting any page data
            return self::ERR_ret( self::ERR_error, "API error, info:"
                .$result['error']['info']." Result:".$result['error']['code'] );
        }
        $toc_elem   = $r['parse']['sections'];
        if ( empty($toc_elem) ) { // Empty, does that mean page doesn't exist?
            if ( self::get_page( $page ) == false) {
                return self::ERR_ret( self::ERR_warn, "Requested TOC for nonexistent page" );
            }
        }
        return array_merge( $toc, $toc_elem );
    }

    /**
     *  Prototype function to retrieve a list of data from the API
     * @param $query        The query string to pass to the API
     * @param $list_type    The type of list being requested
     * @param $continues    The continuation indicator name
     * @param $sub_result   The sub-array(s) containing results. Used where the
     *                      API query returns an array of arrays with needed data
     *                      in the inner arrays.
     * @return              An array of API results
     **/
    function get_a_list( $query, $list_type, $continues, $sub_result = null ) {
        self::DBGecho( "START: get_a_list('$query','$list_type','$continues','$sub_result')" );
        $list   = array();

        $r  = self::query_api( $query );
        if ( isset( $r['error'] ) ) {
            return self::ERR_ret( self::ERR_error, "API error, info:"
                .$r['error']['info']." Result:".$r['error']['code'] );
        }
        if ( $sub_result !== null ) {
            foreach( $r['query'][$sub_result] as $item )
                $list   = array_merge( $list, $item[$list_type] );
        } else {
            $list   = array_merge( $list, $r['query'][$list_type] );
        }
        while ( isset( $r['query-continue'] ) ) {
            $r  = self::query_api( $query, $r['query-continue'][$continues] );
            if ( isset( $r['error'] ) ) {
                return self::ERR_ret( self::ERR_error, "API error, info:"
                    .$r['error']['info']." Result:".$r['error']['code'] );
            }
            if ( $sub_result !== null ) {
                foreach( $r['query'][$sub_result] as $item )
                    $list   = array_merge( $list, $item[$list_type] );
            } else {
                $list   = array_merge( $list, $r['query'][$list_type] );
            }
        }
        if ( empty( $list ) ) {
            return self::ERR_ret( self::ERR_warn, "List is empty" );
        }

        return $list;
    }

    /**
     *  Get a list of pages linked-to from the given page
     * @param $page Page to query links from
     * @param $ns   Namespace(s) to restrict links to, defaults main
     * @return      Returns an array page names, or error
     **/
    public function get_page_links( $page, $ns = '0' ) {
        self::DBGecho( "START: get_page_links('$page','$ns')" );
        $list   = self::get_page_links_DETAIL( $page, $ns = '0' );
        if ( is_array( $list ) )
            array_walk( $list,
                    function( &$val, $key ) {
                        if (isset($val['title']))   $val    = $val['title'];
                    } );
        return $list;
    }

    /**
     *  Get a list of pages linked-to from the given page
     * @param $page Page to query links from
     * @param $ns   Namespace(s) to restrict links to, defaults main
     * @return      Returns an array of API data, or error
     **/
    public function get_page_links_DETAIL( $page, $ns = '0' ) {
        self::DBGecho( "START: get_page_links_DETAIL('$page','$ns')" );
        $q  = '&prop=links&pllimit='.$this->link_limit
            .'&titles='.urlencode( $page ).'&plnamespace='
            .urlencode( $ns );
        return self::get_a_list( $q, 'links', 'links', 'pages' );
    }

    /**
     *  Get a list of the members of a category
     * @param $category Name of category to get list of members of
     * @return          Returns an array of strings, each being a category member
     **/
    public function get_category_members( $category ) {
        self::DBGecho( "START: get_category_members_DETAIL('$category')" );
        $list   = self::get_category_members_DETAIL( $category );
        if ( is_array( $list ) )
            array_walk( $list,
                    function( &$val, $key ) {
                        if (isset($val['title']))     $val    = $val['title'];
                    } );
        return $list;
    }

    /**
     *  Get a list of the members of a category
     * @param $category Name of category to get list of members of
     * @return          Returns an array of API results
     **/
    public function get_category_members_DETAIL( $category ) {
        self::DBGecho( "START: get_category_members_DETAIL('$category')" );
        $q  = '&list=categorymembers&cmlimit='.$this->link_limit
            .'&cmtitle='.self::NS_Category
            .urlencode( $category );
        return self::get_a_list( $q, 'categorymembers','categorymembers' );
    }

    /**
     *  Get a list of links to a specific page
     * @param $page Page desire links to established
     * @return      Returns an array page titles
     **/
    public function get_links_here( $page ) {
        self::DBGecho( "START: get_links_here('$page')" );
        $list   = self::get_links_here_DETAIL( $page );
        if ( is_array($list) )
            array_walk( $list,
                    function( &$val, $key ) {
                        if (isset($val['title']))   $val    = $val['title'];
                    } );
        return $list;
    }

   /**
     *  Get a list of links to a specific page
     * @param $page Page desire links to established
     * @return      Returns an array of API results
     **/
    public function get_links_here_DETAIL( $page ) {
        self::DBGecho( "START: get_links_here_DETAIL('$page')" );
        $q  = '&list=backlinks&bllimit='.$this->link_limit
            .'&bltitle='
            .urlencode( $page );
        return self::get_a_list( $q, 'backlinks', 'backlinks' );
    }

    /**
     *  Function to get a list of pages using a template
     * @param $template Name of template want pages using
     * @return          List of pages using the template
     **/
    public function get_template_pages( $template ) {
        self::DBGecho( "START: get_templated_pages('$template')" );
        $q  = 'list=embeddedin&eilimit='.$this->link_limit
            .'&eititle='.self::NS_Template
            .urlencode( $template );
        return self::get_a_list( $q, 'embeddedin', 'embeddedin' );
    }

    /**
     *  Get a page's entire revision history, with timestamps
     * @param $page Page wish the edit history for
     * @return      An array of API results
     **/
    public function get_revision_history( $page ) {
        self::DBGecho( "START: get_revision_history('$page')" );
        $q  = '&prop=revisions&rvprop=timestamp|ids&rvlimit='.$this->list_limit
            .'&titles='.urlencode($page);
        return self::get_a_list( $q, 'revisions', 'revisions', 'pages' );
    }
}
?>
