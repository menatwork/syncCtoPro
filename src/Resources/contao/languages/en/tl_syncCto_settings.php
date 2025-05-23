<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2014
 * @package    syncCtoPro
 * @license    EULA
 * @filesource
 */

/**
 * Legends
 */
$GLOBALS['TL_LANG']['tl_syncCto_settings']['diff_legend']              = 'Diff settings';
$GLOBALS['TL_LANG']['tl_syncCto_settings']['trigger_legend']           = 'Trigger settings';

/**
 * Fields
 */
$GLOBALS['TL_LANG']['tl_syncCto_settings']['diff_blacklist']           = array('Hidden fields', 'Here you can select which fields should not be displayed in the diff.');
$GLOBALS['TL_LANG']['tl_syncCto_settings']['sync_blacklist']           = array('Excluded fields', 'Here you can select which fields should never be synchronized.');
$GLOBALS['TL_LANG']['tl_syncCto_settings']['blacklist_table']          = array('Database table', 'Here you can select which table should be used.');
$GLOBALS['TL_LANG']['tl_syncCto_settings']['blacklist_entry']          = array('Database column', 'Here you can enter the column that should not be used.');
$GLOBALS['TL_LANG']['tl_syncCto_settings']['trigger_refresh']          = array('Rebuild triggers and hashes', 'Here you can update the triggers and all hash values.');
$GLOBALS['TL_LANG']['tl_syncCto_settings']['trigger_delete']           = array('Delete triggers', 'Here you can remove all triggers.');

/**
 * References
 */
$GLOBALS['TL_LANG']['tl_syncCto_settings']['tables']['all']            = 'All';
$GLOBALS['TL_LANG']['tl_syncCto_settings']['tables']['tl_page']        = 'Page structure';
$GLOBALS['TL_LANG']['tl_syncCto_settings']['tables']['tl_article']     = 'Articles';
$GLOBALS['TL_LANG']['tl_syncCto_settings']['tables']['tl_content']     = 'Content elements';