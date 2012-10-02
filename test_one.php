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
    var_dump($r);

    if (!$r)
        die();

    $page   = $newsiebot->get_page("Main Page");
    var_dump($page);
}
?>
