<?php
//  $Id: english.php 42 2012-05-23 15:05:36Z root $
/**
*   Default English Language file for the lgLib plugin.
*
*   @author     Lee Garner <lee@leegarner.com>
*   @copyright  Copyright (c) 2012 Lee Garner
*   @package    lglib
*   @version    0.0.1
*   @license    http://opensource.org/licenses/gpl-2.0.php 
*               GNU Public License v2 or later
*   @filesource
*/

/**
*   The plugin's lang array
*   @global array $LANG_LGLIB
*/
$LANG_LGLIB = array(
    'list_backups' => 'List Backups',
    'instr_db_bkup_config' => 'Only database tables which exist and are actually used by glFusion are displayed.  To remove tables from the backup, move them into the right pane.',
    'system_message'    => 'System Message',
    'nameparser_suffixes' => array(
        'I', 'II', 'III', 'IV', 'V',
        'Senior', 'Junior', 'Jr', 'Sr',
        'PhD', 'APR', 'RPh', 'PE', 'MD', 'MA', 'DMD', 'CME', 'CPA',
    ),
    // compound elements in names like "Norman Van De Kamp"
    'nameparser_compound' => array(
        'vere', 'von', 'van', 'de', 'del', 'della', 'di', 'da',
        'pietro', 'vanden', 'du', 'st.', 'st', 'la', 'ter',
    ),
    // small words not to be converted by LGLIB_titleCase()
    'smallwords' => array(
        'of', 'a', 'the', 'and', 'an', 'or', 'nor', 'but', 'is', 'if', 'then',
        'else', 'when', 'at', 'from', 'by', 'on', 'off', 'for', 'in', 'out',
        'over', 'to', 'into', 'with',
    ),
    'menu_label' => 'lgLib',
);

// Messages for the plugin upgrade
$PLG_lglib_MESSAGE06 = 'Plugin upgrade not supported.';

// Localization of the Admin Configuration UI
$LANG_configsections['lglib'] = array(
    'label' => 'lgLib',
    'title' => 'Utility Plugin Configuration',
);

$LANG_confignames['lglib'] = array(
    'cal_style' => 'Calendar Style',
    'img_disp_relpath' => 'Path to display images',
    'cron_schedule_interval' => 'Scheduled Task Interval',
    'cron_key' => 'Scheduled Task Security Key',
);

$LANG_configsubgroups['lglib'] = array(
    'sg_main' => 'Main Settings',
);

$LANG_fs['lglib'] = array(
    'fs_main' => 'Main Settings',
);

// Note: entries 0, 1, and 12 are the same as in $LANG_configselects['Core']
$LANG_configselects['lglib'] = array(
    3 => array('Yes' => 1, 'No' => 0),
    14 => array('Blue' => 'blue', 'Blue2' => 'blue2', 'Brown' => 'brown',
            'Green' => 'green', 'System' => 'system', 'TAS' => 'tas', 
            'Win2k-1' => 'win2k-1', 'Win2k-2' => 'win2k-2',
            'Win2k-Cold-1' => 'win2k-cold-1', 'Win2k-Cold-2' => 'win2k-cold-2',
        ),
    15 => array('Database' => 'db', 'Session Vars' => 'session'),
);

?>
