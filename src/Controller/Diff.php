<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2013
 * @package    syncCto Pro
 * @license    EULA
 * @filesource
 */

namespace MenAtWork\SyncCtoPro\Controller;

use Contao\Backend;
use Contao\BackendTemplate;
use Contao\BackendUser;
use Contao\Environment;
use Contao\Input;
use Contao\ModuleLoader;
use Contao\Session;
use Contao\System;
use Diff_Renderer_Html_Contao;
use SyncCtoCommunicationClient;
use SyncCtoHelper;
use SyncCtoProCommunicationClient;
use SyncCtoProDatabase;

/**
 * Class SyncCtoPopup
 */
class Diff
{
    ////////////////////////////////////////////////////////////////////////////
    // Const
    ////////////////////////////////////////////////////////////////////////////

    const VIEWMODE_OVERVIEW = 'overview';
    const VIEWMODE_DETAIL   = 'detail';
    const VIEWMODE_ALL      = 'all';

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
     * Load the template list and go through the steps
     */
    public function runAction()
    {
        // Check user auth
        BackendUser::getInstance()->authenticate();

        // Set language from get or user
        if (Input::get('language') != '') {
            $GLOBALS['TL_LANGUAGE'] = \Input::get('language');
        } else {
            $GLOBALS['TL_LANGUAGE'] = BackendUser::getInstance()->language;
        }

        // Init Helper
        $this->objSyncCtoHelper                 = SyncCtoHelper::getInstance();
        $this->objSyncCtoCommunicationClient    = SyncCtoCommunicationClient::getInstance();
        $this->objSyncCtoProCommunicationClient = SyncCtoProCommunicationClient::getInstance();
        $this->objSyncCtoProDatabase            = SyncCtoProDatabase::getInstance();

        // Load all values from get param
        $this->initGetParams();

        // Load language files
        System::loadLanguageFile('default');
        System::loadLanguageFile("modules");
        System::loadLanguageFile('tl_page');
        System::loadLanguageFile('tl_article');
        System::loadLanguageFile('tl_content');
        System::loadLanguageFile("tl_syncCto_database");
        System::loadLanguageFile('tl_syncCtoPro_steps');
        if(!empty($this->strTable)){
            System::loadLanguageFile($this->strTable);
        }

        // Run.
        if (\Input::post('showall') == self::VIEWMODE_ALL) {
            $this->strViewMode = self::VIEWMODE_ALL;
        }

        try {
            // Basic functions
            $this->loadSyncSettings();
            $this->initConnetcion();

            // Choose viewmode
            switch ($this->strViewMode) {
                // Overview page
                case self::VIEWMODE_OVERVIEW:
                    $this->renderOverview();
                    break;

                // Detail diff
                case self::VIEWMODE_DETAIL:
                    $arrExtData = $this->loadExternDataFor($this->strTable, $this->intRowId);
                    $arrLocData = $this->loadLocalDataFor($this->strTable, $this->intRowId);

                    if (empty($arrLocData)) {
                        $arrLocalData  = array();
                        $arrExternData = $arrExtData['data'][0]['insert'];
                    } else {
                        $arrLocalData  = $arrLocData[0];
                        $arrExternData = $arrExtData['data'][0]['insert'];
                    }

                    $this->runDiff($arrLocalData, $arrExternData);
                    break;

                // Detail diff for all entries
                case self::VIEWMODE_ALL:
                    $this->runAllDiff();
                    break;

                default:
                    $this->blnError   = true;
                    $this->arrError[] = 'Unknown viewmode.';
                    break;
            }

            $this->saveSyncSettings();
        } catch (\Exception $exc) {
            $this->blnError   = true;
            $this->arrError[] = $exc->getMessage();
        }

        return $this->getResponse();
    }

    /**
     * Output templates
     */
    public function getResponse()
    {
        // Clear all we want a clear array for this windows.
        $GLOBALS['TL_CSS']        = [];
        $GLOBALS['TL_JAVASCRIPT'] = [];

        // Set stylesheets
        $GLOBALS['TL_CSS'][] = 'system/themes/' . Backend::getTheme() . '/basic.css';
        $GLOBALS['TL_CSS'][] = 'bundles/synccto/css/compare.css';
        $GLOBALS['TL_CSS'][] = 'bundles/syncctopro/css/diff.css';

        // Set javascript
        $GLOBALS['TL_JAVASCRIPT'][] = 'assets/mootools/js/mootools-core.min.js';
        $GLOBALS['TL_JAVASCRIPT'][] = 'assets/mootools/js/mootools-more.min.js';
        $GLOBALS['TL_JAVASCRIPT'][] = 'bundles/synccto/js/compare.js';

        // Set wrapper template information
        $this->popupTemplate           = new BackendTemplate('be_syncCtoPro_popup');
        $this->popupTemplate->theme    = Backend::getTheme();
        $this->popupTemplate->base     = Environment::get('base');
        $this->popupTemplate->language = $GLOBALS['TL_LANGUAGE'];
        $this->popupTemplate->title    = $GLOBALS['TL_CONFIG']['websiteTitle'];
        $this->popupTemplate->charset  = $GLOBALS['TL_CONFIG']['characterSet'];
        $this->popupTemplate->headline = basename(utf8_convert_encoding($this->strFile,
            $GLOBALS['TL_CONFIG']['characterSet']));


        // Set default information
        $this->popupTemplate->close    = $this->blnClose;
        $this->popupTemplate->error    = $this->blnError;
        $this->popupTemplate->arrError = $this->arrError;

        // Output template
        $this->popupTemplate->content = $this->strContentData;

        return $this->popupTemplate->getResponse();
    }

    ////////////////////////////////////////////////////////////////////////////
    // Core
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Read get values
     */
    protected function initGetParams()
    {
        $this->intClientID  = \Input::get('id');
        $this->strDirection = \Input::get('direction');
        $this->strTable     = \Input::get('table');
        $this->intRowId     = \Input::get('row_id');
        $this->strViewMode  = \Input::get('view');
    }

