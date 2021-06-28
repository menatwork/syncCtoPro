<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2014
 * @package    syncCto
 * @license    GNU/LGPL
 * @filesource
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

/**
 * Add fields and update palettes.
 */
PaletteManipulator::create()
    ->addField('syncCto_force_diff', 'syncCto_legend', PaletteManipulator::POSITION_APPEND)
    ->addField('syncCto_pagemounts', 'syncCto_tables_legend', PaletteManipulator::POSITION_PREPEND)
    ->applyToPalette('default', 'tl_user_group');


/**
 * Add fields to tl_user_group
 */
$GLOBALS['TL_DCA']['tl_user_group']['fields']['syncCto_force_diff'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_user_group']['syncCto_force_diff'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'sql'       => 'varchar(1)'
];

$GLOBALS['TL_DCA']['tl_user_group']['fields']['syncCto_pagemounts'] = [
    'label'     => &$GLOBALS['TL_LANG']['tl_user_group']['syncCto_pagemounts'],
    'exclude'   => true,
    'inputType' => 'pageTree',
    'eval'      => ['multiple' => true, 'fieldType' => 'checkbox'],
    'sql'       => 'blob NULL'
];
