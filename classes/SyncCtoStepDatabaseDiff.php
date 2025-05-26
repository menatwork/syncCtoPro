<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2013
 * @package    syncCto Pro
 * @license    EULA
 * @filesource
 */
class SyncCtoStepDatabaseDiff extends \Backend implements InterfaceSyncCtoStep
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
     * Ext. database class for pro.
     *
     * @var SyncCtoProDatabase
     */
    protected $objSyncCtoProDatabase;

    /**
     * @var SyncCtoHelper
     */
    protected $objSyncCtoHelper;

    /**
     * @var SyncCtoStepDatabaseDiff
     */
    protected static $objInstance = null;

    const WORKINGMODE_NONE   = 0;
    const WORKINGMODE_DIFF   = 1;
    const WORKINGMODE_UPDATE = 2;

    ////////////////////////////////////////////////////////////////////////////
    // Core
    ////////////////////////////////////////////////////////////////////////////

    protected function __construct()
    {
        parent::__construct();

        // Init helper
        $this->objSyncCtoProCommunicationClient = SyncCtoProCommunicationClient::getInstance();
        $this->objSyncCtoProDatabase            = SyncCtoProDatabase::getInstance();
        $this->objSyncCtoHelper                 = SyncCtoHelper::getInstance();

        $this->loadLanguageFile('tl_syncCtoPro_steps');
    }

    /**
     * @return SyncCtoStepDatabaseDiff
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

    /**
     * @param \SyncCtoModuleClient $syncCtoClient
     */
    public function setSyncCto(SyncCtoModuleClient $syncCtoClient)
    {
        $this->objSyncCtoClient = $syncCtoClient;

        $this->objStepPool          = $this->objSyncCtoClient->getStepPool();
        $this->objData              = $this->objSyncCtoClient->getData();
        $this->arrSyncSettings      = $this->objSyncCtoClient->getSyncSettings();
        $this->arrClientInformation = $this->objSyncCtoClient->getClientInformation();
    }

    /**
     * Save the sync settings for a client
     */
    protected function saveSyncSettings()
    {
        $this->objSyncCtoClient->setSyncSettings($this->arrSyncSettings);
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
        // If automode return false, always.
        if ($this->arrSyncSettings['automode'])
        {
            return false;
        }

        return $this->checkSync();
    }

    protected function checkSync()
    {
        if ($this->getWorkingMode() == self::WORKINGMODE_DIFF)
        {
            return true;
        }

        return false;
    }

    /**
     * Check what we have to do. Only update hashes or do a diff.
     *
     * @return int
     */
    protected function getWorkingMode()
    {
        // Check if database is enabeld
        if ($this->arrSyncSettings['syncCto_SyncDatabase'] != true)
        {
            return self::WORKINGMODE_NONE;
        }

        // Check if diff is enabeld - DC_Memoery and DC_General
        if ($this->arrSyncSettings['post_data']['database_pages_check'] == true || $this->arrSyncSettings['post_data']['database_pages_check_b'] == true || $this->arrSyncSettings['automode'] == true)
        {
            return self::WORKINGMODE_DIFF;
        }

        // Check if we have to regenerate the hashes
        if (is_array($this->arrSyncSettings['syncCto_SyncTables']) && (array_search('tl_page', $this->arrSyncSettings['syncCto_SyncTables']) !== false || array_search('tl_article', $this->arrSyncSettings['syncCto_SyncTables']) !== false || array_search('tl_content', $this->arrSyncSettings['syncCto_SyncTables']) !== false))
        {
            return self::WORKINGMODE_UPDATE;
        }

        return self::WORKINGMODE_NONE;
    }

    ////////////////////////////////////////////////////////////////////////////
    // Hook - Additional Functions
    ////////////////////////////////////////////////////////////////////////////

    public function localeUpdateTimestamp(SyncCtoModuleClient $syncCtoClient, $intClientID)
    {
        // Set Basic
        $this->setSyncCto($syncCtoClient);

        try
        {
            // Call function
            $this->refreshTimestamps();

            // Set info
            $this->objData->setDescription($GLOBALS['TL_LANG']['tl_syncCtoPro_steps']['step_1']['description_2']);
        }
        catch (Exception $exc)
        {
            $strHTML = $this->objData->getHtml();
            $strHTML .= '<p> There was an error for SyncCto Pro: ' . $exc->getMessage() . '</p>';
            $this->objData->setHtml($strHTML);
        }
    }

    /**
     * Refresh all hashes on client side.
     */
    protected function refreshHashes()
    {
        // Check if we have content
        $arrPages           = (array) $this->arrSyncSettings['syncCtoPro_transfer']['tl_page'];
        $arrArticles        = (array) $this->arrSyncSettings['syncCtoPro_transfer']['tl_article'];
        $arrContentElements = (array) $this->arrSyncSettings['syncCtoPro_transfer']['tl_content'];

        if (!empty($arrPages))
        {
            $blnPage = true;
        }

        if (!empty($arrArticles))
        {
            $blnArticle = true;
        }

        if (!empty($arrContentElements))
        {
            $blnContent = true;
        }

        // Update only changed tables.
        $this->objSyncCtoProCommunicationClient->updateSpecialTriggers($blnPage, $blnArticle, $blnContent, true);
    }

    /**
     * Refresh the timestamps
     */
    protected function refreshTimestamps()
    {
        $arrTables = array();

        // Check which table has been updated
        $arrPages           = (array) $this->arrSyncSettings['syncCtoPro_transfer']['tl_page'];
        $arrArticles        = (array) $this->arrSyncSettings['syncCtoPro_transfer']['tl_article'];
        $arrContentElements = (array) $this->arrSyncSettings['syncCtoPro_transfer']['tl_content'];

        if (!empty($arrPages))
        {
            $arrTables[] = 'tl_page';
        }

        if (!empty($arrArticles))
        {
            $arrTables[] = 'tl_article';
        }

        if (!empty($arrContentElements))
        {
            $arrTables[] = 'tl_content';
        }

        $arrTableTimestamp = array(
            'server' => $this->objSyncCtoHelper->getDatabaseTablesTimestamp($arrTables),
            'client' => $this->objSyncCtoProCommunicationClient->getClientTimestamp($arrTables)
        );

        foreach ($arrTableTimestamp AS $location => $arrTimeStamps)
        {
            // Update timestamp
            $mixLastTableTimestamp =\Database::getInstance()
                    ->prepare("SELECT " . $location . "_timestamp FROM tl_synccto_clients WHERE id=?")
                    ->limit(1)
                    ->execute($this->intClientID)
                    ->fetchAllAssoc();

            if (strlen($mixLastTableTimestamp[0][$location . "_timestamp"]) != 0)
            {
                $arrLastTableTimestamp = deserialize($mixLastTableTimestamp[0][$location . "_timestamp"]);
            }
            else
            {
                $arrLastTableTimestamp = array();
            }

            foreach ($arrTimeStamps as $key => $value)
            {
                $arrLastTableTimestamp[$key] = $value;
            }

            // Search for old entries
            $arrTables =\Database::getInstance()->listTables();
            foreach ($arrLastTableTimestamp as $key => $value)
            {
                if (!in_array($key, $arrTables))
                {
                    unset($arrLastTableTimestamp[$key]);
                }
            }

           \Database::getInstance()
                    ->prepare("UPDATE tl_synccto_clients SET " . $location . "_timestamp = ? WHERE id = ? ")
                    ->execute(serialize($arrLastTableTimestamp), $this->intClientID);
        }
    }

    ////////////////////////////////////////////////////////////////////////////
    // Sync functions
    ////////////////////////////////////////////////////////////////////////////

    public function syncFrom()
    {
        try
        {
            throw new Exception('Not impl. now.');
        }
        catch (Exception $exc)
        {
            $objErrTemplate              = new BackendTemplate('be_syncCto_error');
            $objErrTemplate->strErrorMsg = $exc->getMessage();

            $this->objData->setState(SyncCtoEnum::WORK_ERROR);
            $this->objData->setDescription($GLOBALS['TL_LANG']['tl_syncCto_sync']["step_4"]['description_1']);
            $this->objData->setHtml($objErrTemplate->parse());

            $this->objSyncCtoClient->setRefresh(true);
            $this->objSyncCtoClient->addStep();

            $this->log(vsprintf("Error on synchronization client ID %s with msg: %s", array($this->Input->get("id"), $exc->getMessage())), __CLASS__ . " " . __FUNCTION__, "ERROR");
        }
    }

    public function syncTo()
    {
        $this->init();

        // Do the whole diff.
        try
        {
            $i = 1;
            switch ($this->objStepPool->step)
            {
                case $i++: // 1
                    $this->showBasicStep();
                    break;

                case $i++: // 2
                    $this->generateDataForPageTree();
                    break;

                case $i++: // 3
                    $this->loadFilesForPageTree();
                    break;

                case $i++: // 4
                    $this->checkRun();
                    break;

                case $i++: // 5
                    if ($this->arrSyncSettings['automode'])
                    {
                        $this->runAutoDiff();
                    }
                    else
                    {
                        $this->showPopup('To');
                    }
                    break;

                case $i++: // 6
                    $this->generateLocalUpdateFiles();
                    break;

                case $i++: // 7
                    $this->sendUpdateFiles();
                    break;

                case $i++: // 8
                    $this->importExtern();
                    break;

                case $i++: // 9
//                    $this->refreshTimestamps();
                    $this->setNextStep();
                    break;
            }
        }
        catch (Exception $exc)
        {
            $this->showError($exc);
        }
    }

    ////////////////////////////////////////////////////////////////////////////
    // Steps
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Init cache etc
     */
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

    /**
     * Show Error
     *
     * @param Exception $exc
     */
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

    /**
     * Step 1 - Show first step
     */
    protected function showBasicStep()
    {
        $this->objData->setState(SyncCtoEnum::WORK_WORK);
        $this->objData->setTitle($GLOBALS['TL_LANG']['MSC']['step'] . " %s");

        if ($this->getWorkingMode() == self::WORKINGMODE_DIFF)
        {
            $this->objData->setDescription($GLOBALS['TL_LANG']['tl_syncCtoPro_steps']['step_1']['description_1']);
        }
        else if ($this->getWorkingMode() == self::WORKINGMODE_UPDATE)
        {
            $this->objData->setDescription($GLOBALS['TL_LANG']['tl_syncCtoPro_steps']['step_1']['description_2']);
        }

        // Set output
        $this->objStepPool->step++;
    }

    /**
     * Step 1.1 - Check if we have to show the popup
     */
    protected function checkSystem($blnWithTables = true)
    {
        if (!SyncCtoProSystem::getInstance()->checkERData() || !SyncCtoProSystem::getInstance()->checkHash())
        {
            // Skip if no tables are selected
            $this->objData->setState(SyncCtoEnum::WORK_SKIPPED);
            $this->objData->setDescription(vsprintf($GLOBALS['TL_LANG']['tl_syncCtoPro_steps']['pro_sync']['error_server'], array('<a href="' . Environment::getInstance()->base . '" target="_blank" style="text-decoration:underline;">', '</a>')));
            $this->objData->setHtml($GLOBALS['TL_LANG']['tl_syncCtoPro_steps']['security']['error']);

            $this->objSyncCtoClient->setRefresh(true);
            $this->objSyncCtoClient->addStep();

            return;
        }

        if (!$this->objSyncCtoProCommunicationClient->checkER() || !$this->objSyncCtoProCommunicationClient->checkHash())
        {
            // Get Client information
            $arrClientInformation = SyncCtoCommunicationClient::getInstance()->getClientData();
            $strAddress           = $arrClientInformation['address'] . ':' . $arrClientInformation['port'] . '/ctoCommunication';

            // Skip if no tables are selected
            $this->objData->setState(SyncCtoEnum::WORK_SKIPPED);
            $this->objData->setDescription(vsprintf($GLOBALS['TL_LANG']['tl_syncCtoPro_steps']['pro_sync']['error_client'], array('<a href="' . $strAddress . '" target="_blank" style="text-decoration:underline;">', '</a>')));
            $this->objData->setHtml($GLOBALS['TL_LANG']['tl_syncCtoPro_steps']['security']['error']);

            $this->objSyncCtoClient->setRefresh(true);
            $this->objSyncCtoClient->addStep();

            return;
        }

        // If we have no diffs skipp this step
        if ($blnWithTables && count($this->arrSyncSettings['syncCtoPro_tables_checked']) == 0)
        {
            // Skip if no tables are selected
            $this->objData->setState(SyncCtoEnum::WORK_SKIPPED);
            $this->objData->setHtml("");

            $this->objSyncCtoClient->setRefresh(true);
            $this->objSyncCtoClient->addStep();

            return;
        }

        // Go to next step
        $this->objData->setState(SyncCtoEnum::WORK_WORK);
        $this->objData->setHtml("");

        $this->objStepPool->step++;

        $this->objSyncCtoClient->setRefresh(true);

        return;
    }

    /**
     * Step 2 - Load a list with id/titles from client
     */
    protected function generateDataForPageTree()
    {
        $strPageFile    = $this->objSyncCtoProCommunicationClient->exportDatabaseSE('', 'tl_page', null, array('id', 'pid','title', 'sorting', 'published', 'start', 'stop', 'type', 'hide', 'protected'));
        $strArticleFile = $this->objSyncCtoProCommunicationClient->exportDatabaseSE('', 'tl_article', null, array('id', 'pid', 'title'));
        $strContentFile = $this->objSyncCtoProCommunicationClient->exportDatabaseSE('', 'tl_content', null, array('id', 'pid', 'type', 'ptable'));

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
     * Step 3 - Load the se export files from client
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
     * Step 4 - Check if we have to show the popup
     */
    protected function checkRun()
    {
        // Get all data / load helper
        $arrFilePathes         = $this->arrSyncSettings['syncCtoPro_ExternFile'];
        $objSyncCtoProDatabase = SyncCtoProDatabase::getInstance();

        $intDiffFounds = 0;

        // Pages -----------
        // Read client pages
        $arrClientPages      = $objSyncCtoProDatabase->readXML($arrFilePathes['tl_page']);
        $arrClientPageHashes = $this->objSyncCtoProCommunicationClient->getHashValueFor('tl_page');

        // Get server Pages
        $arrPages =\Database::getInstance()
                ->query('SELECT id, pid, title, sorting, published, start, stop, type, hide, protected FROM tl_page ORDER BY pid, id')
                ->fetchAllAssoc();

        $arrPageHashes = $objSyncCtoProDatabase->getHashValueFor('tl_page', array());

        $intDiffFounds += $this->countDiffs($arrPages, $arrPageHashes, $arrClientPages['data'], $arrClientPageHashes);

        // Article ---------
        $arrClientArticle       = $objSyncCtoProDatabase->readXML($arrFilePathes['tl_article']);
        $arrClientArticleHashes = $this->objSyncCtoProCommunicationClient->getHashValueFor('tl_article');

        // Get server article
        $arrArticle =\Database::getInstance()
                ->query('SELECT title, id, pid FROM tl_article ORDER BY pid, id')
                ->fetchAllAssoc();

        $arrArticleHashes = $objSyncCtoProDatabase->getHashValueFor('tl_article', array());

        $intDiffFounds += $this->countDiffs($arrArticle, $arrArticleHashes, $arrClientArticle['data'], $arrClientArticleHashes);

        // Content ---------
        $arrClientContent       = $objSyncCtoProDatabase->readXML($arrFilePathes['tl_content']);
        $arrClientContentHashes = $this->objSyncCtoProCommunicationClient->getHashValueFor('tl_content');

        // Get server article
        $arrContent =\Database::getInstance()
                ->query('SELECT type, id, pid FROM tl_content ORDER BY pid, id')
                ->fetchAllAssoc();

        $arrContentHashes = $objSyncCtoProDatabase->getHashValueFor('tl_content', array());

        $intDiffFounds += $this->countDiffs($arrContent, $arrContentHashes, $arrClientContent['data'], $arrClientContentHashes);

        // If we have no diffs skipp this step
        if ($intDiffFounds == 0)
        {
            // Skip if no tables are selected
            $this->objData->setState(SyncCtoEnum::WORK_SKIPPED);
            $this->objData->setHtml("");

            $this->objSyncCtoClient->setRefresh(true);
            $this->objSyncCtoClient->addStep();

            return;
        }

        // Go to next step
        $this->objData->setState(SyncCtoEnum::WORK_WORK);
        $this->objData->setHtml("");

        $this->objStepPool->step++;

        $this->objSyncCtoClient->setRefresh(true);

        return;
    }

    /**
     * Step 5 - Show popup for pages
     *
     * @param $strDirection
     *
     * @return type
     */
    protected function showPopup($strDirection)
    {
        if (array_key_exists("forward", $_POST))
        {
            // Check if we have some data
            if (empty($this->arrSyncSettings['syncCtoPro_transfer']) && empty($this->arrSyncSettings['syncCtoPro_delete_client']))
            {
                // Skip if no tables are selected
                $this->objData->setState(SyncCtoEnum::WORK_SKIPPED);
                $this->objData->setHtml("");

                $this->objSyncCtoClient->setRefresh(true);
                $this->objSyncCtoClient->addStep();

                return;
            }

            // Go to next step
            $this->objData->setState(SyncCtoEnum::WORK_WORK);
            $this->objData->setHtml("");

            $this->objStepPool->step++;

            $this->objSyncCtoClient->setRefresh(true);

            return;
        }

        // Template
        $objTemp              = new BackendTemplate('be_syncCtoPro_form');
        $objTemp->id          = $this->objSyncCtoClient->getClientID();
        $objTemp->step        = $this->objSyncCtoClient->getStep();
        $objTemp->direction   = $strDirection;
        $objTemp->helperClass = $this;

        // Set output
        $this->objData->setHtml($this->replaceInsertTags($objTemp->parse()));
        $this->objSyncCtoClient->setRefresh(false);
    }

    /**
     * Step 5 - Show popup for pages
     *
     * @return type
     */
    protected function runAutoDiff()
    {
         // Get all data / load helper
        $arrFilePathes         = $this->arrSyncSettings['syncCtoPro_ExternFile'];
        $objSyncCtoProDatabase = SyncCtoProDatabase::getInstance();

        $arrPageDiffs = array();
        $arrArticleDiffs = array();
        $arrContentDiffs = array();

        // Pages -----------
        // Read client pages
        $arrClientPages      = $objSyncCtoProDatabase->readXML($arrFilePathes['tl_page']);
        $arrClientPageHashes = $this->objSyncCtoProCommunicationClient->getHashValueFor('tl_page');

        // Get server Pages
        $arrPages =\Database::getInstance()
                ->query('SELECT title, id, pid FROM tl_page ORDER BY pid, id')
                ->fetchAllAssoc();

        $arrPageHashes = $objSyncCtoProDatabase->getHashValueFor('tl_page', array());

        $arrPageDiffs = $this->getIdDiffs($arrPages, $arrPageHashes, $arrClientPages['data'], $arrClientPageHashes);

        // Article ---------
        $arrClientArticle       = $objSyncCtoProDatabase->readXML($arrFilePathes['tl_article']);
        $arrClientArticleHashes = $this->objSyncCtoProCommunicationClient->getHashValueFor('tl_article');

        // Get server article
        $arrArticle =\Database::getInstance()
                ->query('SELECT title, id, pid FROM tl_article ORDER BY pid, id')
                ->fetchAllAssoc();

        $arrArticleHashes = $objSyncCtoProDatabase->getHashValueFor('tl_article', array());

        $arrArticleDiffs = $this->getIdDiffs($arrArticle, $arrArticleHashes, $arrClientArticle['data'], $arrClientArticleHashes);

        // Content ---------
        $arrClientContent       = $objSyncCtoProDatabase->readXML($arrFilePathes['tl_content']);
        $arrClientContentHashes = $this->objSyncCtoProCommunicationClient->getHashValueFor('tl_content');

        // Get server article
        $arrContent =\Database::getInstance()
                ->query('SELECT type, id, pid FROM tl_content ORDER BY pid, id')
                ->fetchAllAssoc();

        $arrContentHashes = $objSyncCtoProDatabase->getHashValueFor('tl_content', array());

        $arrContentDiffs = $this->getIdDiffs($arrContent, $arrContentHashes, $arrClientContent['data'], $arrClientContentHashes);

        $this->saveIdToSession($arrPageDiffs, 'tl_page');
        $this->saveIdToSession($arrArticleDiffs, 'tl_article');
        $this->saveIdToSession($arrContentDiffs, 'tl_content');

        // Go to next step
        $this->objData->setState(SyncCtoEnum::WORK_WORK);
        $this->objData->setHtml("");

        $this->objStepPool->step++;

        $this->objSyncCtoClient->setRefresh(true);

        return;
    }

    /**
     * Step 6 - Build update files for extern
     *
     * @throws Exception
     */
    protected function generateLocalUpdateFiles()
    {
        $arrPages           = (array) $this->arrSyncSettings['syncCtoPro_transfer']['tl_page'];
        $arrArticles        = (array) $this->arrSyncSettings['syncCtoPro_transfer']['tl_article'];
        $arrContentElements = (array) $this->arrSyncSettings['syncCtoPro_transfer']['tl_content'];

        if (empty($arrPages) && empty($arrArticles) && empty($arrContentElements))
        {
            // Set output
            $this->objStepPool->step = $this->objStepPool->step + 2;
            return;
        }

        $objSyncCtoDatabasePro = SyncCtoProDatabase::getInstance();

        // Write some tempfiles
        $strRandomToken = substr(md5(time() . " | " . rand(0, 65535)), 0, 8);
        $arrFiles       = array();

        if (!empty($arrPages))
        {
            $strPageFile = $objSyncCtoDatabasePro->getDataForAsFile($this->objSyncCtoHelper->standardizePath($GLOBALS['SYC_PATH']['tmp'], "SyncCto-SE-$strRandomToken-page.gzip"), 'tl_page', $arrPages, null, $this->getIgnoredFieldsFor('tl_page'));

            // Check if we have all files
            if ($strPageFile === false)
            {
                throw new Exception('Missing export file for tl_page');
            }

            $arrFiles['tl_page'] = "SyncCto-SE-$strRandomToken-page.gzip";
        }

        if (!empty($arrArticles))
        {
            $strArticleFile = $objSyncCtoDatabasePro->getDataForAsFile($this->objSyncCtoHelper->standardizePath($GLOBALS['SYC_PATH']['tmp'], "SyncCto-SE-$strRandomToken-article.gzip"), 'tl_article', $arrArticles, null, $this->getIgnoredFieldsFor('tl_article'));

            // Check if we have all files
            if ($strArticleFile === false)
            {
                throw new Exception('Missing export file for tl_article');
            }

            $arrFiles['tl_article'] = "SyncCto-SE-$strRandomToken-article.gzip";
        }

        if (!empty($arrContentElements))
        {
            $strContentFile = $objSyncCtoDatabasePro->getDataForAsFile($this->objSyncCtoHelper->standardizePath($GLOBALS['SYC_PATH']['tmp'], "SyncCto-SE-$strRandomToken-content.gzip"), 'tl_content', $arrContentElements, null, $this->getIgnoredFieldsFor('tl_content'));

            // Check if we have all files
            if ($strContentFile === false)
            {
                throw new Exception('Missing export file for tl_content');
            }

            $arrFiles['tl_content'] = "SyncCto-SE-$strRandomToken-content.gzip";
        }

        $this->objStepPool->files = $arrFiles;

        // Set output
        $this->objStepPool->step++;
    }

    /**
     * Step 6 - Build update files for local
     *
     * @throws Exception
     */
    protected function generateExternUpdateFiles()
    {
        $arrPages           = $this->arrSyncSettings['syncCtoPro_transfer']['tl_page'];
        $arrArticles        = array();
        $arrContentElements = array();

        // Article

        $arrResultArticles =\Database::getInstance()
                ->prepare('SELECT id FROM tl_article WHERE pid IN(' . implode(', ', $arrPages) . ')')
                ->execute()
                ->fetchAllAssoc();

        foreach ($arrResultArticles as $arrArticle)
        {
            $arrArticles[] = $arrArticle['id'];
        }

        // Content Elements

        $arrResultContentElements =\Database::getInstance()
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
     * Step 7 - Send files
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
     * Step 7 - Get files
     * @throws Exception
     */
    protected function getUpdateFiles()
    {
        foreach ($this->objStepPool->files as $strType => $strFile)
        {
            $arrResponse = $this->objSyncCtoProCommunicationClient->getFile($strFile, $this->objSyncCtoHelper->standardizePath($GLOBALS['SYC_PATH']['tmp'], $strFile));

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
     * Step 8 - Import on client
     * @throws Exception
     */
    protected function importExtern()
    {
        // Insert Data
        if (!empty($this->arrSyncSettings['syncCtoPro_transfer']))
        {
            foreach ((array) $this->objStepPool->files as $strType => $strFile)
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
        }

        // Delete Data
        if (!empty($this->arrSyncSettings['syncCtoPro_delete_client']))
        {
            $arrDeleteIds = $this->arrSyncSettings['syncCtoPro_delete_client'];

            foreach ((array) $arrDeleteIds as $strTable => $arrIds)
            {
                $this->objSyncCtoProCommunicationClient->deleteEntries($strTable, $arrIds);
            }
        }

        // Set output
        $this->objData->setDescription($GLOBALS['TL_LANG']['tl_syncCto_sync']['step_4']['description_3']);
        $this->objStepPool->step++;
    }

    /**
     * Step 8 - Import on server
     * @throws Exception
     */
    protected function importLocal()
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

    /**
     * Set all for the next step
     */
    protected function setNextStep()
    {
        $this->objData->setState(SyncCtoEnum::WORK_OK);
        $this->objData->setHtml('');

        $this->objSyncCtoClient->addStep();
        $this->objSyncCtoClient->setRefresh(true);
    }

    ////////////////////////////////////////////////////////////////////////////
    // Helper functions
    ////////////////////////////////////////////////////////////////////////////

    /**
     * count the diffs
     *
     * @param array $arrSourcePages
     * @param array $arrSourceHashes
     * @param array $arrTargetPages
     * @param array $arrTargetHashes
     *
     * @return int
     */
    protected function countDiffs($arrSourcePages, $arrSourceHashes, $arrTargetPages, $arrTargetHashes)
    {
        // Set id as key
        $arrSourcePages = $this->rebuildArray($arrSourcePages);
        $arrTargetPages = $this->rebuildArray($arrTargetPages);

        // Search for missing entries
        $arrKeysSource = array_keys($arrSourcePages);
        $arrKeysTarget = array_keys($arrTargetPages);

        $arrMissingClient = array_diff($arrKeysSource, $arrKeysTarget);
        $arrMissingServer = array_diff($arrKeysTarget, $arrKeysSource);

        $intDiffFounds = 0;

        foreach ($arrSourcePages as $intID => $mixValues)
        {
            if ($arrSourceHashes[$intID]['hash'] == $arrTargetHashes[$intID]['hash'])
            {
                continue;
            }

            $intDiffFounds++;
        }

        $intDiffFounds = $intDiffFounds + count($arrMissingServer);

        return $intDiffFounds;
    }

    /**
     * Get a list with ID's from diff.
     *
     * @param array $arrSourcePages
     * @param array $arrSourceHashes
     * @param array $arrTargetPages
     * @param array $arrTargetHashes
     *
     * @return int
     */
    protected function getIdDiffs($arrSourcePages, $arrSourceHashes, $arrTargetPages, $arrTargetHashes)
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
            if ($arrSourceHashes[$intID]['hash'] == $arrTargetHashes[$intID]['hash'])
            {
                continue;
            }

            $arrReturn['transfer'][] = $intID;
        }

        $arrReturn['delete'] = $arrMissingServer;

        return $arrReturn;
    }

    /**
     * Search the id and set it as key
     *
     * @param array $arrData
     * @return array
     */
    public function rebuildArray($arrData)
    {
        $arrReturn = array();

        foreach ($arrData as $arrValue)
        {
            if (array_key_exists('insert', $arrValue))
            {
                $arrReturn[$arrValue['insert']['id']] = $arrValue['insert'];
            }
            else
            {
                $arrReturn[$arrValue['id']] = $arrValue;
            }
        }

        return $arrReturn;
    }

    /**
     * Get a list with ignored fields for the sync
     *
     * @param string $strTable Name of table
     * @return array
     */
    protected function getIgnoredFieldsFor($strTable)
    {
        $arrReturn = array();

        // Get all Values
        if (array_key_exists('all', $GLOBALS['SYC_CONFIG']['sync_blacklist']))
        {
            $arrReturn = array_merge($arrReturn, $GLOBALS['SYC_CONFIG']['sync_blacklist']['all']);
        }

        // Get special Values
        if (array_key_exists($strTable, $GLOBALS['SYC_CONFIG']['sync_blacklist']))
        {
            $arrReturn = array_merge($arrReturn, $GLOBALS['SYC_CONFIG']['sync_blacklist'][$strTable]);
        }

        $arrUserSettings = array();
        foreach ((array) deserialize($GLOBALS['TL_CONFIG']['syncCto_sync_blacklist']) as $key => $value)
        {
            $arrUserSettings[$value['table']][] = $value['entry'];
        }

        // Get all Values
        if (array_key_exists('all', $arrUserSettings))
        {
            $arrReturn = array_merge($arrReturn, $arrUserSettings['all']);
        }

        // Get special Values
        if (array_key_exists($strTable, $arrUserSettings))
        {
            $arrReturn = array_merge($arrReturn, $arrUserSettings[$strTable]);
        }

        return array('all' => array_unique($arrReturn));
    }

    /**
     * Save all id's to session settings.
     *
     * @param array $arrData Array with insert and deletet ids.
     * @param string $strTable Name of table.
     */
    protected function saveIdToSession($arrData, $strTable)
    {
        // Run each field for transfer
        foreach ((array) $arrData['transfer'] as $intTransferId)
        {
            $this->arrSyncSettings['syncCtoPro_transfer'][$strTable][$intTransferId] = $intTransferId;
        }

        // Run each field for delete
        foreach ((array) $arrData['delete'] as $intDeleteId)
        {
            $this->arrSyncSettings['syncCtoPro_delete_client'][$strTable][$intDeleteId] = $intDeleteId;
        }

        $this->saveSyncSettings();
    }

}
