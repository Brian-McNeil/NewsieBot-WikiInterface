<?php
/**
    Title:		NewsieBot: mediawatch.class.php
    Author(s):	Brian McNeil ([[n:Brian McNeil]])
    Description:
        Class containing functions for managing data related to the
        wikinewsie.org site manager database and tracking of media files
        in-use on English Wikinews from Commons.
        Where such files have appeared on critical pages (eg main page
        or published) and deleted from Commons,
        then such files can be restored from the JWS wiki.

    Version History
    ===============
    Vers.		Date		Author		Summary
    0.0.1		2012-09-19	BMcN		Creation

    Copyright:	CC-BY-2.5 - http://creativecommons.org/licenses/by/2.5/
*/

// This class is for handling tables associated with managing the monitoring of
// media on two wikis (developed for commons vs wikines).
// Three tables are used, and track the pages using images, links between them,
// and the images themselves.
// MediaWiki-specific code is not included in this class.
class mediawatch {
    private $dB;
    private $cf;

    function __construct ( $config_obj, $debug=false ) {
        $this->cf = array(
            'debug'	=> $debug,
            'lvl' => "oFEWSI!!!!!!!!!!", //Fatal,Error,Warning,Status,Info,eh?
            'vers' => "0.0.1",
            'errmsg' => "Error not caught/documented",
            'errlevel' => 1
            );
        $this->dbg("Configuring mediawatch instance ",5);

        // Pull all used SQL statements into the config array
        $this->cf['sql'] = $this->getprepared();

        $this->dB = array();

        // Pull the database connection parameters from the configuration
        // object. Then, using PDO, make a connection to the database.
        $this->dB['conn']	= $config_obj->get_conn_pars();
        $this->dB['PDO']	= $this->connectdb();

        // Now connected to the database, turn all SQL query 'templates'
        // into prepared statements for future use.
        $this->dB['Q']		= $this->preparequeries($this->cf['sql']);

        // Some semblance of error handling
        if (isset($this->cf['failed'])) {
            $this->dbg("Failed configuring mediawatch instance",1);
            $this->dbg($this->cf['errmsg'],2);
            die($this->cf['errlevel']);
        }
    }

    // Stuff all relevant prepared SQL queries into an array
    // See where these are pushed thru PDO (preparequeries) to set up and
    // return an array of query referrers for future use.
    private function getprepared() {
        $this->dbg("Loading prepared SQL statements",5);
        $r          = array();

        // Define templates for insert functions (INSERT)
        // This works as-follows: 'table_shortname' = SQL query 'template'
        // Within the query code, fields in the table are bound to named array elements for later use.
        // Thus: 'person' => "INSERT INTO People (SSN, Name, Age) VALUES (:SSN,:Name,:Age)"
        // Can be used: $this->( 'person', $record );
        // with $record an array containing named elements 'SSN','Name' and 'Age'
        $r['INSERT']    = array(
'page'  => "INSERT INTO nBot_articles (page_id, page_title, page_pubdate, page_changed) VALUES (:page_id, :page_title, :page_pubdate, :page_changed)",
'image' => "INSERT INTO nBot_images (image_id, image_title, image_URL, image_uploader, image_dateadded, image_changed, image_lastseen, image_saved)".
                                        " VALUES (:image_id, :image_title, :image_URL, :image_uploader, :image_dateadded, :image_changed, :image_lastseen, :image_saved)",
'link'  => "INSERT INTO nBot_links (page_id, image_id) VALUES (:page_id, :image_id)"
            );
        // FIND is for SELECT statements constrained to returning one record only
        $r['FIND']      = array(
'page'  => "SELECT * FROM nBot_articles WHERE page_id = :page_id LIMIT 1",
'image' => "SELECT * FROM nBot_images WHERE image_id = :image_id LIMIT 1",
'link'  => "SELECT * FROM nBot_links WHERE page_id = :page_id AND image_id = :image_id LIMIT 1"
            );
        // UPDATEs
        $r['UPDATE']    = array(
'page'  => "UPDATE nBot_articles SET page_title=:page_title, page_pubdate=:page_pubdate, page_changed=:page_changed ".
                "WHERE page_id = :page_id",
'image' => "UPDATE nBot_images SET image_URL=:image_URL, image_uploader=:image_uploader, image_changed=:image_changed, image_lastseen=:image_lastseen, image_saved=:image_saved ".
                "WHERE image_id = :image_id"
            );
        // DEKETEs Only really want to delete links, but pages included for future use handling redirects
        $r['DELETE']    = array(
'page'  => "DELETE from nBot_articles WHERE page_id = :page_id",
'link'  => "DELETE FROM nBot_links WHERE page_id = :page_id AND image_id = :image_id"
            );
//TBD: finds (select), updates, deletions

    return $r;
    }

