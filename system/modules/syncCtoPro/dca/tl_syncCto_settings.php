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
        "/syncCto_hidden_tables/i", "syncCto_hidden_tables;{diff_legend:hide},syncCto_diff_blacklist,syncCto_sync_blacklist;{trigger_legend:hide},syncCto_trigger_refresh,syncCto_trigger_delete", $GLOBALS['TL_DCA']['tl_syncCto_settings']['palettes']['default']
);

$GLOBALS['TL_DCA']['tl_syncCto_settings']['fields']['syncCto_diff_blacklist'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['diff_blacklist'],
    'exclude'   => true,
    'inputType' => 'multiColumnWizard',
    'eval'      => array(
        'alwaysSave'   => true,
        'columnFields' => array(
            'table' => array(
                'label'     => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['blacklist_table'],
                'reference' => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['tables'],
                'exclude'   => true,
                'options'   => array('all', 'tl_page', 'tl_article', 'tl_content'),
                'inputType' => 'select',
                'eval'      => array(
                    'includeBlankOption' => true,
                    'chosen'             => true,
                    'style'              => 'width:235px'),
            ),
            'entry'              => array(
                'label'     => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['blacklist_entry'],
                'exclude'   => true,
                'inputType' => 'text',
                'eval'      => array('style'         => 'width:352px'),
            )
        )
    ),
    'load_callback' => array(array('tl_syncCto_settings_pro', 'loadDiffBlacklist')),
    'save_callback' => array(array('tl_syncCto_settings_pro', 'saveDiffBlacklist'))
);

$GLOBALS['TL_DCA']['tl_syncCto_settings']['fields']['syncCto_sync_blacklist'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['sync_blacklist'],
    'exclude'   => true,
    'inputType' => 'multiColumnWizard',
    'eval'      => array(
        'alwaysSave'   => true,
        'columnFields' => array(
            'table' => array(
                'label'     => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['blacklist_table'],
                'reference' => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['tables'],
                'options'   => array('all', 'tl_page', 'tl_article', 'tl_content'),
                'inputType' => 'select',
                'eval'      => array(
                    'includeBlankOption' => true,
                    'chosen'             => true,
                    'style'              => 'width:235px'
                ),
            ),
            'entry'              => array(
                'label'     => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['blacklist_entry'],
                'exclude'   => true,
                'inputType' => 'text',
                'eval'      => array(
                    'style'         => 'width:352px'
                ),
            )
        )
    ),
    'load_callback' => array(array('tl_syncCto_settings_pro', 'loadSyncBlacklist')),
    'save_callback' => array(array('tl_syncCto_settings_pro', 'saveSyncBlacklist'))
);

