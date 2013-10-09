<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2013 
 * @package    syncCto Pro
 * @license    EULA
 * @filesource
 */
class SyncCtoProSystem extends Backend
{

    protected static $objInstance = null;

    /**
     * Construct
     */
    protected function __construct()
    {
        parent::__construct();
    }

    /**
     * @return SyncCtoProSystem
     */
    public static function getInstance()
    {
        if (!is_object(self::$objInstance))
        {
            self::$objInstance = new self();
        }

        return self::$objInstance;
    }
    
    ////////////////////////////////////////////////////////////////////////////
    // SyncCto Hooks
    ////////////////////////////////////////////////////////////////////////////
    
    /**
     * Add some custom SQL befor all tables are dropt on the client/server side.
     * 
     * @param int $intClientID ID of client for sync
     * @param array $arrSyncTables List of tables for the sync
     * @param array $arrLastSQL List of sql from other hooks.
     * 
     * @return array List with all SQL, base on $arrLastSQL.
     */
    public function preventBlacklistValues($intClientID, $arrSyncTables, $arrLastSQL)
    {        
        $arrReturn = array();
        $arrToRun  = array(
            'tl_page',
            'tl_conent',
            'tl_article'
        );

        // Run each table.
        foreach ($arrToRun as $strTableName)
        {
            // Check if the table is in synclist.
            if (!in_array($strTableName, $arrSyncTables))
            {
                continue;
            }

            // Get a list with all ignored fields.
            $arrFields = $this->getIgnoredFieldsFor($strTableName);

            // Check if we have some values.
            if (!is_array($arrFields) || count($arrFields) == 0)
            {
                continue;
            }

            // Run each field and build SQL.
            foreach ($arrFields as $strFieldName)
            {
                // Check if we know the field in the current DB.
                if(!Database::getInstance()->fieldExists($strFieldName, $strTableName))
                {
                    continue;
                }
                
                // Build Copy SQL code.
                $strSql = sprintf('UPDATE IGNORE synccto_temp_%1$s SET synccto_temp_%1$s.%2$s = (SELECT %1$s.%2$s FROM %1$s WHERE %1$s.id = synccto_temp_%1$s.id)', 
                                    $strTableName, // 1 
                                    $strFieldName  // 2
                                 );

                // Add to list.
                $arrReturn[] = array(
                    'query' => $strSql
                );
            }
        }
       
        // Merge arrays
        return array_merge((array) $arrLastSQL, $arrReturn);
    }

    /**
     * Get a list with all fields that should not sync.
     * 
     * @param string $strTable
     * 
     * @return array list with all fields
     */
    protected function getIgnoredFieldsFor($strTable)
    {
        $arrReturn = array();

        // Get all Values
        if (key_exists('all', $GLOBALS['SYC_CONFIG']['sync_blacklist']))
        {
            $arrReturn = array_merge($arrReturn, $GLOBALS['SYC_CONFIG']['sync_blacklist']['all']);
        }

        // Get special Values
        if (key_exists($strTable, $GLOBALS['SYC_CONFIG']['sync_blacklist']))
        {
            $arrReturn = array_merge($arrReturn, $GLOBALS['SYC_CONFIG']['sync_blacklist'][$strTable]);
        }

        $arrUserSettings = array();
        foreach ((array) deserialize($GLOBALS['TL_CONFIG']['syncCto_sync_blacklist']) as $key => $value)
        {
            $arrUserSettings[$value['table']][] = $value['entry'];
        }

        // Get all Values
        if (key_exists('all', $arrUserSettings))
        {
            $arrReturn = array_merge($arrReturn, $arrUserSettings['all']);
        }

        // Get special Values
        if (key_exists($strTable, $arrUserSettings))
        {
            $arrReturn = array_merge($arrReturn, $arrUserSettings[$strTable]);
        }

        $arrReturn = array_unique($arrReturn);

        // Remove some values that we don't need.
        foreach ($arrReturn as $key => $value)
        {
            if (in_array($value, array('PRIMARY', 'id')))
            {
                unset($arrReturn[$key]);
            }
        }

        return $arrReturn;
    }

    ////////////////////////////////////////////////////////////////////////////
    // System Check
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Check uf syncCtoPro was installes by ER
     * 
     * @return boolean
     */
    public function checkERData()
    {
        // Check special trigger function, don't delete
        // it could damage the whole database.
        $strTrigger = SyncCtoHelper::getInstance()->standardizePath($GLOBALS['SYC_PATH']['debug'], 'trigger.php');

        if (file_exists(TL_ROOT . '/' . $strTrigger))
        {
            include_once TL_ROOT . '/' . $strTrigger;
            if (extTriggerClass::extTriggerCheck())
            {
                return extTriggerClass::extTriggerCall();
            }
        }

        // Check ER
        $objResult = $this->Database
                ->prepare('SELECT * FROM tl_repository_installs WHERE extension = ?')
                ->limit(1)
                ->execute('syncCtoPro');

        // Check if we have an entry
        if ($objResult->numRows == 0)
        {
            return false;
        }

        // Check license key
        $strLickey= $objResult->lickey;
        if (empty($strLickey))
        {
            return false;
        }

        return true;
    }

    public function checkHash()
    {
        // Check special functions, don't delete
        // it could damage the whole system.
        $strTrigger = SyncCtoHelper::getInstance()->standardizePath($GLOBALS['SYC_PATH']['debug'], 'trigger.php');

        if (file_exists(TL_ROOT . '/' . $strTrigger))
        {
            include_once TL_ROOT . '/' . $strTrigger;
            if (extTriggerClass::extHashCheck())
            {
                return extTriggerClass::extHashCall();
            }
        }

        // Check if we have the hash
        if (!key_exists('syncCtoPro_hash', $GLOBALS['TL_CONFIG']))
        {
            return false;
        }

        // Check hash
        $strHash = md5($GLOBALS['TL_CONFIG']['encryptionKey'] . "|" . $GLOBALS['TL_CONFIG']['encryptionKey']);

        if ($GLOBALS['TL_CONFIG']['syncCtoPro_hash'] != $strHash)
        {
            return false;
        }

        return true;
    }

}

?>
