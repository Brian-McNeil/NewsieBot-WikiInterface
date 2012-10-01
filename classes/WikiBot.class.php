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
     * General query function; sends a query to the target MediaWiki using the API
     * @param $query    Passed-in query string (eg '&prop=revisions&title=Foo')
     * @param $postdata Optional data to go by POST method
     * @return          False if fails, or unserialized result data
     **/
    function query( $query, $postdata = null ) {
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
     * General 'page-fetching' function
     * @param $page     The title of the required page
     * @param $revid    The revision ID (optional) to be fetched
     * @return          False if fails, or wikitext of desired page
     **/
    function get_page( $page, $revid = null ) {
        $q  = '&prop=revisions&titles='.urlencode($page).'&rvlimit=1&rvprop=content|timestamp';

        if ($revid !== null )
            $q  .= '&rvstartid='.$revid;

        $r  = $this->query( $q );
        if (!$r)
            return false;
        // Now, stash page fetched and the revision ID.
        $this->bot['pagetitle'] = $page;

        return $r['revisions'][0]['*'];
    }
}
?>
