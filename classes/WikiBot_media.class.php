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
 *      0.0.0-1     2012-10-20  Brian McNeil
 *                              Create.
 *      0.0.0-2     2012-10-22  Brian McNeil
 *                              Add media_location, media_uploader functions
 **/

require_once(CLASSPATH.'WikiBot.class.php');

class WikiBot_media extends WikiBot {

    const Version       = "WikiBot_media.class v0.0.0-2";

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

        $result = parent::__construct( $wiki_url, $wiki_api, $ht_user, $ht_pass, $quiet );
        if ( $result === false )
            return false;

        // Identify the class which is in-use
        if ( defined( 'WikiBot_Name' ) ) {
            $rmsg   = CRLF.'# Run of '.WikiBot_Name.' using ';
        } else {
            $rmsg   = CRLF.'# Unnamed bot using ';
        }
        $this->bot['runmsg']    = $rmsg.self::Version.CRLF
                                .'#:: Start: '.gmdate( 'Y-m-d H:i:m' );
        return $result;

    }

    /**
     * Destructor; frees up the bot instance
     * @return      void (assuming parent also returns void)
     **/
    function __destruct() {
        return parent::__destruct();
    }

    /**
     * Retrieve a media file's actual location.
     * @param $name The "File:" page on the wiki which the URL of is desired.
     * @return      The URL pointing directly to the media file
     *              (Eg http://upload.mediawiki.org/wikipedia/en/1/1/Example.jpg)
     **/
    function media_location ( $name ) {
        parent::DBGecho( "Retrieving location of media '".$name."'" );
        $q  = '?action=query&format=php&prop=imageinfo&titles='
            .urlencode($name).'&iilimit=1&iiprop=url';
        $r  = parent::query_api( $q );
        foreach ($r['query']['pages'] as $ret ) {
            if (isset($ret['imageinfo'][0]['url'])) {
                return $ret['imageinfo'][0]['url'];
            } else
                return parent::ERR_ret( parent::ERR_error, "Media not found" );
        }
        if ( isset( $r['error'] ) ) {
            return self::ERR_ret( parent::ERR_error, "API error, info:"
                .$r['error']['info']
                ." Result:".$r['error']['code'] );
        }
    }

    function media_uploader( $name ) {
        parent::DBGecho( "Finding media file's uploader for '".$name."'" );
        $q  = '?action=query&format=php&prop=imageinfo&titles='
            .urlencode($name).'&iilimit=1&iiprop=user';
        $r  = parent::query_api( $q );
        foreach ( $r['query']['pages'] as $pg ) {
            if ( isset( $pg['imageinfo'][0]['user'] ) ) {
                return  $ret['imageinfo'][0]['user'];
            } else {
                return parent::ERR_ret( parent::ERR_error, "Unable to establish media uploader" );
            }
        }
        if ( isset( $r['error'] ) ) {
            return self::ERR_ret( parent::ERR_error, "API error, info:"
                .$r['error']['info']
                ." Result:".$r['error']['code'] );
        }
    }

}
?>
