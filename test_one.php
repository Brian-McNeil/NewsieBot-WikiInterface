<?php
/**
 *  Title:      test_one.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Description:
 *      Test framework for bot
 *
 **/

require_once('./classes/config.class.php');
require_once('./classes/WikiBot.class.php');

$newsbot    = new WikiBot('https://en.wikinewsie.org');

if (!$newsiebot) {
    echo "Error initializing wikibot";
} else {
    $r = $newsiebot->login(mW_WIKI,mW_PASS);

    if (!$r)
        die();

    $page   = $newsiebot->get_page("Main Page");
    var_dump($page);
}
?>
