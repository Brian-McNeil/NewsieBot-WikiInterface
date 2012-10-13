<?php
/**
 *  Title:      Sandbox_Reset.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Version:    0.1.0-0
 *  Date:       October 13, 2012
 *  Description:
 *      Basic function to reset the Wikinews sandbox if it has not been edited
 *      within the last six hours.
 *
 *      Copyright: CC-BY-2.5 (See Creative Commons website for full terms)
 *
 *  History
 *      0.1.0-0    2012-10-13   Brian McNeil
 *                              Create: Straightforward bot process.
 **/

define('CLASSPATH', '/home/wikinews/NewsieBot/classes/');
require_once(CLASSPATH.'config.class.php');
require_once(CLASSPATH.'WikiBot.class.php');

$newsiebot  = new WikiBot(mW_WIKI);
$sandbox    = 'Wikinews:Sandbox';

if (!$newsiebot) {
    echo "Error initializing NewsieBot\r\n";
} else {
    $r = $newsiebot->login(mW_USER, mW_PASS);
    $newsiebot->quiet = false; // More-verbose logging
    if (!$r) {
        echo "Failed to log in correctly\r\n";
        return false;
    }
    // Fetch Sandbox page, and edit token.
    $page   = $newsiebot->get_page( $sandbox, true );
    $content    = '{{sandbox}}';
    $newsiebot->runmsg  = $newsiebot->runmsg
                        ."\r\n:: Check Sandbox";
    if ($page !== false ) {
        // MediaWiki timestamps look like: "2012-10-12T16:52:52Z"
        $timestamp  = $newsiebot->rev_time; // Grab the timestamp of current revision
        // Work out 'now' minus six hours in MW-date-format
        $sixHRS     = time() - 6 * 60 * 60;
        $compdate   = gmdate( 'Y-m-d', $sixHRS ).'T'.gmdate( 'H:i:s', $sixHRS ).'Z';
        if ( !$newsiebot->quiet ) { // If being 'noisy', show the timestamps in log
            echo "Page timestamp:$timestamp \r\n";
            echo "Rset timestamp:$compdate \r\n";
        }
        // Last edit older than compare date? Page not 'pristine'?
        if ( ( strcmp( $compdate, $timestamp ) > 0 ) && ( strcmp( $page, $content ) !== 0 ) ) {
            // Time to reset the sandbox...
            $r  = $newsiebot->write_page( $sandbox, $content, "Clear sandbox, untouched for six hours" );
            if ( $r !== false ) {
                echo "Sandbox reset.\r\n";
                $newsiebot->runmsg  = $newsiebot->runmsg
                                    ."\r\n:: RESET Sandbox";
            }

        }
    }
}
?>
