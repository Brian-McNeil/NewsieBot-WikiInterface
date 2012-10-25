This is a starting README

Existing, and tested, public functions

WikiBot.class

__construct( $wiki_url, $wiki_api, $ht_user, $ht_pass, $quiet )

request_timeout( $seconds )
login( $user, $pass )
logout()
get_section( $page, $section, $gettoken, $revid )
get_page( $page, $gettoken, $revid, $section )
write_section( $title, $content, $section, $summary )
write_page( $title, $content, $summary, $section )
get_pageid( $page )
get_toc( $page, $revid )
get_page_links( $page, $ns )
get_category_members ( $category )
get_links_here( $page )
get_template_pages( $template )

WikiBot_media.class

__construct( $wiki_url, $wiki_api, $ht_user, $gt_pass, $quiet )

login( $user, $pass )
media_location( $name )
media_uploader( $media )
get_used_media( $page )
upload_media ( $media, $file_loc, $desc, $comment )
copy_media ( $media, $file_url, $desc, $comment ) [UNTESTED]



TO-DO:
    In WikiBot.class

get_subpages
get_transclusions

    In WikiBot_media.class
where_included
duplicate_images

    Elsewhere

move_page
delete_page
delete_revisions
undelete_page
protect_page
block_user
unblock_user
manage_userrights
get_user_contribs
email_user

--- Extra classes ---

Extend the base WikiBot.class.php with:
* Category handling
* Image handling
* Template handling
* Links/Where used
* Non-editing functions (eg, emailing users).
* Administrative functions
* 'Crat functions

--- Workarounds ---
* Links in DPL and some ParserFunction conditional code don't end up in the link table
  Should be able to check what extensions in the wiki, and extend any link/W.U. functions
  to pull said data by rendering page and crawling for links (expensive, ugly, might nudge MW
  devs to fix it).
