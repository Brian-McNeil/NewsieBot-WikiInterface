<?php
/**
 *  Title:      testframe.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Description:
 *      Test framework for bot
 *
 **/

define('CLASSPATH', '/home/wikinews/NewsieBot/classes/');
require_once(CLASSPATH.'WikiBot.class.php');

$newsiebot  = new WikiBot(mW_WIKI);
$pg         = "Project:Water cooler/miscellaneous";

if (!$newsiebot) {
    echo "Error initializing wikibot";
} else {
    $r = $newsiebot->login(mW_USER, mW_PASS);
    $newsiebot->quiet = false;
    if (!$r)
        die();
    $newsiebot->runmsg  = $newsiebot->runmsg
                        .CRLF.":: Testing and Development run";

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

    echo "Logging out\r\n";
    $newsiebot->logout();
}
?>
