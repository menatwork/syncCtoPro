<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2014 
 * @package    syncCtoPro
 * @license    EULA
 * @filesource
 */

$GLOBALS['TL_DCA']['tl_syncCto_clients_syncTo']['palettes']['__selector__'][] = 'database_check';

// Check if we alread have a value in the subpallettes.
$currentSubPalettes = $GLOBALS['TL_DCA']['tl_syncCto_clients_syncTo']['subpalettes']['database_check'];
if(empty($currentSubPalettes)){
    $GLOBALS['TL_DCA']['tl_syncCto_clients_syncTo']['subpalettes']['database_check']  = 'database_pages_check'; 
} else {
    $GLOBALS['TL_DCA']['tl_syncCto_clients_syncTo']['subpalettes']['database_check']  = sprintf("%s,database_pages_check", $currentSubPalettes); 
}

$GLOBALS['TL_DCA']['tl_syncCto_clients_syncTo']['fields']['database_check']['eval']['submitOnChange'] = 'true';

$GLOBALS['TL_DCA']['tl_syncCto_clients_syncTo']['fields']['database_pages_check'] = array
(
    'label'          => &$GLOBALS['TL_LANG']['tl_syncCto_clients_syncTo']['database_pages_check'],
    'inputType'      => 'checkbox',
    'exclude'        => true,
);