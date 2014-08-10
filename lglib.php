<?php
/**
*   Table names and other global configuration values.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012-2014 Lee Garner <lee@leegarner.com>
*   @package    lglib
*   @version    0.0.6
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

// Static configuration items
global $_LGLIB_CONF;
$_LGLIB_CONF['pi_version'] = '0.0.6';
$_LGLIB_CONF['pi_name'] = 'lglib';
$_LGLIB_CONF['gl_version'] = '1.4.0';
$_LGLIB_CONF['pi_url'] = 'http://www.leegarner.com';
$_LGLIB_CONF['pi_display_name'] = 'lgLib';

global $_TABLES, $_DB_table_prefix;
$_DB_prefix = $_DB_table_prefix . 'lglib_';
$_TABLES['lglib_messages'] = $_DB_prefix . 'messages';

?>
