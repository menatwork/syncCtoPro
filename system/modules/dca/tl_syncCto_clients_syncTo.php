<?php if (!defined('TL_ROOT')) die('You cannot access this file directly!');

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2013 
 * @package    syncCto
 * @license    GNU/LGPL 
 * @filesource
 */
$GLOBALS['TL_DCA']['tl_syncCto_clients_syncTo']['palettes']['default'] = preg_replace(
        "/{table_legend},database_check/i", "{table_legend},database_check,database_pages_check", $GLOBALS['TL_DCA']['tl_syncCto_clients_syncTo']['palettes']['default']
);

$GLOBALS['TL_DCA']['tl_syncCto_clients_syncTo']['fields']['database_pages_check'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_syncCto_clients_syncTo']['database_pages_check'],
    'inputType' => 'checkbox',
    'exclude'   => true,
);
?>