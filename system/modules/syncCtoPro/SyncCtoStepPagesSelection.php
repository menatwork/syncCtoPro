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
     * Extended communication include the basic communication
     * 
     * @var SyncCtoProCommunicationClient 
     */
    protected $objSyncCtoProCommunicationClient;

    /**
     * @var SyncCtoHelper
     */
    protected $objSyncCtoHelper;

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

        // Init helper
        $this->objSyncCtoProCommunicationClient = SyncCtoProCommunicationClient::getInstance();
        $this->objSyncCtoHelper                 = SyncCtoHelper::getInstance();
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

    protected function init()
    {
        // Init Step Counter
        if ($this->objStepPool->step == null)
        {
            $this->objStepPool->step = 1;
        }

        // Reset state
        $this->objData->setState(SyncCtoEnum::WORK_WORK);
        $this->objData->setHtml('');

        $this->objSyncCtoClient->setRefresh(true);
    }

    protected function showError(Exception $exc)
    {
        $objErrTemplate              = new BackendTemplate('be_syncCto_error');
        $objErrTemplate->strErrorMsg = $exc->getMessage();

        $this->objData->setState(SyncCtoEnum::WORK_ERROR);
        $this->objData->setDescription($GLOBALS['TL_LANG']['tl_syncCto_sync']["step_4"]['description_1']);
        $this->objData->setHtml($objErrTemplate->parse());

        $this->objSyncCtoClient->setRefresh(false);

        $this->log(vsprintf("Error on synchronization client ID %s with msg: %s", array($this->Input->get("id"), $exc->getMessage())), __CLASS__ . " " . __FUNCTION__, "ERROR");
    }

    public function rebuildArray($arrData)
    {
        $arrReturn = array();

        foreach ($arrData as $arrValue)
        {
            $arrReturn[$arrValue['id']] = $arrValue;
        }

        return $arrReturn;
    }

    ////////////////////////////////////////////////////////////////////////////
    // Steps
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Show first step
     */
    protected function showBasicStep()
    {
        $this->objData->setState(SyncCtoEnum::WORK_WORK);
        $this->objData->setTitle($GLOBALS['TL_LANG']['MSC']['step'] . " %s");
        $this->objData->setDescription('Einzel Seiten Synchronisieren.');

        // Set output
        $this->objStepPool->step++;
    }

    /**
     * Load a list with id/titles from client
     */
    protected function generateDataForPageTree()
    {
        $strPageFile = $this->objSyncCtoProCommunicationClient->exportDatabaseSE('', 'tl_page', null, array('id', 'pid', 'title'));
        $strArticleFile = $this->objSyncCtoProCommunicationClient->exportDatabaseSE('', 'tl_article', null, array('id', 'pid', 'title'));
        $strContentFile = $this->objSyncCtoProCommunicationClient->exportDatabaseSE('', 'tl_content', null, array('id', 'pid', 'type'));

        // Check if we have all files
        if ($strPageFile === false)
        {
            throw new Exception('Missing export file for tl_page');
        }

        if ($strArticleFile === false)
        {
            throw new Exception('Missing export file for tl_content');
        }

        if ($strContentFile === false)
        {
            throw new Exception('Missing export file for tl_article');
        }

        // Save for next step
        $this->objStepPool->files = array(
            'tl_page'    => $strPageFile,
            'tl_article' => $strArticleFile,
            'tl_content' => $strContentFile
        );

        // Save on Session for popup
        $this->arrSyncSettings['syncCtoPro_ExternFile'] = array(
            'tl_page'    => $strPageFile,
            'tl_article' => $strArticleFile,
            'tl_content' => $strContentFile
        );
        
        $this->objSyncCtoClient->setSyncSettings($this->arrSyncSettings);

        // Set output
        $this->objStepPool->step++;
    }

    /**
     * Load the se export files from client
     * 
     * @throws Exception
     */
    protected function loadFilesForPageTree()
    {
        // Save for next step
        $arrFilePathes = $this->objStepPool->files;

        // Check if we have files in list
        if (empty($arrFilePathes))
        {
            throw new Exception('No files for donwnload found.');
        }

        // Download each file
        foreach ($arrFilePathes as $strType => $strFilePath)
        {
            $strSavePath = $this->objSyncCtoHelper->standardizePath($GLOBALS['SYC_PATH']['tmp'], basename($strFilePath));

            $blnResponse = $this->objSyncCtoProCommunicationClient->getFile($strFilePath, $strSavePath);

            // Check if we have the file
            if (!$blnResponse)
            {
                throw new Exception("Empty file list from client. Maybe file sending was not complet for $strType.");
            }

            $arrFilePathes[$strType] = $strSavePath;
        }

        // Set output
        $this->objStepPool->step++;
    }

    /**
     * Choose pages
     * @return type
     */
    protected function showPageTree()
    {
        $arrIds = $this->Input->post('ids');

        if (key_exists("forward", $_POST) && !empty($arrIds))
        {
            $this->objStepPool->pageIDs = $this->Input->post('ids');

            // Go to next step
            $this->objData->setState(SyncCtoEnum::WORK_WORK);
            $this->objData->setHtml("");

            $this->objStepPool->step++;

            $this->objSyncCtoClient->setRefresh(true);

            return;
        }
        else if ((key_exists("forward", $_POST) && empty($arrIds)) || key_exists("skip", $_POST))
        {
            // Skip if no tables are selected
            $this->objData->setState(SyncCtoEnum::WORK_SKIPPED);
            $this->objData->setHtml("");

            $this->objSyncCtoClient->setRefresh(true);
            $this->objSyncCtoClient->addStep();

            return;
        }

        // Template
        $objTemp = new BackendTemplate('be_syncCtoPro_form');
        $objTemp->id               = $this->objSyncCtoClient->getClientID();
        $objTemp->step             = $this->objSyncCtoClient->getStep();
        $objTemp->direction        = "To";
        $objTemp->helperClass      = $this;

        // Set output
        $this->objData->setHtml($this->replaceInsertTags($objTemp->parse()));
        $this->objSyncCtoClient->setRefresh(false);
    }

    /**
     * 
     * @param array $arrSourcePages
     * @param array $arrSourceHashes
     * @param array $arrTargetPages
     * @param array $arrTargetHashes
     */
    protected function buildTree($arrSourcePages, $arrSourceHashes, $arrTargetPages, $arrTargetHashes)
    {
        // Set id as key
        $arrSourcePages = $this->rebuildArray($arrSourcePages);
        $arrTargetPages = $this->rebuildArray($arrTargetPages);

        // Search for missing entries
        $arrKeysSource = array_keys($arrSourcePages);
        $arrKeysTarget = array_keys($arrTargetPages);

        $arrMissingClient = array_diff($arrKeysSource, $arrKeysTarget);
        $arrMissingServer = array_diff($arrKeysTarget, $arrKeysSource);

        $arrReturn = array();

        foreach ($arrSourcePages as $intID => $mixValues)
        {
            $arrReturn[$intID] = array(
                'id'     => $intID,
                'source' => array(
                    'title'  => $mixValues['title'],
                    'id'     => $mixValues['id'],
                    'pid'    => $mixValues['pid'],
                    'hash'   => $arrSourceHashes[$intID]['hash']
                ),
                'target' => array(
                    'title' => $arrTargetPages[$intID]['title'],
                    'id'    => $arrTargetPages[$intID]['id'],
                    'pid'   => $arrTargetPages[$intID]['pid'],
                    'hash'  => $arrTargetHashes[$intID]['hash']
                ),
            );
        }

        foreach ($arrMissingServer as $intID)
        {
            $arrReturn[$intID] = array(
                'id'     => $intID,
                'source' => array(),
                'target' => array(
                    'title' => $arrTargetPages[$intID]['title'],
                    'id'    => $arrTargetPages[$intID]['id'],
                    'pid'   => $arrTargetPages[$intID]['pid'],
                    'hash'  => $arrTargetHashes[$intID]['hash']
                ),
            );
        }

        return $arrReturn;
    }

    /**
     * Build Data
     * @throws Exception
     */
    protected function generateUpdateFiles()
    {
        $arrPages    = $this->objStepPool->pageIDs;
        $arrArticles = array();
        $arrContentElements = array();

        // Article

        $arrResultArticles = $this->Database
                ->prepare('SELECT id FROM tl_article WHERE pid IN(' . implode(', ', $arrPages) . ')')
                ->execute()
                ->fetchAllAssoc();

        foreach ($arrResultArticles as $arrArticle)
        {
            $arrArticles[] = $arrArticle['id'];
        }

        // Content Elements

        $arrResultContentElements = $this->Database
                ->prepare('SELECT id FROM tl_content WHERE pid IN(' . implode(', ', $arrArticles) . ')')
                ->execute()
                ->fetchAllAssoc();

        foreach ($arrResultContentElements as $arrContentElement)
        {
            $arrContentElements[] = $arrContentElement['id'];
        }

        $objSyncCtoDatabasePro = SyncCtoProDatabase::getInstance();

        // Write some tempfiles
        $strRandomToken = substr(md5(time() . " | " . rand(0, 65535)), 0, 8);

        $strPageFile    = $objSyncCtoDatabasePro->getDataForAsFile($this->objSyncCtoHelper->standardizePath($GLOBALS['SYC_PATH']['tmp'], "SyncCto-SE-$strRandomToken-page.gzip"), 'tl_page', $arrPages);
        $strArticleFile = $objSyncCtoDatabasePro->getDataForAsFile($this->objSyncCtoHelper->standardizePath($GLOBALS['SYC_PATH']['tmp'], "SyncCto-SE-$strRandomToken-article.gzip"), 'tl_article', $arrArticles);
        $strContentFile = $objSyncCtoDatabasePro->getDataForAsFile($this->objSyncCtoHelper->standardizePath($GLOBALS['SYC_PATH']['tmp'], "SyncCto-SE-$strRandomToken-content.gzip"), 'tl_content', $arrContentElements);

        // Check if we have all files
        if ($strPageFile === false || $strArticleFile === false || $strContentFile === false)
        {
            throw new Exception('Missing export file for tl_page');
        }

        if ($strPageFile === false || $strArticleFile === false || $strContentFile === false)
        {
            throw new Exception('Missing export file for tl_content');
        }

        if ($strPageFile === false || $strArticleFile === false || $strContentFile === false)
        {
            throw new Exception('Missing export file for tl_article');
        }

        $this->objStepPool->files = array(
            'tl_page'    => "SyncCto-SE-$strRandomToken-page.gzip",
            'tl_article' => "SyncCto-SE-$strRandomToken-article.gzip",
            'tl_content' => "SyncCto-SE-$strRandomToken-content.gzip",
        );

        // Set output
        $this->objStepPool->step++;
    }

    /**
     * Send files
     * @throws Exception
     */
    protected function sendUpdateFiles()
    {
        foreach ($this->objStepPool->files as $strType => $strFile)
        {
            $arrResponse = $this->objSyncCtoProCommunicationClient->sendFile($GLOBALS['SYC_PATH']['tmp'], $strFile, "", SyncCtoEnum::UPLOAD_SQL_TEMP);

            // Check if the file was send and saved.
            if (!is_array($arrResponse) || count($arrResponse) == 0)
            {
                throw new Exception("Empty file list from client. Maybe file sending was not complet for $strType.");
            }
        }

        // Set output
        $this->objData->setDescription($GLOBALS['TL_LANG']['tl_syncCto_sync']['step_4']['description_3']);
        $this->objStepPool->step++;
    }

    /**
     * Import
     * @throws Exception
     */
    protected function importExtern()
    {
        foreach ($this->objStepPool->files as $strType => $strFile)
        {
            $blnResponse = $this
                    ->objSyncCtoProCommunicationClient
                    ->importDatabaseSE($this->objSyncCtoHelper->standardizePath($this->arrClientInformation['folders']['tmp'], 'sql', $strFile));

            // Check if the file was send and saved.
            if (!$blnResponse)
            {
                throw new Exception("Could not import file for $strType.");
            }
        }

        // Set output
        $this->objData->setState(SyncCtoEnum::WORK_OK);
        $this->objData->setHtml('');
        $this->objData->setDescription($GLOBALS['TL_LANG']['tl_syncCto_sync']['step_4']['description_3']);

        $this->objSyncCtoClient->addStep();
        $this->objSyncCtoClient->setRefresh(true);
    }

    ////////////////////////////////////////////////////////////////////////////
    // Sync functions
    ////////////////////////////////////////////////////////////////////////////

    public function syncFrom()
    {
        
    }

    public function syncTo()
    {
        $this->init();

        $i = 1;
        try
        {
            switch ($this->objStepPool->step)
            {
                case $i++:
                    $this->showBasicStep();
                    break;

                case $i++:
                    $this->generateDataForPageTree();
                    break;

                case $i++:
                    $this->loadFilesForPageTree();
                    break;

                case $i++:
                    $this->showPageTree();
                    break;

                case $i++:
                    $this->generateUpdateFiles();
                    break;

                case $i++:
                    $this->sendUpdateFiles();
                    break;

                case $i++:
                    $this->importExtern();
                    break;
            }
        }
        catch (Exception $exc)
        {
            $this->showError($exc);
        }
    }

}

?>
