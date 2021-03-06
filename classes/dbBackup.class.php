<?php
//  $Id$
/**
*   glFusion Database Backup Class.
*   Based on the Wordpress wp-db-backup plugin by Austin Matzko
*   http://www.ilfilosofo.com/
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2010-2014 Lee Garner <lee@leegarner.com>
*   @package    lglib
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/


/**
*   Class for backup
*   @package lglib
*/
class dbBackup
{
    /** Backup filename, resulting from backup fucntion.  May be false.
    *   @var mixed */
    private $backup_file = '';

    /** Backup filename.  Static, created from configured values.
    *   @var string */
    private $backup_filename;

    /** Flag to indicate whether GZip is to be used.
    *   @var boolean */
    private $gzip = false;

    /** Flag to indicate if the backup is running from cron.
    *   @var boolean */
    private $fromCron = false;

    /** File pointer to backup file
    *   @var mixed */
    private $fp;

    private $tablenames;
    private $exclusions;


    /**
    *   Constructor.
    *   Normally just instantiated.  If it's used from cron, then call
    *   $DB = new dbBackup(true);
    */
    public function __construct($fromCron = false)
    {
        global $_VARS, $_CONF, $_TABLES;

        $this->setGZip(true);
        $this->fromCron = $fromCron ? true : false;
        $this->backup_dir = $_CONF['backup_path'];

        // Create the backup filename
        $table_prefix = empty($_VARS['lglib_dbback_prefix']) ?
            'glfusion_db_backup_' : $_VARS['lglib_dbback_prefix'] . '_';
        $datum = date("Y_m_d_H_i_s");
        $this->backup_filename = $table_prefix . $datum . '.sql';
        if ($this->gzip) $this->backup_filename .= '.gz';

        // Get all tables in the database
        $mysql_tables = array();
        $res = DB_query('SHOW TABLES');
        while ($A = DB_fetchArray($res)) {
            $mysql_tables[] = $A[0];
        }
        // Get only tables that exist and are listed in $_TABLES
        $this->tablenames = array_intersect($mysql_tables, $_TABLES);

        // Get exclusions and remove from backup list
        $this->exclusions = @unserialize($_VARS['lglib_dbback_exclude']);
        if (!is_array($this->exclusions))
            $this->exclusions = array($this->exclusions);
        $this->tablenames = array_diff($this->tablenames, $this->exclusions);

        // If we're running a backup from the web interface, run it now.
        if (isset($_GET['dobackup']) && $_GET['dobackup'] == 'backup') {
            $this->perform_backup();
        }

    }   // dbBackup()


    /**
    *   Execute a database backup
    *   Intended to be called from the constructor when requested via url
    *
    *   @return boolean True on success, False on failure
    */
    public function perform_backup()
    {
        $this->backup_file = $this->backupDB();
        if (false !== $this->backup_file) {
            $this->Purge();
            return true;
        } else {
            return false;
        }
    }


