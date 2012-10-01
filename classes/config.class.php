<?php
/**
    Title:		NewsieBot: config.class.php
    Author(s):	Brian McNeil ([[n:Brian McNeil]])
    Description:
        Initialise a class containing relevant information for an
        instance of NewsieBot

    Version History
    ===============
    Vers.		Date		Author		Summary
    0.0.1		2012-09-19	BMcN		Creation

    Copyright:	CC-BY-2.5 - http://creativecommons.org/licenses/by/2.5/
 **/
require_once(CLASSPATH.'NewsieBot_parameters.php');

class bot_config {
    public $bot;
    private $cf;

    function __construct ( $debug=false ) {
        $this->dbg("Configuring NewsieBot config instance ",5);

        $this->cf = array(
            'debug'	=> $debug,
            'lvl'	=> "oFEWSI!!!!!!!!!!", //Fatal,Error,Warning,Status,Info,eh?
            'vers'  => "0.0.1",
            'errmsg' => "Error not caught/documented",
            'errlevel' => 1
                );
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
        $this->dbg("Returning database connection parameters",5);
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
        $this->dbg("Destructing NewsieBot config instance",5);
        $this->dbg("    Version:".$this->cf['vers'],5);
        unset($this->bot);
        unset($this->cf);
    }

    private function dbg( $str, $lev ) {
        if ($this->cf['debug'])
            if ($this->cf['debug'] >= $lev )
                echo date('c').'  ['.substr($this->cf['lvl'],$lev,1)."] $str\r\n";
    }
}
?>