    // Take the previously defined list of SQL query 'templates' and turn them
    // into actual prepared queries for faster execution,
    // and simpler use in mainline code.
    function preparequeries($query_list) {
        $this->dbg("Initialising prepared SQL statements",4);
        $r      = array();
        $targ	= $this->dB['PDO'];

        // Queries are grouped as 'types' (eg 'INSERT' contains all
        // query 'templates' which are used to create prepared insert queries).
        foreach( $query_list as $qry_type =>$qtype_list ) {
            $this->dbg(" Preparing queries of type:'$qry_type'",4);
            $q  = strtoupper($qry_type);
            $qlst = array();
            try {
                foreach( $qtype_list as $qry_name => $qry_template ) {
                    $this->dbg("  Query name '$qry_name' is: $qry_template", 5);
                    $qlst[$qry_name] = $targ->prepare( $qry_template );
                }
            } catch(PDOException $ex) {
                $this->dbg("Error initialising prepared statement for [$qry_name]",2);
                $this->cf['failed'] = 1;
                $this->cf['errmsg'] = $ex->getMessage();
            }
            $r[$q]  = $qlst;
        }

        if (sizeof($r) == 0) {
            throw new Exception("Failed to initialise any prepared SQL queries");
        }
        return $r;
    }

    // This is one of the most straightforward functions used, insertion of a
    // page record.
    // The SQL statement 'template' used to set up ['Q']['insert_page'] will
    // require an array of named values (currently 4 items). This is passed
    // into this function, which uses the prepared query to insert the record,
    // or simply returns failure.
    // N.B.   This is "INSERT_apage" to differentiate from "INSERT_page".
    function INSERT_apage($record) {
        $this->dbg("Inserting page record",4);
        try {
            $do_insert = $this->dB['Q']['INSERT']['page'];
            $r = $do_insert->execute($record);
        } catch(PDOException $ex) {
            $this->dbg("Error inserting page record",3);
            $this->cf['failed'] = 1;
            $this->cf['errmsg'] = $ex->getmessage();
            return false;
        }
        $this->dbg("    Insert seems OK", 5);
        return $r;
    }

    // Define 'generic' INSERT function
    // See the above INSERT_apage, which this *should* be able to replace.
    // A call of $foo->insert_page($record) will be converted within __call to
    // $foo->INSERT_generic($type='page',$record)
    private function INSERT_generic($type, $record) {
        $this->dbg("Inserting record of '$type' type",4);
        if (!isset($this->dB['Q']['INSERT'][$type])) {
            throw new Exception("Trying to insert record of type: $type. No such type defined");
        }
        try {
            $do_insert = $this->dB['Q']['INSERT'][$type];
            $r = $do_insert->execute($record);
        } catch(PDOException $ex) {
            $this->dbg("Error inserting $type record",3);
            $this->cf['failed'] = 1;
            $this->cf['errmsg'] = $ex->getmessage();
            return false;
        }
        $this->dbg("    Insert seems OK", 5);
        return $r;
    }

