<?php
/**
 *  Title:      Sandbox_Reset.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Description:
 *          Basic function to reset the Wikinews sandbox if it has not been edited
 *          within the last six hours.
 *
 **/

define('CLASSPATH', '/home/wikinews/NewsieBot/classes/');
require_once(CLASSPATH.'config.class.php');
require_once(CLASSPATH.'WikiBot.class.php');

$newsiebot  = new WikiBot(mW_WIKI);
$sandbox    = 'Wikinews:Sandbox';

if (!$newsiebot) {
    echo "Error initializing wikibot";
} else {
    $r = $newsiebot->login(mW_USER, mW_PASS);
    $newsiebot->quiet = false;
    if (!$r) {
        echo "Failed to log in correctly\r\n";
        return false;
    }
    $newsiebot->quiet   = false;
    // Fetch Sandbox page, and edit token.
    $page   = $newsiebot->get_page( $sandbox, true );
    $content    = '{{sandbox}}';

    if ($page !== false ) {
        // MediaWiki timestamps look like: "2012-10-12T16:52:52Z"
        $timestamp  = $newsiebot->rev_time; // Grab the timestamp
        $sixHRS     = time() - 6 * 60 * 60;
        $compdate   = gmdate( 'Y-m-d', $sixHRS ).'T'.gmdate( 'H:i:s', $sixHRS ).'Z';
        echo "Page timestamp:$timestamp \r\n";
        echo "Rset timestamp:$compdate \r\n";
        if ( ( strcmp( $compdate, $timestamp ) > 0 ) && ( strcmp( $page, $content ) !== 0 ) ) {
            // Time to reset the sandbox...
            $r  = $newsiebot->write_page( $sandbox, $content, "Clear sandbox, untouched for six hours" );
            if ( $r !== false ) echo "Sandbox reset.\r\n";
        }
    }
}
