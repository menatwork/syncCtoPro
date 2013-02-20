<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2013 
 * @package    syncCto
 * @license    GNU/LGPL 
 * @filesource
 */
/**
 * Initialize the system
 */
define('TL_MODE', 'BE');
require_once('../system/initialize.php');

require_once TL_ROOT . '/plugins/phpdiff/Diff.php';
require_once TL_ROOT . '/plugins/phpdiff/Diff/Renderer/Html/Contao.php';

/**
 * Class SyncCtoPopup
 */
class PopupSyncDiff extends Backend
{
    ////////////////////////////////////////////////////////////////////////////
    // Const
    ////////////////////////////////////////////////////////////////////////////

    const VIEWMODE_OVERVIEW = 'overview';
    const VIEWMODE_DETAIL   = 'detail';

    ////////////////////////////////////////////////////////////////////////////
    // Objects
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @var SyncCtoCommunicationClient 
     */
    protected $objSyncCtoCommunicationClient;

    /**
     * @var SyncCtoProCommunicationClient 
     */
    protected $objSyncCtoProCommunicationClient;

    /**
     * @var SyncCtoProDatabase 
     */
    protected $objSyncCtoProDatabase;

    /**
     * @var BackendTemplate
     */
    protected $popupTemplate;

    ////////////////////////////////////////////////////////////////////////////
    // Vars
    ////////////////////////////////////////////////////////////////////////////

    /**
     * ID for the client.
     * 
     * @var integer 
     */
    protected $intClientID;

    /**
     * Direction To|From
     * 
     * @var string 
     */
    protected $strDirection;

    /**
     * Name of tabel for the check
     * 
     * @var string 
     */
    protected $strTable;

    /**
     * Id of the row
     * 
     * @var integer 
     */
    protected $intRowId;

    /**
     * Viewmode, overview or detail view
     * 
     * @var string 
     */
    protected $strViewMode = self::VIEWMODE_OVERVIEW;

    /**
     * Cleint settings
     * 
     * @var array 
     */
    protected $arrSyncSettings = array();

    /**
     * A list with all extern data
     * 
     * @var array 
     */
    protected $arrExternData;

    /**
     * A list with all local data
     * 
     * @var array 
     */
    protected $arrLocalData;

    /**
     * @var array 
     */
    protected $strContentData;

    /**
     * Flag to show if we have an error
     * 
     * @var boolean 
     */
    protected $blnError = false;

    /**
     * State for closing the lightbox/mediabox
     * 
     * @var boolean
     */
    protected $blnClose = false;

    /**
     * A list with all errors
     * 
     * @var array 
     */
    protected $arrError = array();

    ////////////////////////////////////////////////////////////////////////////
    // System
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Initialize the object
     */
    public function __construct()
    {
        // Imports
        $this->import('Input');
        $this->import('BackendUser', 'User');

        parent::__construct();

        // Check user auth
        $this->User->authenticate();

        // Set language from get or user
        if ($this->Input->get('language') != '')
        {
            $GLOBALS['TL_LANGUAGE'] = $this->Input->get('language');
        }
        else
        {
            $GLOBALS['TL_LANGUAGE'] = $this->User->language;
        }

        // Init Helper
        $this->objSyncCtoHelper                 = SyncCtoHelper::getInstance();
        $this->objSyncCtoCommunicationClient    = SyncCtoCommunicationClient::getInstance();
        $this->objSyncCtoProCommunicationClient = SyncCtoProCommunicationClient::getInstance();
        $this->objSyncCtoProDatabase            = SyncCtoProDatabase::getInstance();

        // Load all values from get param
        $this->initGetParams();

        // Load language files
        $this->loadLanguageFile('default');
        $this->loadLanguageFile('tl_page');
        $this->loadLanguageFile('tl_article');
        $this->loadLanguageFile('tl_content');
        $this->loadLanguageFile('tl_syncCtoPro_steps');
        $this->loadLanguageFile($this->strTable);

        // Basic Template
        $this->popupTemplate = new BackendTemplate('be_syncCtoPro_popup');
    }

