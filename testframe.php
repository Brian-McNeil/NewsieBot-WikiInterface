<?php
/**
 *  Title:      testframe.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Description:
 *      Test framework for bot
 *
 **/
define( 'WikiBot_Name', "WikiBot testing and development framework" );
define('CLASSPATH', '/home/wikinews/NewsieBot/classes/');
require_once(CLASSPATH.'WikiBot_media.class.php');

$newsiebot  = new WikiBot_media(mW_WIKI);


if (!$newsiebot) {
    echo "Error initializing wikibot";
} else {
    $r = $newsiebot->login(mW_USER, mW_PASS);
    $newsiebot->quiet = false;
    if (!$r)
        die();
    $newsiebot->runmsg  = "Logged in";

/*
    $toc    = $newsiebot->get_toc( "Project:Newsroom" );
    if ( $toc !== false )
        $newsiebot->runmsg  = "Successfully retrieved TOC for Newsroom";
    var_dump( $toc );


    echo "Testing image retrieval".CRLF;
    $x  = $newsiebot->get_used_media( "Main Page" );
    if ( $x !== false )
        $newsiebot->runmsg  = "Successfully retrieved used media for Main Page";
    var_dump( $x );

    $u  = $newsiebot->upload_media( "File:Static.gif",
                        "/home/wikinews/NewsieBot/static.gif",
                        "This test upload can be deleted at-will as image is unused and unneeded",
                        "Upload edit summary" );

    $list   = $newsiebot->get_category_members( 'Politicians' );
    if ( $list !== false )
        $newsiebot->runmsg  = "Retrieved category members for 'Politicians'";
    var_dump( $list );

    $list   = $newsiebot->get_page_links( 'Main Page' );
    if ( $list !== false )
        $newsiebot->runmsg  = "Got pages linked-to from Main Page";
    var_dump( $list );

    $list   = $newsiebot->get_links_here( 'Talk:Main Page' );
    if ( $list !== false )
        $newsiebot->runmsg  = "Got pages linking to 'Talk:Main Page'";
    var_dump( $list );

    $list   = $newsiebot->get_template_pages( 'United Kingdom' );
    if ( $list !== false )
        $newsiebot->runmsg  = "Got pages using template 'United Kingdom'";
    var_dump( $list );
*/

    $newsiebot->runmsg  = "Trying to pull Main Page revision history";

    $history    = $newsiebot->get_revision_history( 'Main Page' );
    var_dump( $history );

    echo "Calling logout".CRLF;
    $newsiebot->logout();
}
?>
