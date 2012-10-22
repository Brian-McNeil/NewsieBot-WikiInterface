<?php
/**
 *  Title:      testframe.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Description:
 *      Test framework for bot
 *
 **/

define('CLASSPATH', '/home/wikinews/NewsieBot/classes/');
require_once(CLASSPATH.'WikiBot_media.class.php');

$newsiebot  = new WikiBot_media(mW_WIKI);
$pg         = "Project:Water cooler/miscellaneous";

if (!$newsiebot) {
    echo "Error initializing wikibot";
} else {
    $r = $newsiebot->login(mW_USER, mW_PASS);
    $newsiebot->quiet = false;
    if (!$r)
        die();
    $newsiebot->runmsg  = "Testing and Development run";

    // Try and see if this pulls the TOC
    $toc    = $newsiebot->get_toc( $pg );
//    echo "Dumping returned data for page TOC:".CRLF;
//    var_dump($toc);

    $sectxt = $newsiebot->get_section( $pg, '2', true );
    echo "Tried pulling 2nd section; content:".CRLF;
    var_dump($sectxt);

    $sectxt     .= CRLF."::* Ignore me, I'm just a bot trying something out --~~~~";
//    $write = $newsiebot->write_section( $pg, $sectxt, '2' );
//    if ( !$write )  var_dump( $newsiebot );

    $newsiebot->conflict = false; // Not really an edit conflict, but want to write the new section regardless
    $sectxt     = "Hi, I'm a bot! --~~~~";
//    $write = $newsiebot->write_section( $pg, $sectxt, 'new', "A bot-generated section" );
//    if ( !$write )  var_dump( $newsiebot );

    echo "Getting file location".CRLF;
    $loc    = $newsiebot->media_location( "File:Example.png" );
    var_dump( $loc );

//    echo "Getting file location".CRLF;
//    $loc    = $newsiebot->media_location( "File:Nonexistent-file-requested.png" );
//    var_dump( $loc );

    echo "Testing image retrieval".CRLF;
    $x  = $newsiebot->used_media( "Main Page" );
    var_dump( $x );

    echo "Logging out".CRLF;
    $newsiebot->logout();
}
?>
