<?php
/**
 *  Title:      test_two.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Description:
 *      Test framework for bot
 *      Test two:   Log in, retrieve page, write back with
 *                  extra line; rewrite original, 'fake'
 *                  edit conflict.
 *
 **/

define('CLASSPATH', '/home/wikinews/NewsieBot/classes/');
require_once(CLASSPATH.'config.class.php');
require_once(CLASSPATH.'WikiBot.class.php');

$newsiebot  = new WikiBot(mW_WIKI);
$pg         = "Wikinews:Sandbox";

if (!$newsiebot) {
    echo "Error initializing wikibot";
} else {
    $r = $newsiebot->login(mW_USER, mW_PASS);
    $newsiebot->quiet = false;
    if (!$r)
        die();

    // Ask for a token to be retrieved
    $page   = $newsiebot->get_page($pg, true);
    var_dump($page);
    echo "\r\nAnd, check a couple of gets\r\n";
    echo "Edit token:".$newsiebot->token."\r\n";
    echo "Page title:".$newsiebot->pagetitle."\r\n";
    echo "Timestamp:".$newsiebot->timestamp."\r\n";
    $old_timestamp  = $newsiebot->rev_time; // Save timestamp for subsequent EC.

    $newpage    = $page."\nAdding some text to the test page";

    $r  = $newsiebot->write_page( $pg, $newpage, "A test edit" );

    $r  = $newsiebot->write_page( $pg, $page,
        "A test rollback", false, true, null, null, false, true );

    $discard_page   = $newsiebot->get_page($pg, true);
    sleep(5);  // quick nap
    echo "Go edit...\r\n";
    sleep(45); // minor snooze

    $r  = $newsiebot->write_page( $pg, $newpage,
        "Force conflict", false, true, null, null, false, false,
        $old_timestamp);

    if ( $r == false ) {
        echo "Error editing page\r\n    Msg: ".$newsiebot->error." Severity: ".$newsiebot->errcode."\r\n";
    } else {
        echo "Returned:\r\n";
        var_dump($r);
    }

    echo "Logging out\r\n";
    $newsiebot->logout();
}
?>