    /**
     * Init connection
     */
    protected function initConnetcion()
    {
        $this->objSyncCtoCommunicationClient->setClientBy($this->intClientID);
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
        $arrTransferIds     = \Input::post('transfer_ids');
        $arrDeleteClientIds = \Input::post('delete_client_ids');
        $arrDeleteIds       = \Input::post('delete_ids');

        // Submit with fields
        if (array_key_exists("transfer", $_POST) && !(empty($arrTransferIds) && empty($arrDeleteClientIds))) {
            // Run each field for transfer
            foreach ((array)$arrTransferIds as $mixTransferId) {
                $arrTransferId = explode("::", $mixTransferId);

                $this->arrSyncSettings['syncCtoPro_transfer'][$arrTransferId[0]][$arrTransferId[1]] = $arrTransferId[1];
            }

            // Run each field for delete
            foreach ((array)$arrDeleteClientIds as $mixDeleteId) {
                $arrDeleteId = explode("::", $mixDeleteId);

                $this->arrSyncSettings['syncCtoPro_delete_client'][$arrDeleteId[0]][$arrDeleteId[1]] = $arrDeleteId[1];
            }

            $this->blnClose = true;

            return;
        } // Submit without values
        elseif (array_key_exists("transfer", $_POST) && empty($arrTransferIds) && empty($arrDeleteClientIds)) {
            $this->arrSyncSettings['syncCtoPro_transfer'] = array();

            $this->blnClose = true;

            return;
        } // Submit for delete
        elseif (array_key_exists("delete", $_POST) && !empty($arrDeleteIds)) {
            // Run each field
            foreach ($arrDeleteIds as $mixDeleteId) {
                $mixDeleteId = trimsplit("::", $mixDeleteId);

                $this->arrSyncSettings['syncCtoPro_delete'][$mixDeleteId[0]][$mixDeleteId[1]] = $mixDeleteId[1];
            }
        }

        // Get all data
        $arrAllPageValues     = $this->renderElementsPart('tl_page',
            array('title', 'id', 'pid', 'sorting', 'published', 'start', 'stop', 'type', 'hide', 'protected'));
        $arrAllArticleValues  = $this->renderElementsPart('tl_article', array('title', 'id', 'sorting', 'pid'));
        $arrAllContentValues  = $this->renderElementsPart('tl_content',
            array('type', 'id', 'sorting', 'pid', 'ptable'));
        $arrAdditionalContent = array();

        // Get the events and news content
        foreach ($arrAllContentValues as $strKey => $arrContent) {
            $strSourcePTable = $arrContent['source']['ptable'];
            $strTargetPTable = $arrContent['target']['ptable'];

            if ($strSourcePTable != '' && $strSourcePTable != 'tl_article') {
                $arrAdditionalContent[$strSourcePTable][] = $arrContent;
                unset($arrAllContentValues[$strKey]);
                continue;
            }

            if ($strTargetPTable != '' && $strTargetPTable != 'tl_article') {
                $arrAdditionalContent[$strTargetPTable][] = $arrContent;
                unset($arrAllContentValues[$strKey]);
                continue;
            }
        }

        // Sorting
        uasort($arrAllPageValues, array($this, 'sortByPid'));
        uasort($arrAllArticleValues, array($this, 'sortByPid'));
        uasort($arrAllContentValues, array($this, 'sortByPid'));

        $arrArticleNeeded = array();
        $arrPageNeeded    = array();

        $arrAllowedTables = $this->arrSyncSettings['syncCtoPro_tables_checked'];

        // Clean up content
        foreach ($arrAllContentValues as $key => $value) {
            if (in_array($value['state'], array('same', 'ignored'))) {
                unset($arrAllContentValues[$key]);
                continue;
            }

            if (!in_array('tl_content', $arrAllowedTables)) {
                unset($arrAllContentValues[$key]);
                continue;
            }

            $arrArticleNeeded[$value['pid']] = true;
        }

        // Clean up additional content e.g. news, events
        foreach ($arrAdditionalContent as $strTable => $arrContentData) {
            foreach ($arrContentData as $key => $value) {
                if (in_array($value['state'], array('same', 'ignored'))) {
                    unset($arrAdditionalContent[$strTable][$key]);
                    continue;
                }

                if (!in_array('tl_content', $arrAllowedTables)) {
                    unset($arrAdditionalContent[$strTable][$key]);
                    continue;
                }
            }

            // If empty remove it from array.
            if (empty($arrAdditionalContent[$strTable])) {
                unset($arrAdditionalContent[$strTable]);
            }
        }

        // Clean up article
        foreach ($arrAllArticleValues as $key => $value) {
            if (in_array($value['state'], array('same', 'ignored')) && !array_key_exists($value['id'],
                    $arrArticleNeeded)) {
                unset($arrAllArticleValues[$key]);
                continue;
            }

            if (!in_array('tl_article', $arrAllowedTables) && !array_key_exists($value['id'], $arrArticleNeeded)) {
                unset($arrAllArticleValues[$key]);
                continue;
            }

            $arrPageNeeded[$value['pid']] = true;
        }

        // Clean up pages
        foreach ($arrAllPageValues as $key => $value) {
            if (in_array($value['state'], array('same', 'ignored')) && !array_key_exists($value['id'],
                    $arrPageNeeded)) {
                unset($arrAllPageValues[$key]);
                continue;
            }

            if (!in_array('tl_page', $arrAllowedTables) && !array_key_exists($value['id'], $arrPageNeeded)) {
                unset($arrAllPageValues[$key]);
                continue;
            }
        }

        // No data so skip
        if (empty($arrAllPageValues) && empty($arrAllArticleValues) && empty($arrAllContentValues) && empty($arrAdditionalContent)) {
            $this->blnClose = true;

            return;
        }

        // Get some more information for the $arrAdditionalContent
        $arrAdditionalContentReorder = array();
        if (!empty($arrAdditionalContent)) {
            foreach ($arrAdditionalContent as $strTable => $arrContentData) {
                $arrData = array();

                // Events or calender
                if ($strTable == 'tl_calendar_events') {
                    foreach ($arrContentData as $strKey => $arrData) {
                        $intPid        = ($arrData['source']['pid']) ? $arrData['source']['pid'] : $arrData['target']['pid'];
                        $arrParentData = $this->getParentData(
                            $intPid,
                            'tl_calendar_events',
                            'tl_calendar',
                            'title',
                            'title'
                        );

                        // Get the author.
                        $author = \Database::getInstance()
                                           ->prepare('SELECT username, name, email, id FROM tl_user WHERE id = (SELECT author FROM tl_calendar_events WHERE id = ?)')
                                           ->execute($arrParentData['middle_id']);

                        // Rebuild array.
                        if (empty($arrParentData)) {
                            $strFullTitle = 'Unknown';
                        } elseif (isset($arrParentData['head']) && isset($arrParentData['middle'])) {
                            $strFullTitle = sprintf('%s - %s', $arrParentData['head'], $arrParentData['middle']);
                        } elseif (isset($arrParentData['middle'])) {
                            $strFullTitle = sprintf('%s', $arrParentData['middle']);
                        } else {
                            $strFullTitle = 'Unknown';
                        }

                        $arrData['parent_information']                                   = $arrParentData;
                        $arrAdditionalContentReorder[$strTable][$strFullTitle]['data'][] = $arrData;

                        // Add all data.
                        $arrAdditionalContentReorder[$strTable][$strFullTitle]['meta'] = array(
                            'archive_id' => $arrParentData['head_id'],
                            'news_id'    => $arrParentData['middle_id'],
                            'author'     => $author->fetchAssoc()
                        );
                    }
                } // News
                elseif ($strTable == 'tl_news') {
                    foreach ($arrContentData as $strKey => $arrData) {
                        $intPid        = ($arrData['source']['pid']) ? $arrData['source']['pid'] : $arrData['target']['pid'];
                        $arrParentData = $this->getParentData(
                            $intPid,
                            'tl_news',
                            'tl_news_archive',
                            'headline',
                            'title'
                        );

                        // Get the author.
                        $author = \Database::getInstance()
                                           ->prepare('SELECT username, name, email, id FROM tl_user WHERE id = (SELECT author FROM tl_news WHERE id = ?)')
                                           ->execute($arrParentData['middle_id']);

                        // Rebuild array.
                        if (empty($arrParentData)) {
                            $strFullTitle = 'Unknown';
                        } elseif (isset($arrParentData['head']) && isset($arrParentData['middle'])) {
                            $strFullTitle = sprintf('%s - %s', $arrParentData['head'], $arrParentData['middle']);
                        } elseif (isset($arrParentData['middle'])) {
                            $strFullTitle = sprintf('%s', $arrParentData['middle']);
                        } else {
                            $strFullTitle = 'Unknown';
                        }

                        $arrData['parent_information']                                   = $arrParentData;
                        $arrAdditionalContentReorder[$strTable][$strFullTitle]['data'][] = $arrData;

                        // Add all data.
                        $arrAdditionalContentReorder[$strTable][$strFullTitle]['meta'] = array(
                            'archive_id' => $arrParentData['head_id'],
                            'news_id'    => $arrParentData['middle_id'],
                            'author'     => $author->fetchAssoc()
                        );
                    }
                } // Unknown
                else {
                    foreach ($arrContentData as $strKey => $arrData) {
                        $arrData['parent_information']                               = 'Unknown';
                        $arrAdditionalContentReorder[$strTable]['Unknown']['data'][] = $arrData;
                    }
                }
            }
        }

        // Add the authors.
        foreach ($arrAllArticleValues as $key => $values) {
            // Get the author.
            $author = \Database::getInstance()
                               ->prepare('SELECT username, name, email, id FROM tl_user WHERE id = (SELECT author FROM tl_article WHERE id = ?)')
                               ->execute($values['id']);

            $arrAllArticleValues[$key]['author'] = $author->fetchAssoc();
        }

        // Template
        $objOverviewTemplate = new \BackendTemplate('be_syncCtoPro_popup_overview');

        $objOverviewTemplate->arrAllPageValues           = $arrAllPageValues;
        $objOverviewTemplate->arrAllArticleValues        = $arrAllArticleValues;
        $objOverviewTemplate->arrAllContentValues        = $arrAllContentValues;
        $objOverviewTemplate->arrAdditionalContentValues = $arrAdditionalContentReorder;
        $objOverviewTemplate->arrAllowedTables           = $arrAllowedTables;
        $objOverviewTemplate->base                       = \Environment::get('base');
        $objOverviewTemplate->path                       = \Environment::get('path');
        $objOverviewTemplate->id                         = $this->intClientID;
        $objOverviewTemplate->direction                  = $this->strDirection;
        $objOverviewTemplate->headline                   = $GLOBALS['TL_LANG']['MSC']['show_differences'];
        $objOverviewTemplate->forwardValue               = $GLOBALS['TL_LANG']['MSC']['apply'];
        $objOverviewTemplate->helperClass                = $this;

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
        $arrElement = \Database::getInstance()
                               ->query('SELECT ' . implode(", ",
                                       $arrFields) . ' FROM ' . $strTable . ' ORDER BY pid, id')
                               ->fetchAllAssoc();

        $arrElementHashes = $this->objSyncCtoProDatabase->getHashValueFor($strTable, array());

        if (array_key_exists($strTable, (array)$this->arrSyncSettings['syncCtoPro_delete'])) {
            return $this->buildTree($arrElement, $arrElementHashes, $arrClientElement['data'], $arrClientElementHashes,
                (array)$this->arrSyncSettings['syncCtoPro_delete'][$strTable]);
        } else {
            return $this->buildTree($arrElement, $arrElementHashes, $arrClientElement['data'], $arrClientElementHashes,
                array());
        }
    }