    /**
     * Load the template list and go through the steps
     */
    public function run()
    {
        try
        {
            // Basic functions
            $this->loadSyncSettings();
            $this->initConnetcion();

            // Choose viewmode
            switch ($this->strViewMode)
            {
                // Overview page
                case self::VIEWMODE_OVERVIEW:
                    $this->renderOverview();
                    break;

                // Detail diff
                case self::VIEWMODE_DETAIL:
                    $this->loadExternDataFor($this->strTable, $this->intRowId);
                    $this->loadLocalDataFor($this->strTable, $this->intRowId);
                    $this->runDiff();
                    break;

                default:
                    $this->blnError   = true;
                    $this->arrError[] = 'Unknown viewmode.';
                    break;
            }

            $this->saveSyncSettings();
        }
        catch (Exception $exc)
        {
            $this->blnError   = true;
            $this->arrError[] = $exc->getMessage();
        }

        $this->output();
    }

    ////////////////////////////////////////////////////////////////////////////
    // Core
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Read get values
     */
    protected function initGetParams()
    {
        $this->intClientID  = $this->Input->get('id');
        $this->strDirection = $this->Input->get('direction');
        $this->strTable     = $this->Input->get('table');
        $this->intRowId     = $this->Input->get('row_id');
        $this->strViewMode  = $this->Input->get('view');
    }

    /**
     * Init connection
     */
    protected function initConnetcion()
    {
        $this->objSyncCtoCommunicationClient->setClientBy($this->intClientID);
    }

    /**
     * Init connection
     */
    protected function output()
    {
        // Set stylesheets
        $GLOBALS['TL_CSS'][] = TL_SCRIPT_URL . 'system/themes/' . $this->getTheme() . '/main.css';
        $GLOBALS['TL_CSS'][] = TL_SCRIPT_URL . 'system/themes/' . $this->getTheme() . '/basic.css';
        $GLOBALS['TL_CSS'][] = TL_SCRIPT_URL . 'system/themes/' . $this->getTheme() . '/popup.css';
        $GLOBALS['TL_CSS'][] = TL_SCRIPT_URL . 'system/modules/syncCto/html/css/compare.css';
        $GLOBALS['TL_CSS'][] = TL_SCRIPT_URL . 'system/modules/syncCtoPro/html/css/diff.css';

        // Set javascript
        $GLOBALS['TL_JAVASCRIPT'][] = TL_PLUGINS_URL . 'plugins/mootools/' . MOOTOOLS_CORE . '/mootools-core.js';
        $GLOBALS['TL_JAVASCRIPT'][] = 'contao/contao.js';
        $GLOBALS['TL_JAVASCRIPT'][] = TL_SCRIPT_URL . 'system/modules/syncCto/html/js/compare_src.js';

        // Template work
        $this->popupTemplate->theme    = $this->getTheme();
        $this->popupTemplate->base     = $this->Environment->base;
        $this->popupTemplate->path     = $this->Environment->path;
        $this->popupTemplate->language = $GLOBALS['TL_LANGUAGE'];
        $this->popupTemplate->title    = $GLOBALS['TL_CONFIG']['websiteTitle'];
        $this->popupTemplate->charset  = $GLOBALS['TL_CONFIG']['characterSet'];
        $this->popupTemplate->headline = basename(utf8_convert_encoding($this->strFile, $GLOBALS['TL_CONFIG']['characterSet']));

        $this->popupTemplate->close    = $this->blnClose;
        $this->popupTemplate->error    = $this->blnError;
        $this->popupTemplate->arrError = $this->arrError;

        $this->popupTemplate->content = $this->strContentData;

        $this->popupTemplate->output();
    }

