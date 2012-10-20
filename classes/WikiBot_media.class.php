<?php
/**
 *  Title:      WikiBot_media.class.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Version:    0.0.0-1
 *  Date:       October 20, 2012
 *  Description:
 *      Class to extend the basic WikiBot.class.php with a range of functions
 *      for handling media files and pages.
 *
 *      Copyright: CC-BY-2.5 (See Creative Commons website for full terms)
 *
 *  History
 *      0.0.0-1    2012-10-20   Brian McNeil
 *                              Create.
 **/

require_once(CLASSPATH.'WikiBot.class.php');

class WikiBot_media extends WikiBot {

    /**
     * Constructor function; simply calls parent constructor
     * @param $wiki_url     The "base" URL for the wiki
     * @param $wiki_api     The path to append to base for the API
     * @param $ht_user      Optional HTTP-Auth username
     * @param $ht_pass      Optional HTTP-Auth password
     * @param $quiet        Default true; optional output tracing/debug parameter
     * @return              True or False (as-returned by parent class)
     *
     **/
    function __construct( $wiki_url = parent::DEFAULT_wiki, $wiki_api = parent::DEFAULT_api,
                            $ht_user = null, $ht_pass = null, $quiet = true ) {

        return parent::__construct( $wiki_url, $wiki_api, $ht_user, $ht_pass, $quiet );

    }

    /**
     * Destructor; frees up the bot instance
     * @return      void (assuming parent also returns void)
     **/
    function __destruct() {
        return parent::__destruct();
    }

}
?>