    ////////////////////////////////////////////////////////////////////////////
    // View - Detail
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Search for differences and generate the
     * detail page.
     */
    protected function runDiff(
        $arrLocalData,
        $arrExternData,
        $blnReturn = false,
        $strTemplate = 'be_syncCtoPro_popup_detail'
    ) {
        $strContent = "";
        $blnFlip    = false;

        // Diff Options
        $arrDiffOptions = array(
            'ignoreWhitespace' => true,
            'ignoreCase'       => true,
        );

        // Get ignored fields
        $arrFilterFields = $this->getIgnoredFieldsFor($this->strTable);

        // Load fields
        \Contao\Controller::loadDataContainer($this->strTable);
        $arrDcaFields = $GLOBALS['TL_DCA'][$this->strTable]['fields'];

        $arrDataForDiff = array();

        if (empty($arrLocalData)) {
            $arrLocalData  = $arrExternData;
            $arrExternData = null;
            $blnFlip       = true;
        }

        // Get fields
        $fields = \Database::getInstance()->listFields($this->strTable);

        $arrFieldMeta = array();

        foreach ($fields as $key => $value) {
            if ($value["type"] == "index") {
                continue;
            }

            $arrFieldMeta[$value["name"]] = $value;
        }

        // Check data an make something with it
        foreach ($arrLocalData as $strField => $mixValue) {
            // Check if the field is in diff blacklist for all
            if (in_array($strField, $arrFilterFields)) {
                continue;
            }

            // Get current values
            $strCurrentFieldSettings = $arrDcaFields[$strField];

            $mixValuesServer = $mixValue;
            $mixValuesClient = $arrExternData[$strField];

            // Check if we have a difference
            if ($mixValuesServer == $mixValuesClient) {
                continue;
            }

            // Hidden conditions
            if ($strCurrentFieldSettings['inputType'] == 'password' || $strCurrentFieldSettings['eval']['doNotShow'] || $strCurrentFieldSettings['eval']['hideInput']) {
                continue;
            }

            // Convert serialized arrays into strings
            if (is_array(($tmp = deserialize($mixValuesServer))) && !is_array($mixValuesServer)) {
                $mixValuesServer                              = $this->implode($tmp);
                $arrDataForDiff[$strField]['server']['array'] = true;
            }
            if (is_array(($tmp = deserialize($mixValuesClient))) && !is_array($mixValuesClient)) {
                $mixValuesClient                              = $this->implode($tmp);
                $arrDataForDiff[$strField]['client']['array'] = true;
            }
            unset($tmp);

            // Convert date fields
            if ($strCurrentFieldSettings['eval']['rgxp'] == 'date') {
                $mixValuesServer = \Date::parse($GLOBALS['TL_CONFIG']['dateFormat'], $mixValuesServer ?: '');
                $mixValuesClient = \Date::parse($GLOBALS['TL_CONFIG']['dateFormat'], $mixValuesClient ?: '');
            } elseif ($strCurrentFieldSettings['eval']['rgxp'] == 'time') {
                $mixValuesServer = \Date::parse($GLOBALS['TL_CONFIG']['timeFormat'], $mixValuesServer ?: '');
                $mixValuesClient = \Date::parse($GLOBALS['TL_CONFIG']['timeFormat'], $mixValuesClient ?: '');
            } elseif ($strCurrentFieldSettings['eval']['rgxp'] == 'datim') {
                $mixValuesServer = \Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $mixValuesServer ?: '');
                $mixValuesClient = \Date::parse($GLOBALS['TL_CONFIG']['datimFormat'], $mixValuesClient ?: '');
            }

            // Try to solve the problem for the ... binary uuid things.
            if (strlen($mixValuesServer) == 16 && in_array($arrFieldMeta[$strField]['type'],
                    array('binary', 'tinyblob', 'mediumblob', 'blob', 'longblob'))) {
                if (version_compare(VERSION . '.' . BUILD, '3.5.5', '>=')) {
                    $mixValuesServer = @\StringUtil::binToUuid($mixValuesServer);
                } else {
                    $mixValuesServer = @\String::binToUuid($mixValuesServer);
                }
            }

            // Try to solve the problem for the ... binary uuid things.
            if (strlen($mixValuesClient) == 16 && in_array($arrFieldMeta[$strField]['type'],
                    array('binary', 'tinyblob', 'mediumblob', 'blob', 'longblob'))) {
                if (version_compare(VERSION . '.' . BUILD, '3.5.5', '>=')) {
                    $mixValuesClient = @\StringUtil::binToUuid($mixValuesClient);
                } else {
                    $mixValuesClient = @\String::binToUuid($mixValuesClient);
                }
            }

            // Save for later operations
            $arrDataForDiff[$strField]['server']['data'] = $mixValuesServer;
            $arrDataForDiff[$strField]['client']['data'] = $mixValuesClient;
        }

