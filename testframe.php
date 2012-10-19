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
$pg         = "Project:Water cooler";

if (!$newsiebot) {
    echo "Error initializing wikibot";
} else {
    $r = $newsiebot->login(mW_USER, mW_PASS);
    $newsiebot->quiet = false;
    if (!$r)
        die();
    $newsiebot->runmsg  = $newsiebot->runmsg
                        ."\r\n:: Testing and Development run";

    // Try and see if this pulls the TOC
    $toc    = $newsiebot->get_toc( $pg );
    echo "Dumping returned data for page TOC:\r\n";
    var_dump($toc);

    $sectxt = $newsiebot->get_section( $pg, 0 );
    echo "Tried pulling 0th section; content:\r\n";
    var_dump($sectxt);


    echo "Logging out\r\n";
    $newsiebot->logout();
}
?>
