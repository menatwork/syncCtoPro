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
        $arrIds = $this->Input->post('ids');

        // Check if we have a submit
        if (key_exists("forward", $_POST) && !empty($arrIds))
        {
            $this->arrSyncSettings['syncCtoPro_transfer']['tl_page'] = $arrIds;

            $this->blnClose = true;
            return;
        }
        else if ((key_exists("forward", $_POST) && empty($arrIds)) || key_exists("skip", $_POST))
        {
            $this->arrSyncSettings['syncCtoPro_transfer'] = array();

            $this->blnClose = true;
            return;
        }

        $arrAllPageValues = $this->renderEmelemtsPart('tl_page', array('title', 'id', 'pid'));
        $arrAllArticleValues = $this->renderEmelemtsPart('tl_article', array('title', 'id', 'pid'));
        $arrAllContentValues = $this->renderEmelemtsPart('tl_content', array('type', 'id', 'pid'));
        
        // Template
        $objOverviewTemplate = new BackendTemplate('be_syncCtoPro_popup_overview');

        $objOverviewTemplate->arrAllPageValues    = $arrAllPageValues;
        $objOverviewTemplate->arrAllArticleValues = $arrAllArticleValues;
        $objOverviewTemplate->arrAllContentValues = $arrAllContentValues;
        $objOverviewTemplate->base                = $this->Environment->base;
        $objOverviewTemplate->path                = $this->Environment->path;
        $objOverviewTemplate->id                  = $this->intClientID;
        $objOverviewTemplate->direction           = $this->strDirection;
        $objOverviewTemplate->headline            = $GLOBALS['TL_LANG']['MSC']['show_differences'];
        $objOverviewTemplate->forwardValue        = $GLOBALS['TL_LANG']['MSC']['apply'];
        $objOverviewTemplate->helperClass         = $this;

        $this->strContentData = $objOverviewTemplate->parse();
    }

    protected function renderEmelemtsPart($strTable, $arrFields)
    {
        // Get all data / load helper
        $arrFilePathes = $this->arrSyncSettings['syncCtoPro_ExternFile'];

        // Read client pages
        $arrClientElement       = $this->objSyncCtoProDatabase->readXML($arrFilePathes[$strTable]);
        $arrClientElementHashes = $this->objSyncCtoProCommunicationClient->getHashValueFor($strTable);
        
        // Get server Pages
        $arrtElement = $this->Database
                ->query('SELECT ' . implode(", ", $arrFields) . ' FROM ' . $strTable . ' ORDER BY pid, id')
                ->fetchAllAssoc();

        $arrtElementHashes = $this->objSyncCtoProDatabase->getHashValueFor($strTable, array());
        
        return $this->buildTree($arrtElement, $arrtElementHashes, $arrClientElement['data'], $arrClientElementHashes);
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

    protected function runDiff()
    {
        $strContent = "";

        // Diff Options
        $arrDiffOptions = array(
                //'ignoreWhitespace' => true,
                //'ignoreCase' => true,
        );

        // Load fields
        $this->loadDataContainer($this->strTable);
        $arrDcaFields = $GLOBALS['TL_DCA'][$this->strTable]['fields'];

        foreach ($this->arrLocalData[0] as $strField => $mixValue)
        {
            // Get current values
            $strCurrentFieldSettings = $arrDcaFields[$strField];

            $mixValuesServer = $mixValue;
            $mixValuesClient = $this->arrExternData['data'][0][$strField];

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
                $mixValuesServer = $this->implode($tmp);
            }
            if (is_array(($tmp             = deserialize($mixValuesClient))) && !is_array($mixValuesClient))
            {
                $mixValuesClient = $this->implode($tmp);
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

            // Convert strings into arrays
            if (!is_array($mixValuesServer))
            {
                $mixValuesServer = explode("\n", $mixValuesServer);
            }
            if (!is_array($mixValuesClient))
            {
                $mixValuesClient = explode("\n", $mixValuesClient);
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

            // Run php-diff
            $objDiff = new Diff($mixValuesClient, $mixValuesServer, $arrDiffOptions);

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

        $objDetailsTemplate->content = $strContent;
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
            $arrReturn[$arrValue['id']] = $arrValue;
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
            if ($arrSourceHashes[$intID]['hash'] == $arrTargetHashes[$intID]['hash'])
            {
                continue;
            }

            $arrReturn[$intID] = array(
                'id'     => $intID,
                'source' => array_merge($mixValues, $arrSourceHashes[$intID]),
                'target' => array_merge($arrTargetPages[$intID], $arrTargetHashes[$intID])
            );
        }

        foreach ($arrMissingServer as $intID)
        {
            $arrReturn[$intID] = array(
                'id'     => $intID,
                'source' => array(),
                'target' => array_merge($arrTargetPages[$intID], $arrTargetHashes[$intID])
            );
        }

        return $arrReturn;
    }

}

/**
 * Instantiate controller
 */
$objPopup = new PopupSyncDiff();
$objPopup->run();
?>