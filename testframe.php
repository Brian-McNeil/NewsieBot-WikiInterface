<?php
/**
 *  Title:      testframe.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Description:
 *      Test framework for bot
 *
 **/

define('CLASSPATH', '/home/wikinews/NewsieBot/classes/');
require_once(CLASSPATH.'config.class.php');
require_once(CLASSPATH.'WikiBot.class.php');

$newsiebot  = new WikiBot(mW_WIKI);
$pg         = "Wikinews:Newsroom";

if (!$newsiebot) {
    echo "Error initializing wikibot";
} else {
    $r = $newsiebot->login(mW_USER, mW_PASS);
    $newsiebot->quiet = false;
    if (!$r)
        die();

    // Try and see if this pulls the TOC
    $toc    = $newsiebot->get_toc( $pg );
    var_dump($toc);

    // Try for nonexistent page
    $toc   = $newsiebot->get_toc( "no-sooch-page" );
    var_dump($toc);

    // Try to make it a'splode
    $toc   = $newsiebot->get_toc( "Special:no-sooch-page" );
    var_dump($toc);

    echo "Logging out\r\n";
    $newsiebot->logout();
}
?>