    /**
    *   Save the time of this backup, if running from cron
    */
    private function save_backup_time()
    {
        global $_TABLES;

        $backup_time = time();
        DB_query("INSERT INTO {$_TABLES['vars']}
                (name, value) VALUES ('db_backup_lastrun', '$backup_time')
                ON DUPLICATE KEY 
                    UPDATE value='$backup_time'");
    }


    /**
     * Add backquotes to tables and db-names in
     * SQL queries. Taken from phpMyAdmin.
     */
    private function backquote($a_name)
    {
        if (!empty($a_name) && $a_name != '*') {
            if (is_array($a_name)) {
                $result = array();
                reset($a_name);
                while(list($key, $val) = each($a_name)) 
                    $result[$key] = '`' . $val . '`';
                return $result;
            } else {
                return '`' . $a_name . '`';
            }
        } else {
            return $a_name;
        }
    } 


    /**
    *   Open the backup file for writing, using gzip if appropriate
    *
    *   @param  string  $filename   Fully-qualified filename to open
    *   @param  string  $mode       File mode, default = write
    */
    private function open($filename, $mode='w')
    {
        if ($this->gzip) 
            $this->fp = @gzopen($filename, $mode);
        else
            $this->fp = @fopen($filename, $mode);
    }


    /**
    *   Close the backup file
    */
    private function close()
    {
        if ($this->gzip)
            gzclose($this->fp);
        else
            fclose($this->fp);
    }


    /**
     * Write to the backup file
     * @param string $query_line the line to write
     * @return null
     */
    private function stow($query_line)
    {
        if ($this->gzip) {
            if (!@gzwrite($this->fp, $query_line))
                $this->error('There was an error writing a line to the backup script:' . '  ' . $query_line);
        } else {
            if (false === @fwrite($this->fp, $query_line))
                $this->error('There was an error writing a line to the backup script:' . '  ' . $query_line);
        }
    }

    
    /**
    *   Logs any error messages
    *   @param array $args
    *   @return bool
    */
    private function error($msg)
    {
        $backtrace = debug_backtrace();
        $method = $backtrace[1]['class'].'::'.$backtrace[1]['function'];
        COM_errorLog($method . ' - ' . $msg);
    }


    /**
     * Taken partially from phpMyAdmin and partially from
     * Alain Wolf, Zurich - Switzerland
     * Website: http://restkultur.ch/personal/wolf/scripts/db_backup/
    
     * Modified by Scott Merrill (http://www.skippy.net/) 
     * to use the WordPress $wpdb object
     * @param string $table
     * @return void
     */
    private function backupTable($table, $structonly=false)
    {
        global $_TABLES;

        // Save the backquoted table name, gets used a lot
        $db_tablename = $this->backquote($table);

        // Get the table structure
        $res = DB_query("DESCRIBE $table");
        $table_structure = array();
        while ($A =DB_fetchArray($res, false)) {
            $table_structure[] = $A;
        }
        if (empty($table_structure)) {
            $this->error('Error getting table details: ' . $table);
            return false;
        }
    
        // Add SQL statement to drop existing table
        $this->stow("\n\n");
        $this->stow("#\n");
        $this->stow("# Delete any existing table $db_tablename\n");
        $this->stow("#\n");
        $this->stow("\n");
        $this->stow("DROP TABLE IF EXISTS $db_tablename;\n");
            
        // Table structure
        // Comment in SQL-file
        $this->stow("\n\n");
        $this->stow("#\n");
        $this->stow("# Table structure of table $db_tablename\n");
        $this->stow("#\n");
        $this->stow("\n");

        $res = DB_query("SHOW CREATE TABLE $table");
        if (!$res) {
            $err_msg = 'Error with SHOW CREATE TABLE for ' . $table;
            $this->error($err_msg);
            $this->stow("#\n# $err_msg\n#\n");
        }
        $create_table = DB_fetchArray($res);
        $this->stow($create_table[1] . ' ;');

        // If only backing up the structure, return now
        if ($structonly) return;

        // Comment in SQL-file
        $this->stow("\n");
        $this->stow("#\n");
        $this->stow("# Data contents of table $db_tablename\n");
        $this->stow("#\n");
        
        $defs = array();
        $ints = array();
        foreach ($table_structure as $struct) {
            if ( (0 === strpos($struct->Type, 'tinyint')) ||
                    (0 === strpos(strtolower($struct->Type), 'smallint')) ||
                    (0 === strpos(strtolower($struct->Type), 'mediumint')) ||
                    (0 === strpos(strtolower($struct->Type), 'int')) ||
                    (0 === strpos(strtolower($struct->Type), 'bigint')) ) {
                $defs[strtolower($struct->Field)] = (null === $struct->Default)
                         ? 'NULL' : $struct->Default;
                $ints[strtolower($struct->Field)] = "1";
            }
        }
            
        if (!ini_get('safe_mode')) @set_time_limit(15*60);
        $sql = "SELECT * FROM $table";
        $res = DB_query($sql);
        $table_data = array();
        $insert = "INSERT INTO {$db_tablename} VALUES (";

        while ($A = DB_fetchArray($res, false)) {
            //    \x08\\x09, not required
            $search = array("\x00", "\x0a", "\x0d", "\x1a");
            $replace = array('\0', '\n', '\r', '\Z');
            $values = array();
            foreach ($A as $key => $value) {
                if ($ints[strtolower($key)]) {
                    // make sure there are no blank spots in the insert syntax,
                    // yet try to avoid quotation marks around integers
                    $value = (null === $value || '' === $value) ? 
                            $defs[strtolower($key)] : $value;
                    $values[] = ( '' === $value ) ? "''" : $value;
                } else {
                    $values[] = "'" . str_replace($search, $replace, DB_escapeString($value)) . "'";
                }
            }
            $this->stow(" \n" . $insert . implode(', ', $values) . ');');
        }
        //$this->stow(';');       // finish the table's insert

        // Create footer/closing comment in SQL-file
        $this->stow("\n");
        $this->stow("#\n");
        $this->stow("# End of data contents of table $db_tablename\n");
        $this->stow("# --------------------------------------------------------\n");
        $this->stow("\n");

    }   // end backupTable()


    /**
    *   Actually performs the backup.
    *   Creates the backup file, saves the file pointer, then cycles
    *   through all the tables and calls backupTable() for each.
    *
    *   @return mixed   False on failure, name of backup file on success.
    */
    private function backupDB()
    {
        global $_TABLES, $_CONF, $_DB_host, $_DB_name;

        if (is_writable($this->backup_dir)) {
            $this->open($this->backup_dir . $this->backup_filename);
            if(!$this->fp) {
                COM_errorLog('Could not open the backup file for writing! (' .
                    $this->backup_dir . $this->backup_file . ')');
                return false;
            }
        } else {
            $this->error('The backup directory is not writeable (' .
                $this->backup_dir . ')');
            return false;
        }

        //Begin new backup of MySql
        $this->stow("# glFusion MySQL database backup\n");
        $this->stow("#\n");
        $this->stow('# Generated: ' . date('l j. F Y H:i T') .  "\n");
        $this->stow("# Hostname: $_DB_host\n");
        $this->stow("# Database: $_DB_name\n");
        $this->stow("# --------------------------------------------------------\n");
        
        foreach ($this->tablenames as $key=>$table) {
            // Increase script execution time-limit to 15 min for every table.
            if (!ini_get('safe_mode')) @set_time_limit(15*60);
            // Create the SQL statements
            $this->stow("# --------------------------------------------------------\n");
            $this->stow("# Table: $table\n");
            $this->stow("# --------------------------------------------------------\n");
            $this->backupTable($table);
        }

        // Back up the structure of excluded tables, if so configured
        if ($_VARS['lglib_dbback_allstructs']) {
            // Create the SQL statements
            $this->stow("# --------------------------------------------------------\n");
            $this->stow("# Table: $table\n");
            $this->stow("# --------------------------------------------------------\n");
            $this->backupTable($table, true);   // backup structure only
        }
            
        $this->stow("#\n");
        $this->stow("# Database Backup Finished.\n");
        $this->stow("#\n");
        $this->close();
        
        if (count($this->errors)) {
            return false;
        } else {
            return $this->backup_filename;
        }
        
    }   // backupDB()



    /**
    *   Send an email with attachments.
    *   This is a verbatim copy of COM_mail(), but with the $attachments
    *   paramater added and 3 extra lines of code near the end.
    *
    *   @param  string  $to         Receiver's email address
    *   @param  string  $from       Sender's email address
    *   @param  string  $subject    Message Subject
    *   @param  string  $message    Message Body
    *   @param  boolean $html       True for HTML message, False for Text
    *   @param  integer $priority   Message priority value
    *   @param  string  $cc         Other recipients
    *   @param  string  $altBody    Alt. body (text)
    *   @param  array   $attachments    Array of attachments
    *   @return boolean             True on success, False on Failure
    */
    private function SendMail(
        $to, 
        $subject, 
        $message, 
        $from = '', 
        $html = false, 
        $priority = 0, 
        $cc = '', 
        $altBody = '',
        $attachments = array()
    ) {
        global $_CONF;

        $subject = substr($subject, 0, strcspn($subject, "\r\n"));
        $subject = COM_emailEscape($subject);

        require_once $_CONF['path'] . 'lib/phpmailer/class.phpmailer.php';

        $mail = new PHPMailer();
        $mail->SetLanguage('en',$_CONF['path'].'lib/phpmailer/language/');
        $mail->CharSet = COM_getCharset();
        if ($_CONF['mail_backend'] == 'smtp') {
            $mail->IsSMTP();
            $mail->Host     = $_CONF['mail_smtp_host'];
            $mail->Port     = $_CONF['mail_smtp_port'];
            if ($_CONF['mail_smtp_secure'] != 'none') {
                $mail->SMTPSecure = $_CONF['mail_smtp_secure'];
            }
            if ($_CONF['mail_smtp_auth']) {
                $mail->SMTPAuth   = true;
                $mail->Username = $_CONF['mail_smtp_username'];
                $mail->Password = $_CONF['mail_smtp_password'];
            }
            $mail->Mailer = "smtp";

        } elseif ($_CONF['mail_backend'] == 'sendmail') {
            $mail->Mailer = "sendmail";
            $mail->Sendmail = $_CONF['mail_sendmail_path'];
        } else {
            $mail->Mailer = "mail";
        }
        $mail->WordWrap = 76;
        $mail->IsHTML($html);
        if ($html) {
            $mail->Body = COM_filterHTML($message);
        } else {
            $mail->Body = $message;
        }

        if ($altBody != '') {
            $mail->AltBody = $altBody;
        }

        $mail->Subject = $subject;

        if (is_array($from) && isset($from[0]) && $from[0] != '') {
            if ($_CONF['use_from_site_mail'] == 1) {
                $mail->From = $_CONF['site_mail'];
                $mail->AddReplyTo($from[0]);
            } else {
                $mail->From = $from[0];
            }
        } else {
            $mail->From = $_CONF['site_mail'];
        }

        if (is_array($from) && isset($from[1]) && $from[1] != '') {
            $mail->FromName = $from[1];
        } else {
            $mail->FromName = $_CONF['site_name'];
        }
        if (is_array($to) && isset($to[0]) && $to[0] != '') {
            if (isset($to[1]) && $to[1] != '') {
                $mail->AddAddress($to[0],$to[1]);
            } else {
                $mail->AddAddress($to[0]);
            }
        } else {
            // assume old style....
            $mail->AddAddress($to);
        }

        if (isset($cc[0]) && $cc[0] != '') {
            if (isset($cc[1]) && $cc[1] != '') {
                $mail->AddCC($cc[0],$cc[1]);
            } else {
                $mail->AddCC($cc[0]);
            }
        } else {
            // assume old style....
            if (isset($cc) && $cc != '') {
                $mail->AddCC($cc);
            }
        }

        if ($priority) {
            $mail->Priority = 1;
        }

        // Add attachments
        foreach($attachments as $key => $value) { 
            $mail->AddAttachment($value);
        }

        if(!$mail->Send()) {
            COM_errorLog("Email Error: " . $mail->ErrorInfo);
            return false;
        }
        return true;
    }


    /**
    *   Deliver a backup file.
    *   Originally had the option of "http" or "smtp", but for glFusion
    *   only "smtp" is needed.  Files can be downloaded at any time via
    *   the backup admin interface.
    */
    private function deliver_backup($filename = '')
    {
        global $_VARS, $_CONF;

        if ($filename == '' || !filename) return false;
        $diskfile = $this->backup_dir . $filename;
        $recipient = $_VARS['lglib_dbback_sendto'];
        if (!file_exists($diskfile)) {
            COM_errorLog("dbBackup: File $diskfile does not exist");
            return false;
        }
        if (!COM_isEmail($recipient)) {
            COM_errorLog("$recipient is not a valid email address");
            return false;
        }
        $message = sprintf("Attached to this email is\n   %s\n   Size:%s kilobytes\n", $filename, round(filesize($diskfile)/1024));
        $status = $this->SendMail(
                $recipient,
                $_CONF['site_name'] . ' ' . 'Database Backup', 
                $message, '', false, 0, '', '', 
                array($diskfile) );
        return $status;
    }
    

    /**
    *   Run a backup from cron.
    *   E-mails the backup file to the configured address, if any.
    *
    *   @return boolean Status from deliver_backup, or false on backup failure.
    */
    private function cron_backup()
    {
        $backup_file = $this->backupDB();
        $this->save_backup_time();
        if (false !== $backup_file) { 
            $this->Purge();     // Remove old files
            return $this->deliver_backup($backup_file);
        } else {
            return false;
        }
    }


    /**
    *   Determine whether gzip is available, and configured.
    *   Sets $this->gzip to indicate whether gzip should be used.
    *
    *   @param  boolean $val    False to disable, True to enable if available.
    */
    function setGZip($val=true)
    {
        global $_VARS;

        switch ($val) {
        case false:
            $this->gzip = false;
            break;
        case true:
            if ($_VARS['lglib_dbback_gzip']) {
                $this->gzip = function_exists('gzopen') ? true : false;
            } else {
                $this->gzip = false;
            }
            break;
        }
    }
    

    /**
    *   Purge old files.
    *   Removes older files, keeping the requested number.
    *
    *   @param  integer $files  Number to keep, 0 to use configured value.
    */
    public function Purge($files = 0)
    {
        global $_VARS;

        if ($files == 0) {
            $files = (int)$_VARS['lglib_dbback_files'];
        }
        if ($files == 0) return;

        $backups = array();
        $fd = opendir($this->backup_dir);
        $index = 0;
        while ((false !== ($file = @readdir($fd)))) {
            if ($file <> '.' && $file <> '..' && $file <> 'CVS' &&
                    preg_match('/\.sql(\.gz)?$/i', $file)) {
                $index++;
                clearstatcache();
                $backups[] = $file;
            }
        }

        // Sort ascending by filename, which includes timestamp
        sort($backups);
        $count = count($backups);       // How many we have
        $topurge = $count - $files;     // How many to delete
        if ($topurge <= 0) return;
        for ($i = 0; $i < $topurge; $i++) {
            unlink($this->backup_dir . $backups[$i]);
        }
    }

}   // class dbBackup


?>