    private function FIND_generic( $type, $record) {
        $this->dbg("Find record of '$type' type", 4);
        if (!isset($this->dB['Q']['FIND'][$type])) {
            throw new Exception("Trying to find record of type $type. No such type defined");
        }
        try {
            $do_find = $this['Q']['FIND'][$type];
            $qry = $do_find->execute($record);
        } catch(PDOException $ex) {
            $this->dbg("Error setting up FIND for $type record", 3);
            $this->cf['failed'] = 1;
            $this->cf['errmsg'] = $ex->getmessage();
            return false;
        }
        $r = $qry->fetch(PDO::FETCH_ASSOC);
        $qry->closeCursor();
        return $r;
    }

    private function UPDATE_generic( $type, $record) {
        $this->dbg("Update record of '$type' type", 4);
        if (!isset($this->dB['Q']['UPDATE'][$type])) {
            throw new exception("Trying to update record of type $type. No such type defined");
        }
        try {
            $do_upd = $this['Q']['UPDATE'][$type];
            $r = $do_upd->execute($record);
        } catch(PROException $ex) {
            $this->dbg("Error updating record of $type type", 3);
            $this->cf['failed'] = 1;
            $this->cf['errmsg'] = $ex->getmessage();
            return false;
        }
        return $r;
    }

    private function DELETE_generic( $type, $record) {
    }

    /*
      'Catching' Caller.
      This magicword function is to catch calls to nonexistent methods which match
      up to prepared queries, and call the relevant 'generic' function.
    */
    function __call( $f_name, $f_arguments ) {
        $this->dbg("Catchall __call",4);

         // Assuming 'generic' function before underscore, or error
        $f_parts = explode( '_', $f_name, 2);
        if (sizeof($f_parts) !== 2 ) {
            $this->dbg("Error calling method. No such method as $f_name",2);
            throw new Exception("Trying to call undefined method: ".$f_name);
        }
        $act    = strtoupper($f_parts[0]);  // capitalise the generic
        $act_on = $f_parts[1];              // pull rhe sub-method/function

        // We start by assuming the relevant generic will be defined, and we
        // got an array as a single argument. If additional arguments were
        // passed, they're not carried-thru.
        if (isset($this->dB['Q'][$act][$act_on])) {
            // Build something to use like:  $this->INSERT_generic('page', $record);
            // Where the code would've been: $foo->insert_page($record);
            $funct = $act.'_generic';
            return $this->{$funct}($act_on, $f_arguments[0]);
        } else {
            if (isset($this->dB['Q'][$act])) {
                $this->dbg("Error, calling method group '$act'",3);
            }
            $this->dbg("Error calling method. No such method as $f_name",2);
            throw new Exception("Trying to call undefined method: ".$f_name);
        }


    }

    // Connect to the database.
    // This relies upon the parameters (4, any unused defined as null) being
    // loaded into the ['conn'] element of $dB as an array of values.
    private function connectdb() {
        $this->dbg("Making connection to the database",4);
        $cPDO = $this->dB['conn'];
        $myPDO = false;
        try {
            $myPDO = new PDO( $cPDO[0], $cPDO[1], $cPDO[2], $cPDO[3] );
        } catch(PDOException $ex) {
            $this->dbg("Error connecting to database",1);
            $this->cf['failed'] = 1;
            $this->cf['errmsg'] = "Message:'".$ex->getMessage()."' Code:'".$ex->code()."'";
            exit($ex);
        }
        return $myPDO;
    }

    function __destruct () {
        $this->dbg("Destructing mediawatch instance",5);
        $this->dbg("    Version:".$this->cf['vers'],5);
        unset($this->dB['Q']);
        unset($this->dB['PDO']);
        unset($this->dB);
        unset($this->cf);
    }

    // Simple 'kludge' for debugging during development.
    // Replace with a debug class and trace array at some future point.
    function dbg( $str, $lev ) {
    if ($this->cf['debug'])
        if ($this->cf['debug'] >= $lev )
        echo date('c').'  ['.substr($this->cf['lvl'],$lev,1)."] ".$str."\r\n";
    }
}
?>