        // Get the last key
        $arrLastKeys = array_keys($arrDataForDiff);
        $arrLastKeys = array_pop($arrLastKeys);

        // Check each field a make if diff if not empty
        foreach ($arrDataForDiff as $strField => $arrValues) {
            // Only check array if we have enough other entries
            if ($strContent != '' || ($strContent == '' && $strField != $arrLastKeys)) {
                // Check for empty data
                if ($arrValues['server']['array'] === true) {
                    $strReplaceTest = trim(str_replace(array(',', '{', '}'), '', $arrValues['server']['data']));
                    if (empty($strReplaceTest)) {
                        $arrValues['server']['data'] = '';
                    }
                }

                if ($arrValues['client']['array'] === true) {
                    $strReplaceTest = trim(str_replace(array(',', '{', '}'), '', $arrValues['client']['data']));
                    if (empty($strReplaceTest)) {
                        $arrValues['client']['data'] = '';
                    }
                }
            }

            // Special handling for '0' and ''.
            foreach ($arrValues as $side => $data) {
                if ($data['data'] === 0 || $data['data'] === '0') {
                    $arrValues[$side]['data'] = 'Value (0)';
                }

                if ($data['data'] === '') {
                    $arrValues[$side]['data'] = 'Value ()';
                }
            }

            // Get field name
            if (empty($GLOBALS['TL_LANG'][$this->strTable][$strField])) {
                $strHumanReadableField = $strField;
            } elseif (is_array($GLOBALS['TL_LANG'][$this->strTable][$strField])) {
                $strHumanReadableField = $GLOBALS['TL_LANG'][$this->strTable][$strField][0];
            } else {
                $strHumanReadableField = $GLOBALS['TL_LANG'][$this->strTable][$strField];
            }

            // Convert strings into arrays
            if (!is_array($mixValuesServer)) {
//                $mixValuesServer = explode("\n\t", strip_tags($mixValuesServer));
                $arrValues['server']['data'] = (array)strip_tags($arrValues['server']['data']);
            }
            if (!is_array($mixValuesClient)) {
//                $mixValuesClient = explode("\n\t", strip_tags($mixValuesClient));
                $arrValues['client']['data'] = (array)strip_tags($arrValues['client']['data']);
            }

            if ($strField == 'sorting') {
                $objMovedTemplate           = new \BackendTemplate('be_syncCtoPro_popup_detail_moved');
                $objMovedTemplate->strField = $strHumanReadableField;

                $intServerSorting = intval($arrValues['server']['data'][0]);
                $intClientSorting = intval($arrValues['client']['data'][0]);


                if ($intServerSorting < $intClientSorting) {
                    $objMovedTemplate->strMoved = 'up';
                    $objMovedTemplate->intMoved = $intClientSorting - $intServerSorting;
                } else {
                    $objMovedTemplate->strMoved = 'down';
                    $objMovedTemplate->intMoved = $intServerSorting - $intClientSorting;
                }

                $strContent .= $objMovedTemplate->parse();
            } elseif ($strField == 'pid') {
                $objMovedTemplate           = new \BackendTemplate('be_syncCtoPro_popup_detail_moved');
                $objMovedTemplate->strField = $strHumanReadableField;
                $objMovedTemplate->strMoved = 'parent';

                $strContent .= $objMovedTemplate->parse();
            } else {
                // Run php-diff
                if ($blnFlip) {
                    $objDiff = new \Diff($arrValues['server']['data'], $arrValues['client']['data'], $arrDiffOptions);
                } else {
                    $objDiff = new \Diff($arrValues['client']['data'], $arrValues['server']['data'], $arrDiffOptions);
                }

                $objRenderer = new Diff_Renderer_Html_Contao();
                $objRenderer->setOptions(array('field' => $strHumanReadableField));

                $mixResult = $objDiff->render($objRenderer);

                $strContent .= $mixResult;
            }
        }

