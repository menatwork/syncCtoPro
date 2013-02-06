<?php 

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2013 
 * @package    syncCto Pro
 * @license    EULA
 * @filesource
 */

class SyncCtoStepPagesSelection extends Backend implements InterfaceSyncCtoStep
{
    ////////////////////////////////////////////////////////////////////////////
    // Vars / Objects
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @var SyncCtoModuleClient 
     */
    protected $objSyncCtoClient;

    /**
     * @var StepPool
     */
    protected $objStepPool;

    /**
     * @var ContentData 
     */
    protected $objData;

    /**
     * @var array 
     */
    protected $arrListFile;

    /**
     * @var array 
     */
    protected $arrListCompare;

    /**
     * @var array
     */
    protected $arrSyncSettings;

    /**
     * @var array
     */
    protected $arrClientInformation;

    /**
     * @var SyncCtoStepPagesSelection 
     */
    protected static $objInstance = null;

    ////////////////////////////////////////////////////////////////////////////
    // Core
    ////////////////////////////////////////////////////////////////////////////

    protected function __construct()
    {
        parent::__construct();
    }

    /**
     * @return SyncCtoStepPagesSelection
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
    // Setter / Getter
    ////////////////////////////////////////////////////////////////////////////

    public function setSyncCto(SyncCtoModuleClient $syncCtoClient)
    {
        $this->objSyncCtoClient = $syncCtoClient;

        $this->objStepPool          = $this->objSyncCtoClient->getStepPool();
        $this->objData              = $this->objSyncCtoClient->getData();
        $this->arrSyncSettings      = $this->objSyncCtoClient->getSyncSettings();
        $this->arrClientInformation = $this->objSyncCtoClient->getClientInformation();
    }

    ////////////////////////////////////////////////////////////////////////////
    // Check System
    ////////////////////////////////////////////////////////////////////////////

    public function checkSyncFrom()
    {
        return $this->checkSync();
    }

    public function checkSyncTo()
    {
        return $this->checkSync();
    }

    protected function checkSync()
    {
        if ($this->arrSyncSettings['post_data']['database_pages_check'] == true)
        {
            return true;
        }
        else
        {
            return false;
        }
    }

    ////////////////////////////////////////////////////////////////////////////
    // Helper functions
    ////////////////////////////////////////////////////////////////////////////

    protected function showSubStep1()
    {
        $this->objData->setState(SyncCtoEnum::WORK_WORK);
        $this->objData->setTitle($GLOBALS['TL_LANG']['MSC']['step'] . " %s");
        $this->objData->setDescription($GLOBALS['TL_LANG']['tl_syncCto_sync']['step_4']['description_1']);
        
        $this->objStepPool->step++;
    }

    ////////////////////////////////////////////////////////////////////////////
    // Sync functions
    ////////////////////////////////////////////////////////////////////////////

    public function syncFrom()
    {
        
    }

    public function syncTo()
    {
        /* ---------------------------------------------------------------------
         * Init
         */

        if ($this->objStepPool->step == null)
        {
            $this->objStepPool->step = 1;
        }

        /* ---------------------------------------------------------------------
         * Run page
         */

        try
        {
            switch ($this->objStepPool->step)
            {
                /**
                 * Init
                 */
                case 1:
                    $this->showSubStep1();
                    break;

                case 2:
                    echo"123";
                    var_dump(SyncCtoProDatabase::getInstance()->readXML('system/tmp/SyncCtoPro-SingleTableExport.614fd2e35c56052d8ee5eaddde13e8a5'));
                    
                    exit();
                    break;
            }
        }
        catch (Exception $exc)
        {
            $objErrTemplate              = new BackendTemplate('be_syncCto_error');
            $objErrTemplate->strErrorMsg = $exc->getMessage();

            $this->objData->setState(SyncCtoEnum::WORK_SKIPPED);
            $this->objData->setDescription($GLOBALS['TL_LANG']['tl_syncCto_sync']["step_4"]['description_1']);
            $this->objData->setHtml($objErrTemplate->parse());
            
            $this->objSyncCtoClient->setRefresh(false);
           
            $this->log(vsprintf("Error on synchronization client ID %s with msg: %s", array($this->Input->get("id"), $exc->getMessage())), __CLASS__ . " " . __FUNCTION__, "ERROR");
        }
    }

}

?>
