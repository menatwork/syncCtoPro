<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2014 
 * @package    syncCtoPro
 * @license    EULA
 * @filesource
 */

$GLOBALS['TL_DCA']['tl_syncCto_settings']['palettes']['default'] .= ';{diff_legend:hide},syncCto_diff_blacklist,syncCto_sync_blacklist;{trigger_legend:hide},syncCto_trigger_refresh,syncCto_trigger_delete';


$GLOBALS['TL_DCA']['tl_syncCto_settings']['fields']['syncCto_diff_blacklist'] = array
(
    'label'         => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['diff_blacklist'],
    'exclude'       => true,
    'inputType'     => 'multiColumnWizard',
    'eval'          => array
    (
        'alwaysSave'   => true,
        'columnFields' => array
        (
            'table' => array
            (
                'label'     => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['blacklist_table'],
                'reference' => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['tables'],
                'exclude'   => true,
                'options'   => array('all', 'tl_page', 'tl_article', 'tl_content'),
                'inputType' => 'select',
                'eval'      => array
                (
                    'includeBlankOption' => true,
                    'chosen'             => true,
                    'style'              => 'width:235px'
                ),
            ),
            'entry' => array
            (
                'label'     => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['blacklist_entry'],
                'exclude'   => true,
                'inputType' => 'text',
                'eval'      => array
                (
                    'style' => 'width:352px'
                ),
            )
        )
    ),
    'load_callback' => array(array('SyncCtoProTableSettings', 'loadDiffBlacklist')),
    'save_callback' => array(array('SyncCtoProTableSettings', 'saveDiffBlacklist'))
);

$GLOBALS['TL_DCA']['tl_syncCto_settings']['fields']['syncCto_sync_blacklist'] = array
(
    'label'         => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['sync_blacklist'],
    'exclude'       => true,
    'inputType'     => 'multiColumnWizard',
    'eval'          => array
    (
        'alwaysSave'   => true,
        'columnFields' => array
        (
            'table' => array
            (
                'label'     => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['blacklist_table'],
                'reference' => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['tables'],
                'options'   => array('all', 'tl_page', 'tl_article', 'tl_content'),
                'inputType' => 'select',
                'eval'      => array
                (
                    'includeBlankOption' => true,
                    'chosen'             => true,
                    'style'              => 'width:235px'
                ),
            ),
            'entry' => array
            (
                'label'     => &$GLOBALS['TL_LANG']['SyncCtoProTableSettings']['blacklist_entry'],
                'exclude'   => true,
                'inputType' => 'text',
                'eval'      => array
                (
                    'style' => 'width:352px'
                ),
            )
        )
    ),
    'load_callback' => array(array('SyncCtoProTableSettings', 'loadSyncBlacklist')),
    'save_callback' => array(array('SyncCtoProTableSettings', 'saveSyncBlacklist'))
);

$GLOBALS['TL_DCA']['tl_syncCto_settings']['fields']['syncCto_trigger_refresh'] = array
(
    'label'         => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['trigger_refresh'],
    'exclude'       => true,
    'inputType'     => 'checkbox',
    'eval'          => array
    (
        'alwaysSave' => true,
        'tl_class'   => 'w50'
    ),
    'save_callback' => array
    (
        array('SyncCtoProTableSettings', 'refreshTrigger')
    )
);

$GLOBALS['TL_DCA']['tl_syncCto_settings']['fields']['syncCto_trigger_delete'] = array
(
    'label'         => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['trigger_delete'],
    'exclude'       => true,
    'inputType'     => 'checkbox',
    'eval'          => array
    (
        'alwaysSave' => true,
        'tl_class'   => 'w50'
    ),
    'save_callback' => array
    (
        array('SyncCtoProTableSettings', 'deleteTrigger')
    )
);
