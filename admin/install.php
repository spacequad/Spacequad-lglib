<?php
/**
*  Installation routine for the lgLib plugin for GLFusion
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012 Lee Garner <lee@leegarner.com>
*   @package    lglib
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/**
 *  Include required glFusion common functions
 */
require_once '../../../lib-common.php';

// Only let Root users access this page
if (!SEC_inGroup('Root')) {
    // Someone is trying to illegally access this page
    COM_errorLog("Someone has tried to illegally access the lglib install/uninstall page.  User id: {$_USER['uid']}, Username: {$_USER['username']}, IP: $REMOTE_ADDR",1);
    $display = COM_siteHeader();
    $display .= COM_startBlock($LANG_PHOTO['access_denied']);
    $display .= $LANG_PHOTO['access_denied_msg'];
    $display .= COM_endBlock();
    $display .= COM_siteFooter(true);
    echo $display;
    exit;
}

/**
*  Include required plugin common functions
*/
$base_path  = "{$_CONF['path']}plugins/lglib";
require_once "$base_path/autoinstall.php";
require_once "$base_path/functions.inc";
USES_lib_install();

/* 
* Main Function
*/
if (SEC_checkToken()) {
    if ($_GET['action'] == 'install') {
        if (plugin_install_lglib()) {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=44');
            exit;
        } else {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=72');
            exit;
        }
    } else if ($_GET['action'] == "uninstall") {
        USES_lib_plugin();
        if (PLG_uninstall('lglib')) {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=45');
            exit;
        } else {
            echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php?msg=73');
            exit;
        }
    }
}

echo COM_refresh($_CONF['site_admin_url'] . '/plugins.php');

?>
