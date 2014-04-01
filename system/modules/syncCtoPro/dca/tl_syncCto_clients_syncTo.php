<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2013
 * @package    syncCto Pro
 * @license    EULA
 * @filesource
 */

$GLOBALS['TL_DCA']['tl_syncCto_clients_syncTo']['palettes']['__selector__'][] = 'database_check';

$GLOBALS['TL_DCA']['tl_syncCto_clients_syncTo']['subpalettes']['database_check'] = 'database_pages_check';

$GLOBALS['TL_DCA']['tl_syncCto_clients_syncTo']['fields']['database_check']['eval']['submitOnChange'] = 'true';

$GLOBALS['TL_DCA']['tl_syncCto_clients_syncTo']['fields']['database_pages_check'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_syncCto_clients_syncTo']['database_pages_check'],
    'inputType' => 'checkbox',
    'exclude'   => true,
);