    ////////////////////////////////////////////////////////////////////////////
    // View - Overview
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Create the overview
     * 
     * @return void
     */
    protected function renderOverview()
    {
        // Get IDs
        $arrTransferIds     = $this->Input->post('transfer_ids');
        $arrDeleteClientIds = $this->Input->post('delete_client_ids');
        $arrDeleteIds       = $this->Input->post('delete_ids');

        // Submit with fields
        if (key_exists("transfer", $_POST) && !(empty($arrTransferIds) && empty($arrDeleteClientIds)))
        {
            // Run each field for transfer         
            foreach ((array) $arrTransferIds as $mixTransferId)
            {
                $arrTransferId = trimsplit("::", $mixTransferId);

                $this->arrSyncSettings['syncCtoPro_transfer'][$arrTransferId[0]][$arrTransferId[1]] = $arrTransferId[1];
            }

            // Run each field for delete         
            foreach ((array) $arrDeleteClientIds as $mixDeleteId)
            {
                $arrDeleteId = trimsplit("::", $mixDeleteId);

                $this->arrSyncSettings['syncCtoPro_delete_client'][$arrDeleteId[0]][$arrDeleteId[1]] = $arrDeleteId[1];
            }

            $this->blnClose = true;
            return;
        }
        // Submit without values
        else if (key_exists("transfer", $_POST) && empty($arrTransferIds) && empty($arrDeleteClientIds))
        {
            $this->arrSyncSettings['syncCtoPro_transfer'] = array();

            $this->blnClose = true;
            return;
        }
        // Submit for delete
        else if (key_exists("delete", $_POST) && !empty($arrDeleteIds))
        {
            // Run each field            
            foreach ($arrDeleteIds as $mixDeleteId)
            {
                $mixDeleteId = trimsplit("::", $mixDeleteId);

                $this->arrSyncSettings['syncCtoPro_delete'][$mixDeleteId[0]][$mixDeleteId[1]] = $mixDeleteId[1];
            }
        }

        // Get all data
        $arrAllPageValues = $this->renderElementsPart('tl_page', array('title', 'id', 'pid', 'sorting'));
        $arrAllArticleValues = $this->renderElementsPart('tl_article', array('title', 'id', 'sorting', 'pid'));
        $arrAllContentValues = $this->renderElementsPart('tl_content', array('type', 'id', 'sorting', 'pid'));

        // Sorting 
        uasort($arrAllPageValues, array($this, 'sortByPid'));
        uasort($arrAllArticleValues, array($this, 'sortByPid'));
        uasort($arrAllContentValues, array($this, 'sortByPid'));

        $arrArticleNeeded = array();
        $arrPageNeeded = array();

        $arrAllowedTables = $this->arrSyncSettings['syncCtoPro_tables_checked'];

        // Clean up content
        foreach ($arrAllContentValues as $key => $value)
        {
            if (in_array($value['state'], array('same', 'ignored')))
            {
                unset($arrAllContentValues[$key]);
                continue;
            }

            if (!in_array('tl_content', $arrAllowedTables))
            {
                unset($arrAllContentValues[$key]);
                continue;
            }

            $arrArticleNeeded[$value['pid']] = true;
        }


        // Clean up article
        foreach ($arrAllArticleValues as $key => $value)
        {
            if (in_array($value['state'], array('same', 'ignored')) && !key_exists($value['id'], $arrArticleNeeded))
            {
                unset($arrAllArticleValues[$key]);
                continue;
            }

            if (!in_array('tl_article', $arrAllowedTables) && !key_exists($value['id'], $arrArticleNeeded))
            {
                unset($arrAllArticleValues[$key]);
                continue;
            }

            $arrPageNeeded[$value['pid']] = true;
        }

        // Clean up pages
        foreach ($arrAllPageValues as $key => $value)
        {
            if (in_array($value['state'], array('same', 'ignored')) && !key_exists($value['id'], $arrPageNeeded))
            {
                unset($arrAllPageValues[$key]);
                continue;
            }

            if (!in_array('tl_page', $arrAllowedTables) && !key_exists($value['id'], $arrPageNeeded))
            {
                unset($arrAllPageValues[$key]);
                continue;
            }
        }

        // No data so skip
        if (empty($arrAllPageValues) && empty($arrAllArticleValues) && empty($arrAllContentValues))
        {
            $this->blnClose = true;
            return;
        }

        // Template
        $objOverviewTemplate = new BackendTemplate('be_syncCtoPro_popup_overview');

        $objOverviewTemplate->arrAllPageValues    = $arrAllPageValues;
        $objOverviewTemplate->arrAllArticleValues = $arrAllArticleValues;
        $objOverviewTemplate->arrAllContentValues = $arrAllContentValues;
        $objOverviewTemplate->arrAllowedTables    = $arrAllowedTables;
        $objOverviewTemplate->base                = $this->Environment->base;
        $objOverviewTemplate->path                = $this->Environment->path;
        $objOverviewTemplate->id                  = $this->intClientID;
        $objOverviewTemplate->direction           = $this->strDirection;
        $objOverviewTemplate->headline            = $GLOBALS['TL_LANG']['MSC']['show_differences'];
        $objOverviewTemplate->forwardValue        = $GLOBALS['TL_LANG']['MSC']['apply'];
        $objOverviewTemplate->helperClass         = $this;

        $this->strContentData = $objOverviewTemplate->parse();
    }

