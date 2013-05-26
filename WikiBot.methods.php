<?php
/**
 *  Title:      WikiBot.methods.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Version:    0.1.0-0
 *  Date:       October 20, 2012
 *  Description:
 *      File for inclusion with all standard page read/write functions
 *      used within WikiBot.class.php
 *      This is held in a separate file so 'tailored' classes can
 *      more-easily be constructed.
 *
 *      Copyright: CC-BY-2.5 (See Creative Commons website for full terms)
 *
 *  History
 *      0.1.0-0    2012-10-20   Brian McNeil
 *                              Create stable set of page functions from
 *                              tested WikiBot.class.php
 **/

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
    function get_section( $page, $section, $gettoken = false, $revid = null ) {
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
    function get_page( $page, $gettoken = false, $revid = null, $section = null ) {
        if ( $section === null ) {
            self::DBGecho( "Fetching page: '$page'" );
        } else {
            self::DBGecho( "Fetching section number $section from: '$page'" );
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

        $r  = $this->query_api( $q );
        if (!$r) {
            return self::ERR_ret( self::ERR_fatal, "No data returned by MediaWiki API" );
        }

        foreach ( $r['query']['pages'] as $t_page ) {
            // Now, stash page fetched and the revision ID.
            $this->bot['pagetitle'] = $page;
            $this->bot['rev_time']  = $t_page['revisions'][0]['timestamp'];
            $this->bot['revid']     = $t_page['revisions'][0]['revid'];

            // Save details of the edit token and the 'edit' start timestamp
            if ( $gettoken !== false ) {
                $this->bot['token']     = $t_page['edittoken'];
                $this->bot['timestamp'] = $t_page['starttimestamp'];
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
    function write_section( $title, $content, $section = 'new', $summary = null ) {
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
    function write_page( $title, $content, $summary = null, $section = null ) {
        self::DBGecho( "Writing to page: '$title'" );
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
            return self::ERR_ret( self::ERR_error, "API error, info:"
                .$result['error']['info']
                ." Result:".$result['error']['code'] );
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
        self::DBGecho( "Fetching TOC for: '$page'" );

        $q  = '&prop=sections&page='.urlencode($page);
        if ( $revid !== null )
            $q  .= '&rvstartid='.$revid;

        $r  = $this->query_content( $q );
        if ( isset($r['error']) ) { // Error getting any page data
            return self::ERR_ret( self::ERR_error, "API error, info:"
                .$result['error']['info']." Result:".$result['error']['code'] );
        }
        $toc_elem   = $r['parse']['sections'];
        if ( empty($toc_elem) ) { // Empty, does that mean page doesn't exist?
            if ( $this->get_page( $page ) == false) {
                return self::ERR_ret( self::ERR_warn, "Requested TOC for nonexistent page" );
            }
        }
        return array_merge($toc, $toc_elem);
    }
 ?>
