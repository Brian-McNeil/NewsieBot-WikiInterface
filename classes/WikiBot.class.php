<?php
/**
 *  Title:      WikiBot.class.php
 *  Author(s):  Brian McNeil [[n:Brian McNeil]]
 *  Description:
 *      Class(es) to handle bot interactions with MediaWiki
 *
 *
 **/

require_once('./HTTPcurl.class.php');

/**
 * This is the base class for interacting with a MediaWiki install
 * @author Brian McNeil
 **/
class WikiBot {
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
    __construct($wiki_url=self::DEFAULT_wiki, $wiki_api=self::DEFAULT_api, $ht_user=null,$ht_pass=null) {
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
        $this->bot  = $r;
        return true;
    }

    /**
     * Configuration function, creates, and returns, the cURL instance
     * @return      false if fails, otherwise the cURL instance
     **/
    private init_cURL( $wiki, $api ) {
        $r  = array();
        $r['URL']   = $wiki.$api;
        $r['cURL']  = new HTTPcurl();

        if ( !$r['cURL'] ) {
            return false;
        }
        $r['token']     = null;
        $r['timestamp'] = null;
        return $r;
    }

    /**
     * Destructor; frees up the bot instance
     * @return      void
     **/
    __destruct() {
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
        if ($postdata == null ) {
            $r  = $this->bot['cURL']->http_get(self::bot['URL'].$query);
        } else {
            $r  = $this->bot['cURL']->http_post(self::bot['URL'].$query, $postdata);
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
     * @return          False if fails, or array of data from the API if succeeds
     **/
    function login( $user = null, $pass = null ) {
        $q          = '?action=login&format=php';

        // If the username is passed in, then we use what we're given
        if (!$user) {
            // Otherwise, try to retrieve saved credentials
            if (isset($this->bot['credentials']) {
                $postdata   = $this->bot['credentials'];
            } else {
                throw new Exception ("Login failed; no credentials supplied");
                return false;   // Fail, don't have any saved credentials
            }
        } else {
            // Save the credentials we got before trying to use them
            $postdata   = array(
                        'lgname'        => $user,
                        'lgpassword'    => $pass
                        );
            $this->bot['credentials']   = $postdata;
        }
        // Start trying to log in...
        $r  = $this->query( $q, $postdata );
        if (isset($r['login']['result'])){
            // Token required in more-recent MediaWiki versions
            if ($r['login']['result'] == 'NeedToken') {
                $postdata['lgtoken']    = $r['login']['token'];
                $r  = $this->query( $q, $postdata );
            }
        }else {
            throw new Exception ("Login failed; no result returned");
            return false;   // It failed to give a result at-all
        }
        if (isset($r['login']['result'])) {
            if ($r['login']['result'] != 'Success') {
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
     * General 'page-fetching' function
     * @param $page     The title of the required page
     * @param $revid    The revision ID (optional) to be fetched
     * @return          False if fails, or wikitext of desired page
     **/
    function get_page( $page, $revid = null ) {
        $q  = '&prop=revisions&titles='.urlencode($page)
            .'&rvlimit=1&rvprop=content|timestamp';

        if ($revid !== null )
            $q  .= '&rvstartid='.$revid;

        $r  = $this->query_api( $q );
        if (!$r)
            return false;
        // Now, stash page fetched and the revision ID.
        $this->bot['pagetitle'] = $page;

        return $r['revisions'][0]['*'];
    }
}
?>