    protected function renderElementsPart($strTable, $arrFields)
    {
        // Get all data / load helper
        $arrFilePathes = $this->arrSyncSettings['syncCtoPro_ExternFile'];

        // Read client pages
        $arrClientElement       = $this->objSyncCtoProDatabase->readXML($arrFilePathes[$strTable]);
        $arrClientElementHashes = $this->objSyncCtoProCommunicationClient->getHashValueFor($strTable);

        // Get server Pages
        $arrElement = $this->Database
                ->query('SELECT ' . implode(", ", $arrFields) . ' FROM ' . $strTable . ' ORDER BY pid, id')
                ->fetchAllAssoc();

        $arrElementHashes = $this->objSyncCtoProDatabase->getHashValueFor($strTable, array());

        if (key_exists($strTable, (array) $this->arrSyncSettings['syncCtoPro_delete']))
        {
            return $this->buildTree($arrElement, $arrElementHashes, $arrClientElement['data'], $arrClientElementHashes, (array) $this->arrSyncSettings['syncCtoPro_delete'][$strTable]);
        }
        else
        {
            return $this->buildTree($arrElement, $arrElementHashes, $arrClientElement['data'], $arrClientElementHashes, array());
        }
    }

    ////////////////////////////////////////////////////////////////////////////
    // View - Detail
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Load a list with id/titles from client
     */
    protected function loadExternDataFor($strTable, $intID)
    {
        $strExportFile = $this->objSyncCtoProCommunicationClient->exportDatabaseSE('', $strTable, array($intID));

        // Check if we have all files
        if ($strExportFile === false)
        {
            throw new Exception('Missing export file for tl_page');
        }

        //Load the se export files from client
        $strSavePath = $this->objSyncCtoHelper->standardizePath($GLOBALS['SYC_PATH']['tmp'], basename($strExportFile));

        $blnResponse = $this->objSyncCtoProCommunicationClient->getFile($strExportFile, $strSavePath);

        // Check if we have the file
        if (!$blnResponse)
        {
            throw new Exception("Empty file list from client. Maybe file sending was not complet for $strExportFile.");
        }

        // Read client pages
        $this->arrExternData = $this->objSyncCtoProDatabase->readXML($strSavePath);
    }

    /**
     * Load local data
     * 
     * @param string $strTable
     * @param integer $intID
     */
    protected function loadLocalDataFor($strTable, $intID)
    {
        $this->arrLocalData = $this->Database->prepare("SELECT * FROM $strTable WHERE id = ?")
                ->executeUncached($intID)
                ->fetchAllAssoc();
    }

