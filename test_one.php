<?php
/**
 *  Title:      test_one.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Description:
 *      Test framework for bot
 *      Test one: Log in and retrieve a page
 *
 **/

define('CLASSPATH', '/home/wikinews/NewsieBot/classes/');
require_once(CLASSPATH.'config.class.php');
require_once(CLASSPATH.'WikiBot.class.php');

$newsiebot    = new WikiBot(mW_WIKI);

if (!$newsiebot) {
    echo "Error initializing wikibot";
} else {
    $r = $newsiebot->login(mW_USER, mW_PASS);

    if (!$r)
        die();

    // Although not editing, ask for a token to be retrieved
    $page   = $newsiebot->get_page("Wikinews:Sandbox", true);
    var_dump($page);
    echo "\r\nAnd, check a couple of gets\r\n";
    echo "Edit token:".$newsiebot->token."\r\n";
    echo "Page title:".$newsiebot->pagetitle."\r\n";
    echo "Timestamp:".$newsiebot->timestamp."\r\n";
    echo "Logging out\r\n";
    $newsiebot->logout();
}
?>
