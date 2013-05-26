<?php
/**
 *  Title:      config.class.php
 *  Author(s):  Brian McNeil ([[n:Brian McNeil]])
 *  Version:    0.0.2-0
 *  Date:       October 13, 2012
 *  Description:
 *      Initialise a class for use of NewsieBot. This assumes the bot will
 *      work with a wiki, and a database. Database parameters and details
 *      for in-use wiki are pulled from NewsieBot_parameters.php
 *
 *      Copyright: CC-BY-2.5 (See Creative Commons website for full terms)
 *
 *  History
 *      0.0.1-0    2012-09-28   Brian McNeil
 *                              Create class
 *      0.0.2-0    2012-10-13   Brian McNeil
 *                              Tear out unused 'debug' stuff; to redo at later date
 **/

require_once(CLASSPATH.'NewsieBot_parameters.php');

class bot_config {
    public $bot;
    private $cf;

    function __construct ( $debug=false ) {

        $this->cf = array();
        $this->cf['quiet']	= true; // Use for debug (to be implemented)
        $this->cf['database_cfg'] = $this->set_dbparameters();
        $this->bot = $this->setparameters($this->cf['database_cfg']);
    }

    private function set_dbparameters() {
        $r = array(
                // Define the parameters required for database
                'engine'	=> dB_ENGINE,
                'dbhost'	=> dB_HOST,
                'dbname'	=> dB_NAME,
                'dbuser'	=> dB_USER,
                'dbpass'	=> dB_PASS
                );
        $r['PDOflags']		= array(
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                );
        $r['PDOconnect']	= $r['engine'].":host=".$r['dbhost'].";dbname=".$r['dbname'];
        return $r;
    }

    private function setparameters($pars) {
        $r = array();
        $r['conn']	= array(
                   $pars['PDOconnect'],
                   $pars['dbuser'],
                   $pars['dbpass'],
                   $pars['PDOflags']
               );
        $r['wiki_credentials']	= array(
                    // Define the parameters for the bot's wiki user account
                    // These more-likely to end up in a different class for wiki access
                    'wikiuser'	=> mW_USER,
                    'wikipass'	=> mW_PASS,
                    'wikiURL'   => mW_WIKI
                );
        return $r;
    }

    public function get_conn_pars() {
        $r	= array(
              $this->bot['conn'][0],
              $this->bot['conn'][1],
              $this->bot['conn'][2],
              $this->bot['conn'][3],
              null
            );
        return $r;
    }

    function __destruct() {
        unset($this->bot);
        unset($this->cf);
    }
}
?>