    /**
     * Search for differences and generate the 
     * detail page.
     */
    protected function runDiff()
    {
        $strContent = "";

        // Diff Options
        $arrDiffOptions = array(
            'ignoreWhitespace' => true,
            'ignoreCase'       => true,
        );

        // Get ignored fields
        $arrFilterFields = $this->getIgnoredFieldsFor($this->strTable);

        // Load fields
        $this->loadDataContainer($this->strTable);
        $arrDcaFields = $GLOBALS['TL_DCA'][$this->strTable]['fields'];

        if (empty($this->arrLocalData))
        {
            $arrExternData = array();
            $arrLocalData = $this->arrExternData['data'][0]['insert'];
        }
        else
        {
            $arrLocalData  = $this->arrLocalData[0];
            $arrExternData = $this->arrExternData['data'][0]['insert'];
        }

        $arrDataForDiff = array();

        // Check data an make something with it
        foreach ($arrLocalData as $strField => $mixValue)
        {
            // Check if the field is in diff blacklist for all
            if (in_array($strField, $arrFilterFields))
            {
                continue;
            }

            // Get current values
            $strCurrentFieldSettings = $arrDcaFields[$strField];

            $mixValuesServer = $mixValue;
            $mixValuesClient = $arrExternData[$strField];

            // Check if we have a difference
            if ($mixValuesServer == $mixValuesClient)
            {
                continue;
            }

            // Hidden conditions
            if ($strCurrentFieldSettings['inputType'] == 'password' || $strCurrentFieldSettings['eval']['doNotShow'] || $strCurrentFieldSettings['eval']['hideInput'])
            {
                continue;
            }

            // Convert serialized arrays into strings
            if (is_array(($tmp = deserialize($mixValuesServer))) && !is_array($mixValuesServer))
            {
                $mixValuesServer                              = $this->implode($tmp);
                $arrDataForDiff[$strField]['server']['array'] = true;
            }
            if (is_array(($tmp                                          = deserialize($mixValuesClient))) && !is_array($mixValuesClient))
            {
                $mixValuesClient                              = $this->implode($tmp);
                $arrDataForDiff[$strField]['client']['array'] = true;
            }
            unset($tmp);

            // Convert date fields
            if ($strCurrentFieldSettings['eval']['rgxp'] == 'date')
            {
                $mixValuesServer = $this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], $mixValuesServer ? : '');
                $mixValuesClient = $this->parseDate($GLOBALS['TL_CONFIG']['dateFormat'], $mixValuesClient ? : '');
            }
            elseif ($strCurrentFieldSettings['eval']['rgxp'] == 'time')
            {
                $mixValuesServer = $this->parseDate($GLOBALS['TL_CONFIG']['timeFormat'], $mixValuesServer ? : '');
                $mixValuesClient = $this->parseDate($GLOBALS['TL_CONFIG']['timeFormat'], $mixValuesClient ? : '');
            }
            elseif ($strCurrentFieldSettings['eval']['rgxp'] == 'datim')
            {
                $mixValuesServer = $this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], $mixValuesServer ? : '');
                $mixValuesClient = $this->parseDate($GLOBALS['TL_CONFIG']['datimFormat'], $mixValuesClient ? : '');
            }
            
            // Save for later operations
            $arrDataForDiff[$strField]['server']['data'] = $mixValuesServer;
            $arrDataForDiff[$strField]['client']['data'] = $mixValuesClient;
        }
        
        // Get the last key
        $arrLastKeys = array_keys($arrDataForDiff);
        $arrLastKeys = array_pop($arrLastKeys);

        // Check each field a make if diff if not empty
        foreach ($arrDataForDiff as $strField => $arrValues)
        {
            // only check array if we have enough other entries
            if ($strContent != '' && $strField != $arrLastKeys)
            {
                // Check for empty data
                if ($arrValues['server']['array'] === true)
                {
                    $strReplaceTest = trim(str_replace(',', '', $arrValues['server']['data']));
                    if (empty($strReplaceTest))
                    {
                        $arrValues['server']['data'] = '';
                    }
                }

                if ($arrValues['client']['array'] === true)
                {
                    $strReplaceTest = trim(str_replace(',', '', $arrValues['client']['data']));
                    if (empty($strReplaceTest))
                    {
                        $arrValues['client']['data'] = '';
                    }
                }
            }

            if (empty($arrValues['server']['data']) == true && empty($arrValues['client']['data']) == true)
            {
                continue;
            }

            // Get field name
            if (empty($GLOBALS['TL_LANG'][$this->strTable][$strField]))
            {
                $strHumanReadableField = $strField;
            }
            else if (is_array($GLOBALS['TL_LANG'][$this->strTable][$strField]))
            {
                $strHumanReadableField = $GLOBALS['TL_LANG'][$this->strTable][$strField][0];
            }
            else
            {
                $strHumanReadableField = $GLOBALS['TL_LANG'][$this->strTable][$strField];
            }

            // Convert strings into arrays
            if (!is_array($mixValuesServer))
            {
//                $mixValuesServer = explode("\n\t", strip_tags($mixValuesServer));
                $arrValues['server']['data'] = (array) strip_tags($arrValues['server']['data']);
            }
            if (!is_array($mixValuesClient))
            {
//                $mixValuesClient = explode("\n\t", strip_tags($mixValuesClient));
                $arrValues['client']['data'] = (array) strip_tags($arrValues['client']['data']);
            }

            // Run php-diff
            if (empty($this->arrLocalData))
            {
                $objDiff = new Diff($arrValues['server']['data'], $arrValues['client']['data'], $arrDiffOptions);
            }
            else
            {
                $objDiff = new Diff($arrValues['client']['data'], $arrValues['server']['data'], $arrDiffOptions);
            }

            $objRenderer = new Diff_Renderer_Html_Contao();
            $objRenderer->setOptions(array('field' => $strHumanReadableField));

            $mixResult = $objDiff->Render($objRenderer);

            $strContent .= $mixResult;
        }

        // Load CSS
        $GLOBALS['TL_JAVASCRIPT'][] = TL_SCRIPT_URL . 'system/modules/syncCtoPro/html/css/diff.css';

        // Set wrapper template information
        $objDetailsTemplate = new BackendTemplate("be_syncCtoPro_popup_detail");

        $objDetailsTemplate->base      = $this->Environment->base;
        $objDetailsTemplate->path      = $this->Environment->path;
        $objDetailsTemplate->id        = $this->intClientID;
        $objDetailsTemplate->direction = $this->strDirection;

        $objDetailsTemplate->content  = $strContent;
        $objDetailsTemplate->headline = vsprintf($GLOBALS['TL_LANG']['tl_syncCtoPro_steps']['popup']['headline_detail'], array($this->strTable, $this->intRowId));

        $this->strContentData = $objDetailsTemplate->parse();
    }

    ////////////////////////////////////////////////////////////////////////////
    // Helper
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Implode a multi-dimensional array recursively
     * @author Leo Feyer https://contao.org
     * @param mixed
     * @return string
     */
    protected function implode($var)
    {
        if (!is_array($var))
        {
            return $var;
        }
        elseif (!is_array(next($var)))
        {
            return implode(', ', $var);
        }
        else
        {
            $buffer = '';

            foreach ($var as $k => $v)
            {
                $buffer .= $k . ": " . $this->implode($v) . "\n";
            }

            return trim($buffer);
        }
    }

    /**
     * Load the sync settings for a client
     */
    protected function loadSyncSettings()
    {
        $this->arrSyncSettings = $this->Session->get("syncCto_SyncSettings_" . $this->intClientID);

        if (!is_array($this->arrSyncSettings))
        {
            $this->arrSyncSettings = array();
        }
    }

    /**
     * Save the sync settings for a client
     */
    protected function saveSyncSettings()
    {
        if (!is_array($this->arrSyncSettings))
        {
            $this->arrSyncSettings = array();
        }

        $this->Session->set("syncCto_SyncSettings_" . $this->intClientID, $this->arrSyncSettings);
    }

    public function rebuildArray($arrData)
    {
        $arrReturn = array();
        foreach ($arrData as $arrValue)
        {
            if (key_exists('insert', $arrValue))
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
     * 
     * @param array $arrSourcePages
     * @param array $arrSourceHashes
     * @param array $arrTargetPages
     * @param array $arrTargetHashes
     */
    protected function buildTree($arrSourcePages, $arrSourceHashes, $arrTargetPages, $arrTargetHashes, $arrIgnoredIds = array())
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
            // Set ID
            $arrReturn[$intID]['id']     = $intID;
            $arrReturn[$intID]['delete'] = false;

            // Set pid
            if (key_exists('pid', $mixValues))
            {
                $arrReturn[$intID]['pid'] = $mixValues['pid'];
            }

            // Set sorting
            if (key_exists('sorting', $mixValues))
            {
                $arrReturn[$intID]['sorting'] = $mixValues['sorting'];
            }

            // Check Hash
            if ($arrSourceHashes[$intID]['hash'] == $arrTargetHashes[$intID]['hash'])
            {
                $arrReturn[$intID]['state'] = 'same';
            }
            else
            {
                $arrReturn[$intID]['state'] = 'diff';
            }

            // Set all other informations
            if (key_exists($intID, $arrTargetPages) && key_exists($intID, $arrTargetHashes))
            {
                $arrReturn[$intID]['source'] = array_merge($mixValues, $arrSourceHashes[$intID]);
                $arrReturn[$intID]['target'] = array_merge($arrTargetPages[$intID], $arrTargetHashes[$intID]);
            }
            else if (key_exists($intID, $arrTargetPages) && !key_exists($intID, $arrTargetHashes))
            {
                $arrReturn[$intID]['source'] = array_merge($mixValues, $arrSourceHashes[$intID]);
                $arrReturn[$intID]['target'] = $arrTargetPages[$intID];
            }
            else if (!key_exists($intID, $arrTargetPages) && key_exists($intID, $arrTargetHashes))
            {
                $arrReturn[$intID]['source'] = array_merge($mixValues, $arrSourceHashes[$intID]);
                $arrReturn[$intID]['target'] = $arrTargetHashes[$intID];
            }
            else
            {
                $arrReturn[$intID]['source'] = array_merge($mixValues, $arrSourceHashes[$intID]);
                $arrReturn[$intID]['target'] = array();
            }

            // Set state ignored if in list
            if (in_array($intID, $arrIgnoredIds))
            {
                $arrReturn[$intID]['state'] = 'ignored';
            }
        }

        foreach ($arrMissingServer as $intID)
        {
            $arrReturn[$intID] = array(
                'id'     => $intID,
                'pid'    => $arrTargetPages[$intID]['pid'],
                'state'  => 'diff',
                'delete' => true,
                'source' => array(),
                'target' => array_merge($arrTargetPages[$intID], $arrTargetHashes[$intID])
            );

            if (in_array($intID, $arrIgnoredIds))
            {
                $arrReturn[$intID]['state'] = 'ignored';
            }
        }



        return $arrReturn;
    }

    /**
     * Get a list with ignored fields for the hashes
     * 
     * @param string $strTable Name of table
     * @return array
     */
    protected function getIgnoredFieldsFor($strTable)
    {
        $arrReturn = array();

        // Get all Values
        if (key_exists('all', $GLOBALS['SYC_CONFIG']['trigger_blacklist']))
        {
            $arrReturn = array_merge($arrReturn, $GLOBALS['SYC_CONFIG']['trigger_blacklist']['all']);
        }

        // Get special Values
        if (key_exists($strTable, $GLOBALS['SYC_CONFIG']['trigger_blacklist']))
        {
            $arrReturn = array_merge($arrReturn, $GLOBALS['SYC_CONFIG']['trigger_blacklist'][$strTable]);
        }

        $arrUserSettings = array();
        foreach ((array) deserialize($GLOBALS['TL_CONFIG']['syncCto_hash_blacklist']) as $key => $value)
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

        return array_unique($arrReturn);
    }

    /**
     * Sorting array
     * 
     * @param array $a
     * @param array $b
     * @return int
     */
    public function sortByPid($a, $b)
    {
        // Pid + Sorting support
        if (key_exists('sorting', $a) && key_exists('sorting', $b) && key_exists('pid', $a) && key_exists('pid', $b))
        {
            if ($a['pid'] == $b['pid'])
            {
                if ($a['sorting'] == $b['sorting'])
                {
                    return 0;
                }

                return ($a['sorting'] < $b['sorting']) ? -1 : 1;
            }

            return ($a['pid'] < $b['pid']) ? -1 : 1;
        }
        // Pid support
        else if (key_exists('pid', $a) && key_exists('pid', $b))
        {
            if ($a['pid'] == $b['pid'])
            {
                if ($a['id'] == $b['id'])
                {
                    return 0;
                }

                return ($a['id'] < $b['id']) ? -1 : 1;
            }

            return ($a['pid'] < $b['pid']) ? -1 : 1;
        }
        // ID only
        else
        {
            if ($a['id'] == $b['id'])
            {
                return 0;
            }

            return ($a['id'] < $b['id']) ? -1 : 1;
        }
    }

}

/**
 * Instantiate controller
 */
$objPopup = new PopupSyncDiff();
$objPopup->run();
?>