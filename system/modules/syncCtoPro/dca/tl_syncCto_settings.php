<?php 

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2013 
 * @package    syncCto Pro
 * @license    EULA
 * @filesource
 */

$GLOBALS['TL_DCA']['tl_syncCto_settings']['palettes']['default'] = preg_replace(
        "/syncCto_hidden_tables/i", "syncCto_hidden_tables;{diff_legend:hide},syncCto_diff_blacklist,syncCto_sync_blacklist", $GLOBALS['TL_DCA']['tl_syncCto_settings']['palettes']['default']
);

$GLOBALS['TL_DCA']['tl_syncCto_settings']['fields']['syncCto_diff_blacklist'] = array(
    'label' => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['diff_blacklist'],
    'exclude' => true,
    'inputType' => 'multiColumnWizard',
    'eval' => array(
        'columnFields' => array(
            'table' => array(
                'label' => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['blacklist_table'],
                'exclude' => true,
                'options' => array('tl_page', 'tl_article', 'tl_content'),
                'inputType' => 'select',                       
                'eval' => array('includeBlankOption' => 'true', 'chosen' => 'true', 'style' => 'width:235px'),
            ),
            'entry' => array(
                'label' => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['blacklist_entry'],
                'exclude' => true,
                'inputType' => 'text',                        
                'eval' => array('style' => 'width:352px'),
            )
        )
    )
    //'load_callback' => array(array('tl_syncCto_settings', 'loadDiffBlacklist')),
    //'save_callback' => array(array('tl_syncCto_settings', 'saveMcwEntries'))
);

$GLOBALS['TL_DCA']['tl_syncCto_settings']['fields']['syncCto_sync_blacklist'] = array(
    'label' => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['sync_blacklist'],
    'exclude' => true,
    'inputType' => 'multiColumnWizard',
    'eval' => array(
        'columnFields' => array(
            'table' => array(
                'label' => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['blacklist_table'],
                'options' => array('tl_page', 'tl_article', 'tl_content'),
                'inputType' => 'select',                      
                'eval' => array('includeBlankOption' => 'true', 'chosen' => 'true', 'style' => 'width:235px'),
            ),
            'entry' => array(
                'label' => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['blacklist_entry'],
                'exclude' => true,
                'inputType' => 'text',                        
                'eval' => array('style' => 'width:352px'),
            )
        )
    )
    //'load_callback' => array(array('tl_syncCto_settings', 'loadSyncBlacklist')),
    //'save_callback' => array(array('tl_syncCto_settings', 'saveMcwEntries'))
);

?>