<?php
/**
 *  Title:      NewsieBot_parameters.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Version:    0.0.1-0
 *  Date:       October 13, 2012
 *  Description:
 *      Static file of parameters for bot
 *
 *      Copyright: CC-BY-2.5 (See Creative Commons website for full terms)
 *
 *  History
 *      0.0.1-0    2012-09-28   Brian McNeil
 *                              Create class
 **/

define('dB_ENGINE', 'mysql');
define('dB_HOST'. 'localhost');
define('dB_NAME', 'Botdatabase');
define('dB_USER', 'BotUsername');
define('dB_PASS', 'MyDatabasePassWord');

define('mW_USER', 'BotUsername');
define('mW_PASS', 'BotWikiPassword');
define('mW_WIKI', 'http://test.wikipedia.org');

define('Bot_LGMAX', 100); // Max actions or runs to record
define('Bot_LOG', false); // set to page where log goes
// You didn't think I'd upload *real* values, did you>
?>
