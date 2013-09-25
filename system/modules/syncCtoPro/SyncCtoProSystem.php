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
