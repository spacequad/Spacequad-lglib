<?php
/**
*   Table definitions for the lgLib plugin
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012 Lee Garner <lee@leegarner.com>
*   @package    lglib
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/** @global array $_TABLES */
global $_TABLES, $_SQL, $_UPGRADE_SQL;

$_SQL['lglib_messages'] = "CREATE TABLE {$_TABLES['lglib_messages']} (
  `uid` int(11) NOT NULL DEFAULT '1',
  `sess_id` varchar(255) NOT NULL DEFAULT '',
  `title` varchar(255) DEFAULT NULL,
  `message` text NOT NULL,
  `pi_code` varchar(255) DEFAULT NULL,
  `persist` tinyint(1) unsigned default 0,
  `dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `expires` datetime
)";

$_UPGRADE_SQL['0.0.2'] = array(
    "CREATE TABLE {$_TABLES['lglib_messages']} (
      `uid` int(11) NOT NULL DEFAULT '1',
      `sess_id` varchar(255) NOT NULL DEFAULT '',
      `title` varchar(255) DEFAULT NULL,
      `message` text NOT NULL,
      `pi_code` varchar(255) DEFAULT NULL,
      `persist` tinyint(1) unsigned default 0,
      `dt` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      `expires` datetime
    )",
);

?>