        // Set wrapper template information
        $objDetailsTemplate = new \BackendTemplate($strTemplate);

        $objDetailsTemplate->base         = Environment::get('base');
        $objDetailsTemplate->path         = Environment::get('path');
        $objDetailsTemplate->id           = $this->intClientID;
        $objDetailsTemplate->direction    = $this->strDirection;
        $objDetailsTemplate->content      = $strContent;
        $objDetailsTemplate->headline     = vsprintf($GLOBALS['TL_LANG']['tl_syncCtoPro_steps']['popup']['headline_detail'],
            array($this->strTable, $this->intRowId));
        $objDetailsTemplate->currentPoint = $this->strCurrentPoint;


        if ($blnReturn) {
            return $objDetailsTemplate->parse();
        } else {
            $this->strContentData = $objDetailsTemplate->parse();
        }
    }

    ////////////////////////////////////////////////////////////////////////////
    // View - Detail for ALL entries
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Load a list with id/titles from client
     */
    protected function runAllDiff()
    {
        $arrShowId  = array();
        $arrExtData = array();
        $arrLocData = array();

        $arrTransferData = (array)\Input::post('transfer_ids');
        $arrDeleteData   = (array)\Input::post('delete_client_ids');

        // Get table and id
        foreach ($arrTransferData as $value) {
            $arrRow = explode("::", $value);

            $arrShowId[$arrRow[0]][] = $arrRow[1];
        }

        // Get table and id
        foreach ($arrDeleteData as $value) {
            $arrRow = explode("::", $value);

            $arrShowId[$arrRow[0]][] = $arrRow[1];
        }

        // Load data from extren
        foreach ($arrShowId as $key => $value) {
            $arrExtData[$key] = $this->loadExternDataFor($key, $value);
        }

        // Load data from locale
        foreach ($arrShowId as $key => $value) {
            $arrLocData[$key] = $this->loadLocalDataFor($key, $value);
        }

        // Rebuild array for extern data
        $arrRebuildExtData = array();
        foreach ($arrExtData as $strTable => $arrTableValues) {
            foreach ($arrTableValues['data'] as $arrData) {
                $arrInsert                                      = $arrData['insert'];
                $arrRebuildExtData[$strTable][$arrInsert['id']] = $arrInsert;
            }
        }

        $arrExtData = $arrRebuildExtData;

        $strDiffBuffer = "";

        $arrTableNames = array_merge(array_keys($arrLocData), array_keys($arrExtData));
        $arrTableNames = array_unique($arrTableNames);

        foreach ($arrTableNames as $strTableName) {
            $this->strTable = $strTableName;

            // Read first local data
            foreach ($arrLocData[$strTableName] as $mixID => $arrLocaleData) {
                $this->intRowId = $arrLocaleData['id'];

                if ($strTableName == 'tl_page') {
                    $this->strCurrentPoint = sprintf($GLOBALS['TL_LANG']['tl_syncCtoPro_steps']['popup']['position'],
                        $arrLocaleData['title'], '-');
                } elseif ($strTableName == 'tl_article') {
                    $arrLookupPage = \Database::getInstance()->prepare('SELECT title FROM tl_page WHERE id =?')
                                              ->execute($arrLocaleData['pid'])
                                              ->fetchAllAssoc();

                    $this->strCurrentPoint = sprintf($GLOBALS['TL_LANG']['tl_syncCtoPro_steps']['popup']['position'],
                        $arrLookupPage[0]['title'], '-');
                } elseif ($strTableName == 'tl_content' && ($arrLocaleData['ptable'] == 'tl_article' || $arrLocaleData['ptable'] == '')) {
                    $arrLookupArticle = \Database::getInstance()->prepare('SELECT pid,title FROM tl_article WHERE id =?')
                                                 ->execute($arrLocaleData['pid'])
                                                 ->fetchAllAssoc();

                    $arrLookupPage = \Database::getInstance()->prepare('SELECT title FROM tl_page WHERE id =?')
                                              ->execute($arrLookupArticle[0]['pid'])
                                              ->fetchAllAssoc();

                    $this->strCurrentPoint = sprintf($GLOBALS['TL_LANG']['tl_syncCtoPro_steps']['popup']['position'],
                        $arrLookupPage[0]['title'], $arrLookupArticle[0]['title']);
                } elseif ($strTableName == 'tl_content' && $arrLocaleData['ptable'] == 'tl_news') {
                    $arrParentData         = $this->getParentData($arrLocaleData['pid'], 'tl_news', 'tl_news_archive',
                        'headline', 'title');
                    $this->strCurrentPoint = sprintf($GLOBALS['TL_LANG']['tl_syncCtoPro_steps']['popup']['position_news'],
                        $arrParentData['head'], $arrParentData['middle']);
                } elseif ($strTableName == 'tl_content' && $arrLocaleData['ptable'] == 'tl_calendar_events') {
                    $arrParentData         = $this->getParentData($arrLocaleData['pid'], 'tl_calendar_events',
                        'tl_calendar', 'title', 'title');
                    $this->strCurrentPoint = sprintf($GLOBALS['TL_LANG']['tl_syncCtoPro_steps']['popup']['position_events'],
                        $arrParentData['head'], $arrParentData['middle']);
                }

                if (isset($arrExtData[$strTableName]) && array_key_exists($arrLocaleData['id'],
                        $arrExtData[$strTableName])) {
                    $strDiffBuffer .= $this->runDiff($arrLocaleData, $arrExtData[$strTableName][$arrLocaleData['id']],
                        true, 'be_syncCtoPro_popup_detail_small');

                    // Free up memory
                    unset($arrExtData[$strTableName][$arrLocaleData['id']]);
                    unset($arrLocData[$strTableName][$mixID]);
                } else {
                    $strDiffBuffer .= $this->runDiff($arrLocaleData, null, true, 'be_syncCtoPro_popup_detail_small');

                    // Free up memory
                    unset($arrLocData[$strTableName][$mixID]);
                }
            }

            // Read second extern data
            foreach ((array)$arrExtData[$strTableName] as $mixID => $arrExternData) {
                $this->intRowId = $mixID;
                $strDiffBuffer  .= $this->runDiff(array(), $arrExternData, true, 'be_syncCtoPro_popup_detail_small');

                // Free up memory
                unset($arrExtData[$strTableName][$mixID]);
            }
        }

        // Add Base template
        $objBaseTemplate = new \BackendTemplate('be_syncCtoPro_popup_all');

        $objBaseTemplate->base      = \Environment::get('base');
        $objBaseTemplate->path      = \Environment::get('path');
        $objBaseTemplate->id        = $this->intClientID;
        $objBaseTemplate->direction = $this->strDirection;

        $objBaseTemplate->headline = $GLOBALS['TL_LANG']['tl_syncCtoPro_steps']['popup']['headline_diff_all'];
        $objBaseTemplate->strData  = $strDiffBuffer;

        $this->strContentData = $objBaseTemplate->parse();
    }

    ////////////////////////////////////////////////////////////////////////////
    // Helper
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Make a lookup for a human readable table name.
     * First syncCto language
     * Second the mapping for mod language
     * Last the mod language
     *
     * @param string $strName Name of table
     *
     * @return string
     */
    public function lookUpName($strName)
    {
        $strBase = str_replace('tl_', "", $strName);

        // If empty return a array.
        if ($strName == '-') {
            return array(
                'tname' => '-',
                'iname' => '-'
            );
        }

        // Make a lookup in synccto language files
        if (is_array($GLOBALS['TL_LANG']['tl_syncCto_database']) && array_key_exists($strName,
                $GLOBALS['TL_LANG']['tl_syncCto_database'])) {
            if (is_array($GLOBALS['TL_LANG']['tl_syncCto_database'][$strName])) {
                return $this->formateLookUpName($strName, $GLOBALS['TL_LANG']['tl_syncCto_database'][$strName][0]);
            } else {
                return $this->formateLookUpName($strName, $GLOBALS['TL_LANG']['tl_syncCto_database'][$strName]);
            }
        }

        // Get MM name
        if (in_array('metamodels', ModuleLoader::getActive()) && preg_match("/^mm_/i", $strName)) {
            try {
                if (!is_null(\MetaModels\Factory::byTableName($strName))) {
                    $objDCABuilder     = \MetaModels\Dca\MetaModelDcaBuilder::getInstance();
                    $objMetaModels     = \MetaModels\Factory::byTableName($strName);
                    $arrDCA            = $objDCABuilder->getDca($objMetaModels->get('id'));
                    $arrBackendcaption = deserialize($arrDCA['backendcaption']);

                    $strReturn = $objMetaModels->getName();

                    foreach ((array)$arrBackendcaption as $value) {
                        if ($value['langcode'] == \BackendUser::getInstance()->language) {
                            $strReturn = $value['label'];
                            break;
                        }
                    }

                    return $this->formateLookUpName($strName, $strReturn);
                }
            } catch (Exception $exc) {
                // Nothing to do;
            }
        }

        // Little mapping for names
        if (is_array($GLOBALS['SYC_CONFIG']['database_mapping']) && key_exists($strName,
                $GLOBALS['SYC_CONFIG']['database_mapping'])) {
            $strRealSystemName = $GLOBALS['SYC_CONFIG']['database_mapping'][$strName];

            if (is_array($GLOBALS['TL_LANG']['MOD'][$strRealSystemName])) {
                return $this->formateLookUpName($strName, $GLOBALS['TL_LANG']['MOD'][$strRealSystemName][0]);
            } else {
                return $this->formateLookUpName($strName, $GLOBALS['TL_LANG']['MOD'][$strRealSystemName]);
            }
        }

        // Search in mod language array for a translation
        if (array_key_exists($strBase, $GLOBALS['TL_LANG']['MOD'])) {
            if (is_array($GLOBALS['TL_LANG']['MOD'][$strBase])) {
                return $this->formateLookUpName($strName, $GLOBALS['TL_LANG']['MOD'][$strBase][0]);
            } else {
                return $this->formateLookUpName($strName, $GLOBALS['TL_LANG']['MOD'][$strBase]);
            }
        }

        return $this->formateLookUpName($strName, $strName);
    }

    /**
     * Return a array with tahble names
     *
     * @param string $strTableName    real name like 'tl_contetn'
     * @param string $strReadableName readable name like 'Conten Elements'
     *
     * @return array('tname' => [for table], 'iname' => [for title/info])
     */
    protected function formateLookUpName($strTableName, $strReadableName)
    {
        // Check if the function is activate
        if (BackendUser::getInstance()->syncCto_useTranslatedNames) {
            return array(
                'tname' => $strReadableName,
                'iname' => $strTableName
            );
        } else {
            return array(
                'tname' => $strTableName,
                'iname' => $strReadableName
            );
        }
    }

    /**
     * Get informtaion from the parent and parent parent table eg. events or news.
     *
     * @param int    $intId          First parent ID.
     *
     * @param string $strMiddleTable Name of the first parent table.
     *
     * @param string $strHeadTable   Name of the parent parent table.
     *
     * @param string $strMiddleField
     *
     * @param string $strHeadField
     *
     * @return array
     */
    public function getParentData($intId, $strMiddleTable, $strHeadTable, $strMiddleField, $strHeadField)
    {
        $arrReturn = array();

        // Check if the middle table exists.
        if (!\Database::getInstance()->tableExists($strMiddleTable)) {
            return $arrReturn;
        }

        // Get the middle table
        $objMiddleTable = \Database::getInstance()
                                   ->prepare('SELECT id, pid, ' . $strMiddleField . ' FROM ' . $strMiddleTable . ' WHERE id=?')
                                   ->execute($intId);

        if ($objMiddleTable->count() == 0) {
            return $arrReturn;
        }

        $arrReturn['middle']    = $objMiddleTable->$strMiddleField;
        $arrReturn['middle_id'] = $objMiddleTable->id;

        // Check if the head table exists.
        if ($strHeadTable == null || !\Database::getInstance()->tableExists($strHeadTable)) {
            return $arrReturn;
        }

        // Get the main table.
        $objHeadTable = \Database::getInstance()
                                 ->prepare('SELECT id, ' . $strHeadField . ' FROM ' . $strHeadTable . ' WHERE id=?')
                                 ->execute($objMiddleTable->pid);

        if ($objHeadTable->count() == 0) {
            return $arrReturn;
        }

        $arrReturn['head']    = $objHeadTable->$strHeadField;
        $arrReturn['head_id'] = $objHeadTable->id;

        return $arrReturn;
    }


    /**
     * Get the humsn readable name of a content type.
     *
     * @param string $strType Type of content element.
     *
     * @return string Name for the content element.
     */
    public function getReadableContentName($strType)
    {
        // Check tl_content
        if (array_key_exists($strType, $GLOBALS['TL_LANG']['tl_content'])) {
            if (is_array($GLOBALS['TL_LANG']['tl_content'][$strType])) {
                return $GLOBALS['TL_LANG']['tl_content'][$strType][0];
            } else {
                return $GLOBALS['TL_LANG']['tl_content'][$strType];
            }
        }

        // Check cte
        if (isset($GLOBALS['TL_LANG']['CTE']) && array_key_exists($strType, $GLOBALS['TL_LANG']['CTE'])) {
            if (is_array($GLOBALS['TL_LANG']['CTE'][$strType])) {
                return $GLOBALS['TL_LANG']['CTE'][$strType][0];
            } else {
                return $GLOBALS['TL_LANG']['CTE'][$strType];
            }
        }

        // Nothing found return the type.
        return $strType;
    }

    /**
     * Load a list with id/titles from client
     *
     * @throws \Exception
     */
    protected function loadExternDataFor($strTable, $mixID)
    {
        $strExportFile = $this->objSyncCtoProCommunicationClient->exportDatabaseSE('', $strTable, (array)$mixID);

        // Check if we have all files
        if ($strExportFile === false) {
            throw new \Exception('Missing export file for tl_page');
        }

        //Load the se export files from client
        $strSavePath = $this->objSyncCtoHelper->standardizePath($GLOBALS['SYC_PATH']['tmp'], basename($strExportFile));

        $blnResponse = $this->objSyncCtoProCommunicationClient->getFile($strExportFile, $strSavePath);

        // Check if we have the file
        if (!$blnResponse) {
            throw new \Exception("Empty file list from client. Maybe file sending was not complet for $strExportFile.");
        }

        // Read client pages
        return $this->objSyncCtoProDatabase->readXML($strSavePath);
    }

    /**
     * Load local data
     *
     * @param string $strTable
     * @param        $mixID
     *
     * @return array
     * @internal param int $intID
     *
     */
    protected function loadLocalDataFor($strTable, $mixID)
    {
        if (is_array($mixID)) {
            return \Database::getInstance()->prepare("SELECT * FROM $strTable WHERE id IN (" . implode(", ",
                    $mixID) . ")")
                            ->execute()
                            ->fetchAllAssoc();
        } else {
            return \Database::getInstance()->prepare("SELECT * FROM $strTable WHERE id = ?")
                            ->execute($mixID)
                            ->fetchAllAssoc();
        }
    }

    /**
     * Implode a multi-dimensional array recursively
     *
     * @author Leo Feyer https://contao.org
     *
     * @param mixed
     *
     * @return string
     */
    protected function implode($var)
    {
        if (!is_array($var)) {
            return $var;
        }

        $strReturn = '';
        $blnFirst  = true;

        foreach ($var as $key => $value) {
            if (!$blnFirst) {
                $strReturn .= ', ';
            } else {
                $blnFirst = false;
            }

            if (is_array($value)) {
                $strReturn .= '{' . $this->implode($value) . '}';
            } else {
                $strReturn .= $value;
            }
        }

        return $strReturn;
    }

    /**
     * Load the sync settings for a client
     */
    protected function loadSyncSettings()
    {
        $this->arrSyncSettings = Session::getInstance()->get("syncCto_SyncSettings_" . $this->intClientID);

        if (!is_array($this->arrSyncSettings)) {
            $this->arrSyncSettings = array();
        }
    }

    /**
     * Save the sync settings for a client
     */
    protected function saveSyncSettings()
    {
        if (!is_array($this->arrSyncSettings)) {
            $this->arrSyncSettings = array();
        }

        Session::getInstance()->set("syncCto_SyncSettings_" . $this->intClientID, $this->arrSyncSettings);
    }

    public function rebuildArray($arrData)
    {
        $arrReturn = array();
        foreach ($arrData as $arrValue) {
            if (array_key_exists('insert', $arrValue)) {
                $arrReturn[$arrValue['insert']['id']] = $arrValue['insert'];
            } else {
                $arrReturn[$arrValue['id']] = $arrValue;
            }
        }

        return $arrReturn;
    }

    /**
     *
     * @param array $arrSourcePages
     *
     * @param array $arrSourceHashes
     *
     * @param array $arrTargetPages
     *
     * @param array $arrTargetHashes
     *
     * @param array $arrIgnoredIds
     *
     * @return array
     */
    protected function buildTree(
        $arrSourcePages,
        $arrSourceHashes,
        $arrTargetPages,
        $arrTargetHashes,
        $arrIgnoredIds = array()
    ) {
        // Set id as key
        $arrSourcePages = $this->rebuildArray($arrSourcePages);
        $arrTargetPages = $this->rebuildArray($arrTargetPages);

        // Search for missing entries
        $arrKeysSource = array_keys($arrSourcePages);
        $arrKeysTarget = array_keys($arrTargetPages);

        $arrMissingClient = array_diff($arrKeysSource, $arrKeysTarget);
        $arrMissingServer = array_diff($arrKeysTarget, $arrKeysSource);

        $arrReturn = array();

        foreach ($arrSourcePages as $intID => $mixValues) {
            // Set ID
            $arrReturn[$intID]['id']     = $intID;
            $arrReturn[$intID]['delete'] = false;

            // Set pid
            if (array_key_exists('pid', $mixValues)) {
                $arrReturn[$intID]['pid'] = $mixValues['pid'];
            }

            // Set sorting
            if (array_key_exists('sorting', $mixValues)) {
                $arrReturn[$intID]['sorting'] = $mixValues['sorting'];
            }

            // Check Hash
            if ($arrSourceHashes[$intID]['hash'] == $arrTargetHashes[$intID]['hash']) {
                $arrReturn[$intID]['state'] = 'same';
            } else {
                $arrReturn[$intID]['state'] = 'diff';
            }

            // Set all other information
            if (array_key_exists($intID, $arrTargetPages) && array_key_exists($intID, $arrTargetHashes)) {
                $arrReturn[$intID]['source'] = array_merge($mixValues, $arrSourceHashes[$intID]);
                $arrReturn[$intID]['target'] = array_merge($arrTargetPages[$intID], $arrTargetHashes[$intID]);
            } elseif (array_key_exists($intID, $arrTargetPages) && !array_key_exists($intID, $arrTargetHashes)) {
                $arrReturn[$intID]['source'] = array_merge($mixValues, $arrSourceHashes[$intID]);
                $arrReturn[$intID]['target'] = $arrTargetPages[$intID];
            } elseif (!array_key_exists($intID, $arrTargetPages) && array_key_exists($intID, $arrTargetHashes)) {
                $arrReturn[$intID]['source'] = array_merge($mixValues, $arrSourceHashes[$intID]);
                $arrReturn[$intID]['target'] = $arrTargetHashes[$intID];
            } else {
                $arrReturn[$intID]['source'] = array_merge($mixValues, $arrSourceHashes[$intID]);
                $arrReturn[$intID]['target'] = array();
            }

            // Set state ignored if in list
            if (in_array($intID, $arrIgnoredIds)) {
                $arrReturn[$intID]['state'] = 'ignored';
            }

            // Set the images.
            $arrReturn[$intID]['source']['site_image'] = $this->getPageIcon($arrReturn[$intID]['source']);
            $arrReturn[$intID]['target']['site_image'] = $this->getPageIcon($arrReturn[$intID]['target']);
        }

        foreach ($arrMissingServer as $intID) {
            $arrReturn[$intID] = array(
                'id'     => $intID,
                'pid'    => $arrTargetPages[$intID]['pid'],
                'state'  => 'diff',
                'delete' => true,
                'source' => array(),
                'target' => array_merge($arrTargetPages[$intID], $arrTargetHashes[$intID])
            );

            if (in_array($intID, $arrIgnoredIds)) {
                $arrReturn[$intID]['state'] = 'ignored';
            }
        }

        return $arrReturn;
    }

    /**
     * Get a list with ignored fields for the hashes
     *
     * @param string $strTable Name of table
     *
     * @return array
     */
    protected function getIgnoredFieldsFor($strTable)
    {
        $arrReturn = array();

        // Get all Values
        if (array_key_exists('all', $GLOBALS['SYC_CONFIG']['trigger_blacklist'])) {
            $arrReturn = array_merge($arrReturn, $GLOBALS['SYC_CONFIG']['trigger_blacklist']['all']);
        }

        // Get special Values
        if (array_key_exists($strTable, $GLOBALS['SYC_CONFIG']['trigger_blacklist'])) {
            $arrReturn = array_merge($arrReturn, $GLOBALS['SYC_CONFIG']['trigger_blacklist'][$strTable]);
        }

        $arrUserSettings = array();
        foreach ((array)deserialize($GLOBALS['TL_CONFIG']['syncCto_diff_blacklist']) as $key => $value) {
            $arrUserSettings[$value['table']][] = $value['entry'];
        }

        // Get all Values
        if (array_key_exists('all', $arrUserSettings)) {
            $arrReturn = array_merge($arrReturn, $arrUserSettings['all']);
        }

        // Get special Values
        if (array_key_exists($strTable, $arrUserSettings)) {
            $arrReturn = array_merge($arrReturn, $arrUserSettings[$strTable]);
        }

        return array_unique($arrReturn);
    }

    /**
     * Sorting array
     *
     * @param array $a
     * @param array $b
     *
     * @return int
     */
    public function sortByPid($a, $b)
    {
        // Pid + Sorting support
        if (array_key_exists('sorting', $a) && array_key_exists('sorting', $b) && array_key_exists('pid',
                $a) && array_key_exists('pid', $b)) {
            if ($a['pid'] == $b['pid']) {
                if ($a['sorting'] == $b['sorting']) {
                    return 0;
                }

                return ($a['sorting'] < $b['sorting']) ? -1 : 1;
            }

            return ($a['pid'] < $b['pid']) ? -1 : 1;
        } // Pid support
        elseif (array_key_exists('pid', $a) && array_key_exists('pid', $b)) {
            if ($a['pid'] == $b['pid']) {
                if ($a['id'] == $b['id']) {
                    return 0;
                }

                return ($a['id'] < $b['id']) ? -1 : 1;
            }

            return ($a['pid'] < $b['pid']) ? -1 : 1;
        } // ID only
        else {
            if ($a['id'] == $b['id']) {
                return 0;
            }

            return ($a['id'] < $b['id']) ? -1 : 1;
        }
    }

    /**
     * Calculate the page status icon name based on the page parameters
     *
     * @param array $arrPage The page array
     *
     * @return string The status icon name
     */
    public function getPageIcon($arrPage)
    {
        $sub   = 0;
        $image = $arrPage['type'] . '.svg';

        // Page not published or not active
        if (!$arrPage['published'] || $arrPage['start'] && $arrPage['start'] > time() || $arrPage['stop'] && $arrPage['stop'] < time()) {
            $sub += 1;
        }

        // Page hidden from menu
        if ($arrPage['hide'] && !in_array($arrPage['type'],
                array('redirect', 'forward', 'root', 'error_403', 'error_404'))) {
            $sub += 2;
        }

        // Page protected
        if ($arrPage['protected'] && !in_array($arrPage['type'], array('root', 'error_403', 'error_404'))) {
            $sub += 4;
        }

        // Get the image name
        if ($sub > 0) {
            $image = $arrPage['type'] . '_' . $sub . '.gif';
        }


        if (file_exists(TL_ROOT . '/system/themes/' . Backend::getTheme() . '/icons/' . $image)) {
            return 'system/themes/' . Backend::getTheme() . '/icons/' . $image;
        }

        if (file_exists(TL_ROOT . '/system/themes/flexible/icons/' . $image)) {
            return 'system/themes/flexible/icons/' . $image;
        }

        return 'system/themes/flexible/icons/regular.svg';
    }

}