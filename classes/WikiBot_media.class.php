<?php
/**
 *  Title:      WikiBot_media.class.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Version:    0.0.0-2
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

    const Version    = "WikiBot_media.class v0.0.0-2";

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
        $this->Rrunmsg  = $rmsg.self::Version
                        .CRLF.'#:: Start, '.gmdate( 'Y-m-d H:i:m' );
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
     *  Wiki login function. Calls parent login, then sets request timeout
     *  length to 1 hour, as-required for large media file uploads
     * @param $user     Username to log in with
     * @param $pass     Password for the user
     * @return          False if fails, or array of data from the API if succeeds
     **/
    public function login( $user = null, $pass = null ) {
        $r  = parent::login( $user, $pass );

        if ( $r !== false ) {
            $this->cURL_request_timeout = 3600;
        }
        return $r;
    }

    /**
     * Retrieve a media file's actual location.
     * @param $media    The "File:" page on the wiki which the URL of is desired.
     * @return          The URL pointing directly to the media file
     *                  (Eg http://upload.mediawiki.org/wikipedia/en/1/1/Example.jpg)
     **/
    public function media_location ( $media ) {
        parent::DBGecho( "START: media_location('$media')" );
        $q  = '?action=query&format=php&prop=imageinfo&titles='
            .urlencode($media).'&iilimit=1&iiprop=url';
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

    /**
     * Find out the uploader of a media file
     * @param $media    Name of the media file
     * @return          The username of who uploaded the file
     **/
    public function media_uploader( $media ) {
        parent::DBGecho( "START: media_uploader('$media')" );
        $q  = '&prop=imageinfo&titles='
            .urlencode( $media ).'&iilimit=1&iiprop=user';
        $r  = parent::query_api( $q );
        foreach ( $r['query']['pages'] as $pg ) {
            if ( isset( $pg['imageinfo'][0]['user'] ) ) {
                return  $ret['imageinfo'][0]['user'];
            } else {
                return parent::ERR_ret( parent::ERR_error, "Unable to establish media uploader" );
            }
        }
        if ( isset( $r['error'] ) ) {
            return parent::ERR_ret( parent::ERR_error, "API error, info:"
                .$r['error']['info']." Result:".$r['error']['code'] );
        }
    }

    /**
     *  Function to get a list of images/media used in a page
     * @param $page Page to find used media of
     * @return      List of media titles, or error.
     **/
    public function get_used_media( $page ) {
        parent::DBGecho( "START: used_media('$page')" );
        $list   = self::get_used_media_DETAIL( $page );
        if ( is_array( $list ) )
            array_walk( $list,
                    function( &$val, $key ) {
                        if (isset($val['title']))   $val    = $val['title'];
                    } );
        return $list;
    }

    /**
     * Used media function, builds list of images and other media used in
     * a page.
     * @param $page Name of page to retrieve media for
     * @return      An array containing all used media on the page
     **/
    public function get_used_media_DETAIL( $page ) {
        parent::DBGecho( "START: used_media_DETAIL('$page')" );
        $q  = '&prop=images&imlimit=500&titles='
            .urlencode( $page );
        return parent::get_a_list( $q, 'images', 'images', 'pages' );
    }

    /**
     *  Function to upload locally-held media file.
     * @param $media    Name of the "File:" being uploaded
     * @param $file_loc Location of the media file
     * @param $desc     The description page text
     * @param $comment  Edit comment for the upload
     * @return
     **/
    public function upload_media( $media, $file_loc, $desc = '', $comment = '' ) {
        parent::DBGecho( "START: upload_media('$media','$file_loc','$desc','$comment')" );
        $q      = '?action=upload&format=php';
        $pars   = array(
                'filename'          => $media,
                'comment'           => $comment,
                'text'              => $desc,
                'file'              => '@'.$file_loc,
                'ignorewarnings'    => '1'
            );
        if ( $this->token ) {
            $pars['token']  = $this->token;
        } else {
            $pars['token']  = parent::get_edittoken();
        }
        // Try to pull the page's ID for later use
        $pgid   = parent::get_pageid( $media );

        $r  = parent::query( $q, $pars );
        if ( isset( $r['error'] ) )
            return parent::ERR_ret( parent::ERR_error, "Media upload failed, info:"
                .$r['error']['info']." Result:".$r['error']['code'] );

        if ( $pgid !== false ) {
            // If got page's ID, need to use page_write to update desc
            $this->newpage  = true;
            $this->conflict = false;
            $r  = parent::write_page( $media, $desc, $comment );
            if ( isset( $r['error'] ) )
                return parent::ERR_ret( parent::ERR_warn, "Failed updating media description, info:"
                    .$r['error']['info']." Result:".$r['error']['code'] );
        }
        return parent::ERR_ret( parent::ERR_success, "Media '$media' uploaded" );
    }

    /**
     *  Function to upload copy of media file specified by URL
     * @param $media    Name of the "File:" being uploaded
     * @param $file_url URL pointing to the media file to upload
     * @param $desc     The description page text
     * @param $comment  Edit comment for the upload
     * @return
     **/
    public function copy_media( $media, $file_url, $desc = '', $comment = '' ) {
        parent::DBGecho( "START: copy_media('$media','$file_url','$desc','$comment')" );
        $q      = '?action=upload&format=php';
        $pars   = array(
                'filename'          => $media,
                'comment'           => $comment,
                'text'              => $desc,
                'url'               => $file_url,
                'ignorewarnings'    => '1'
            );
        if ( $this->token ) {
            $pars['token']  = $this->token;
        } else {
            $pars['token']  = parent::get_edittoken();
        }
        // Try to pull the page's ID for later use
        $pgid   = parent::get_pageid( $media );

        $r  = parent::query( $q, $pars );
        if ( isset( $r['error'] ) )
            return parent::ERR_ret( parent::ERR_error, "Media copy failed, info:"
                .$r['error']['info']." Result:".$r['error']['code'] );

        if ( $pgid !== false ) {
            // If got page's ID, need to use page_write to update desc
            $this->newpage  = true;
            $this->conflict = false;
            $r  = parent::write_page( $media, $desc, $comment );
            if ( isset( $r['error'] ) )
                return parent::ERR_ret( parent::ERR_warn, "Failed updating media description, info:"
                    .$r['error']['info']." Result:".$r['error']['code'] );
        }
        return parent::ERR_ret( parent::ERR_success, "Media '$media' uploaded copy" );
    }
}
?>
