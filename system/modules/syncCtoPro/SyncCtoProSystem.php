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
        if (empty($objResult->lickey))
        {
            return false;
        }
        
        return true;
    }

}

?>
