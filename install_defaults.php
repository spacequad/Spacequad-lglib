<?php
/**
*   Configuration Defaults for the lgLib plugin for glFusion.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012 Lee Garner
*   @package    lglib
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

// This file can't be used on its own
if (!defined ('GVERSION')) {
    die ('This file can not be used on its own.');
}

/** Utility plugin configuration data
*   @global array */
global $_LGLIB_CONF;
if (!isset($_LGLIB_CONF) || empty($_LGLIB_CONF)) {
    $_LGLIB_CONF = array();
    require_once dirname(__FILE__) . '/lglib.php';
}

/** Utility plugin default configurations
*   @global array */
global $_LGLIB_DEFAULTS;
$_LGLIB_DEFAULTS = array(
    'cal_style' => 'blue',
    // Relative path to display images. Used to construct both the absolute
    // path under $_CONF['path_html']/lglib and URL as
    // $_CONF['site_url']/lglib/...
    'img_disp_relpath' => 'data/imgcache',
    'cron_schedule_interval' => 0,
    'cron_key' => md5(time() . rand()),
);

/**
*   Initialize lgLib plugin configuration
*
*   @return boolean             true: success; false: an error occurred
*/
function plugin_initconfig_lglib()
{
    global $_CONF, $_LGLIB_CONF, $_LGLIB_DEFAULTS;

    $c = config::get_instance();

    if (!$c->group_exists($_LGLIB_CONF['pi_name'])) {

        $c->add('sg_main', NULL, 'subgroup', 0, 0, NULL, 0, true, 
                $_LGLIB_CONF['pi_name']);
        $c->add('fs_main', NULL, 'fieldset', 0, 0, NULL, 0, true, 
                $_LGLIB_CONF['pi_name']);

        $c->add('cal_style', $_LGLIB_DEFAULTS['cal_style'],
                'select', 0, 0, 14, 10, true, $_LGLIB_CONF['pi_name']);
        $c->add('img_disp_relpath', $_LGLIB_DEFAULTS['img_disp_relpath'],
                'text', 0, 0, 15, 20, true, $_LGLIB_CONF['pi_name']);
        $c->add('cron_schedule_interval', $_LGLIB_DEFAULTS['cron_schedule_interval'],
                'text', 0, 0, 15, 30, true, $_LGLIB_CONF['pi_name']);
        $c->add('cron_key', $_LGLIB_DEFAULTS['cron_key'],
                'text', 0, 0, 15, 40, true, $_LGLIB_CONF['pi_name']);
     }

     return true;
}

?>
