<?php
//  $Id: polish_utf-8.php 34 2012-12-15 21:44:19Z root $
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
    'system_message'    => 'System Message',
    'nameparser_suffixes' => array(
        'I', 'II', 'III', 'IV', 'V',
        'Senior', 'Junior', 'Jr', 'Sr',
        'PhD', 'APR', 'RPh', 'PE', 'MD', 'MA', 'DMD', 'CME', 'CPA',
    ),
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
    'cal_style' => 'Styl Kalendarza',
    'img_disp_relpath' => 'Path to display images',
    'cron_schedule_interval' => 'Scheduled Task Interval',
    'cron_key' => 'Scheduled Task Security Key',
);

$LANG_configsubgroups['lglib'] = array(
    'sg_main' => 'Ustawienia',
);

$LANG_fs['lglib'] = array(
    'fs_main' => 'Ustawienia',
);

// Note: entries 0, 1, and 12 are the same as in $LANG_configselects['Core']
$LANG_configselects['lglib'] = array(
    3 => array('Tak' => 1, 'Nie' => 0),
    14 => array('Niebieski' => 'blue', 'Niebieski2' => 'blue2', 'Brązowy' => 'brown',
            'Zielony' => 'green', 'Systemowy' => 'system', 'TAS' => 'tas', 
            'Win2k-1' => 'win2k-1', 'Win2k-2' => 'win2k-2',
            'Win2k-Cold-1' => 'win2k-cold-1', 'Win2k-Cold-2' => 'win2k-cold-2',
        ),
    15 => array('Database' => 'db', 'Session Vars' => 'session'),
);

?>
