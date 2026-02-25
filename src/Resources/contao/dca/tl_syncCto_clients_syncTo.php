<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2014
 * @package    syncCtoPro
 * @license    EULA
 * @filesource
 */

$GLOBALS['TL_DCA']['tl_syncCto_clients_syncTo']['fields']['database_pages_check'] = array
(
    'label'     => &$GLOBALS['TL_LANG']['tl_syncCto_clients_syncTo']['database_pages_check'],
    'inputType' => 'checkbox',
    'exclude'   => true
);

\Contao\CoreBundle\DataContainer\PaletteManipulator::create()
    ->addField('database_pages_check', 'database_check')
    ->applyToPalette('default', 'tl_syncCto_clients_syncTo');