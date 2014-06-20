<?php
/**
*   Upgrade routines for the lgLib plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2013 Lee Garner <lee@leegarner.com>
*   @package    lglib
*   @version    0.0.2
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

// Required to get the config values
global $_CONF, $_LGLIB_CONF, $_DB_dbms;

/** Include the default configuration values */
require_once LGLIB_PI_PATH . '/install_defaults.php';

/** Include the table creation strings */
require_once LGLIB_PI_PATH . "/sql/{$_DB_dbms}_install.php";

/**
*   Perform the upgrade starting at the current version.
*
*   @param  string  $current_ver    Current installed version to be upgraded
*   @return integer                 Error code, 0 for success
*/
function LGLIB_do_upgrade($current_ver)
{
    global $_LGLIB_DEFAULTS, $_LGLIB_CONF;

    $error = 0;

    if ($current_ver < '0.0.2') {
        // upgrade from 0.0.1 to 0.0.2
        COM_errorLog("Updating Plugin to 0.0.2");
        $error = LGLIB_do_upgrade_sql('0.0.2');
        if ($error)
            return $error;
    }

    if ($current_ver < '0.0.4') {
        // upgrade from 0.0.3 to 0.0.4
        COM_errorLog("Updating Plugin to 0.0.4");
        $c = config::get_instance();
        $c->add('img_disp_relpath', $_LGLIB_DEFAULTS['img_disp_relpath'],
                'text', 0, 0, 15, 20, true, $_LGLIB_CONF['pi_name']);
    }

    if ($current_ver < '0.0.5') {
        // upgrade from 0.0.4 to 0.0.5
        COM_errorLog("Updating Plugin to 0.0.5");
        $c = config::get_instance();
        $c->add('cron_schedule_interval', $_LGLIB_DEFAULTS['cron_schedule_interval'],
                'text', 0, 0, 15, 30, true, $_LGLIB_CONF['pi_name']);
        $c->add('cron_key', $_LGLIB_DEFAULTS['cron_key'],
                'text', 0, 0, 15, 40, true, $_LGLIB_CONF['pi_name']);
    }

    return $error;
}


/**
*   Actually perform any sql updates.
*
*   @param  string  $version    Version being upgraded TO
*   @param  array   $sql        Array of SQL statement(s) to execute
*/
function LGLIB_do_upgrade_sql($version)
{
    global $_TABLES, $_LGLIB_CONF, $_UPGRADE_SQL;


    // If no sql statements passed in, return success
    if (!isset($_UPGRADE_SQL[$version]) ||
            !is_array($_UPGRADE_SQL[$version]))
        return 0;

    // Execute SQL now to perform the upgrade
    COM_errorLOG("--Updating lgLib to version $version");
    foreach ($_UPGRADE_SQL[$version] as $q) {
        COM_errorLOG("lgLib Plugin $version update: Executing SQL => $q");
        DB_query($q, '1');
        if (DB_error()) {
            COM_errorLog("SQL Error during lgLib plugin update: $q",1);
            return 1;
            break;
        }
    }
    return 0;
}

?>
