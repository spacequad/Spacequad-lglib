<?php
/**
*   Provides automatic installation of the lgLib plugin.
*   There is nothing to do except create the plugin record
*   since there are no tables or user interfaces.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012 Lee Garner <lee@leegarner.com>
*   @package    lglib
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** @global string $_DB_dbms */
global $_DB_dbms;

require_once dirname(__FILE__) . '/functions.inc';
require_once dirname(__FILE__) . '/lglib.php';
require_once LGLIB_PI_PATH . "/sql/{$_DB_dbms}_install.php";

//  Plugin installation options
$INSTALL_plugin[$_LGLIB_CONF['pi_name']] = array(
    'installer' => array('type' => 'installer', 
            'version' => '1', 
            'mode' => 'install'),

    'plugin' => array('type' => 'plugin', 
            'name'      => $_LGLIB_CONF['pi_name'],
            'ver'       => $_LGLIB_CONF['pi_version'], 
            'gl_ver'    => $_LGLIB_CONF['gl_version'],
            'url'       => $_LGLIB_CONF['pi_url'], 
            'display'   => $_LGLIB_CONF['pi_display_name']
    ),
    array('type'    => 'mkdir',
        'dirs'      => array($_CONF['path'] . 'data/' .
                            $_LGLIB_CONF['pi_name'],
                        $_CONF['path'] . 'data/' .
                            $_LGLIB_CONF['pi_name'] . '/cache',
                    ),
    ),
        
    array('type' => 'table', 
            'table'     => $_TABLES['lglib_messages'], 
            'sql'       => $_SQL['lglib_messages']),

);
    
 
/**
*   Puts the datastructures for this plugin into the glFusion database
*   Note: Corresponding uninstall routine is in functions.inc
*
*   @return boolean     True if successful False otherwise
*/
function plugin_install_lglib()
{
    global $INSTALL_plugin, $_LGLIB_CONF;

    COM_errorLog("Attempting to install the {$_LGLIB_CONF['pi_name']} plugin", 1);
    $ret = INSTALLER_install($INSTALL_plugin[$_LGLIB_CONF['pi_name']]);
    if ($ret > 0) {
        return false;
    } else {
        return true;
    }
}


/**
*   Automatic removal function.
*
*   @return array       Array of items to be removed.
*/
function plugin_autouninstall_lglib()
{
    global $_LGLIB_CONF;

    $out = array (
        'tables'    => array('lglib_messages'),
        'groups'    => array(),
        'features'  => array(),
        'php_blocks' => array(),
        'vars'      => array(
            $_CONF_SUBSCR['pi_name'] . '_db_backup_exclude',
            $_CONF_SUBSCR['pi_name'] . '_db_backup_sendto',
            $_CONF_SUBSCR['pi_name'] . '_db_backup_maxfiles',
            $_CONF_SUBSCR['pi_name'] . '_db_backup_interval',
            $_CONF_SUBSCR['pi_name'] . '_db_backup_gzip',
            $_CONF_SUBSCR['pi_name'] . '_db_backup_lastrun',
        ),
    );

    return $out;
}


/**
*   Loads the configuration records for the Online Config Manager.
*
*   @return boolean     True = proceed, False = an error occured
*/
function plugin_load_configuration_lglib()
{
    require_once dirname(__FILE__) . '/install_defaults.php';
    return plugin_initconfig_lglib();
}

?>