$GLOBALS['TL_DCA']['tl_syncCto_settings']['fields']['syncCto_trigger_refresh'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['trigger_refresh'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => array(
        'alwaysSave'   => true,
        'tl_class'     => 'w50'
    ),
    'save_callback' => array(array('tl_syncCto_settings_pro', 'refreshTrigger'))
);

$GLOBALS['TL_DCA']['tl_syncCto_settings']['fields']['syncCto_trigger_delete'] = array(
    'label'     => &$GLOBALS['TL_LANG']['tl_syncCto_settings']['trigger_delete'],
    'exclude'   => true,
    'inputType' => 'checkbox',
    'eval'      => array(
        'alwaysSave'   => true,
        'tl_class'     => 'w50'
    ),
    'save_callback' => array(array('tl_syncCto_settings_pro', 'deleteTrigger'))
);

/**
 * Class for syncCto settings
 */
class tl_syncCto_settings_pro extends Backend
{
    
    // Trigger update ----------------------------------------------------------
    
    /**
     * Refresh all triggers.
     * 
     * @param type $mixValues
     * 
     * @return boolean
     */
    public function refreshTrigger($mixValues)
    {
        // Only run if checked.
        if($mixValues == true)
        {
            SyncCtoProDatabase::getInstance()->updateTriggerFromHook();
        }
        
        return false;
    }

    /**
     * Remove all triggers from the tables.
     * 
     * @param type $mixValues
     * 
     * @return boolean
     */
    public function deleteTrigger($mixValues)
    {
        // Only run if checked.
        if($mixValues == true)
        {
            SyncCtoProDatabase::getInstance()->removeTriggerFromHook();
        }
        
        return false;
    }
    
    // Diff / Trigger ----------------------------------------------------------

    /**
     * Return a list with default values and 
     * user values.
     * 
     * @param array $mixValues
     * @return array
     */
    public function loadDiffBlacklist($mixValues)
    {
        $mixValues = (array) deserialize($mixValues);
        $arrReturn = array();
        $arrKnownValues = array();

        // Get basic settings
        foreach ($GLOBALS['SYC_CONFIG']['trigger_blacklist'] as $strTableName => $arrValues)
        {
            foreach ($arrValues as $strField)
            {
                $arrReturn[] = array(
                    'table' => $strTableName,
                    'entry' => $strField
                );

                $arrKnownValues[] = $strTableName . '::' . $strField;
            }
        }

        // Unset basic settings
        foreach ($mixValues as $key => $arrValues)
        {
            $strCheckValue = $arrValues['table'] . '::' . $arrValues['entry'];

            if (in_array($strCheckValue, $arrKnownValues))
            {
                unset($mixValues[$key]);
            }
        }

        // Merge
        $arrReturn = array_merge($arrReturn, $mixValues);

        // Return
        return $arrReturn;
    }

    /**
     * Return a list with default values and 
     * user values.
     * 
     * @param array $mixValues
     * @return array
     */
    public function saveDiffBlacklist($mixValues)
    {
        $mixValues      = (array) deserialize($mixValues);
        $arrKnownValues = array();

        // Get basic settings
        foreach ($GLOBALS['SYC_CONFIG']['trigger_blacklist'] as $strTableName => $arrValues)
        {
            foreach ($arrValues as $strField)
            {
                $arrKnownValues[] = $strTableName . '::' . $strField;
            }
        }

        // Unset basic settings
        foreach ($mixValues as $key => $arrValues)
        {
            $strCheckValue = $arrValues['table'] . '::' . $arrValues['entry'];

            if (in_array($strCheckValue, $arrKnownValues))
            {
                unset($mixValues[$key]);
            }
        }

        // Return
        return serialize($mixValues);
    }

    // Sync --------------------------------------------------------------------

    /**
     * Return a list with default values and 
     * user values.
     * 
     * @param array $mixValues
     * @return array
     */
    public function loadSyncBlacklist($mixValues)
    {
        $mixValues = (array) deserialize($mixValues);
        $arrReturn = array();
        $arrKnownValues = array();

        // Get basic settings
        foreach ($GLOBALS['SYC_CONFIG']['sync_blacklist'] as $strTableName => $arrValues)
        {
            foreach ($arrValues as $strField)
            {
                $arrReturn[] = array(
                    'table' => $strTableName,
                    'entry' => $strField
                );

                $arrKnownValues[] = $strTableName . '::' . $strField;
            }
        }

        // Unset basic settings
        foreach ($mixValues as $key => $arrValues)
        {
            $strCheckValue = $arrValues['table'] . '::' . $arrValues['entry'];

            if (in_array($strCheckValue, $arrKnownValues))
            {
                unset($mixValues[$key]);
            }
        }

        // Merge
        $arrReturn = array_merge($arrReturn, $mixValues);

        // Return
        return $arrReturn;
    }

    /**
     * Return a list with default values and 
     * user values.
     * 
     * @param array $mixValues
     * @return array
     */
    public function saveSyncBlacklist($mixValues)
    {
        $mixValues      = (array) deserialize($mixValues);
        $arrKnownValues = array();
        $arrReturn = array();

        // Get basic settings
        foreach ($GLOBALS['SYC_CONFIG']['sync_blacklist'] as $strTableName => $arrValues)
        {
            foreach ($arrValues as $strField)
            {
                $arrKnownValues[] = $strTableName . '::' . $strField;
            }
        }

        // Unset basic settings
        foreach ($mixValues as $key => $arrValues)
        {
            $strCheckValue = $arrValues['table'] . '::' . $arrValues['entry'];

            if (!in_array($strCheckValue, $arrKnownValues))
            {
                $arrReturn[] = $arrValues;
            }
        }

        // Return
        return serialize($arrReturn);
    }

}

?>