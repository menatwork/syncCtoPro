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

require_once TL_ROOT . '/plugins/php-diff/Diff.php';
require_once TL_ROOT . '/plugins/php-diff/Diff/Renderer/Html/Array.php';
require_once TL_ROOT . '/plugins/php-diff/Diff/Renderer/Html/Inline.php';
require_once TL_ROOT . '/plugins/php-diff/Diff/Renderer/Html/SideBySide.php';

/**
 * Class SyncCtoPopup
 */
class PopupSyncDiff extends Backend
{
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
    protected $arrDiffData;

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

        // Load language
        $this->loadLanguageFile('default');

        // Init Helper
        $this->objSyncCtoHelper                 = SyncCtoHelper::getInstance();
        $this->objSyncCtoCommunicationClient    = SyncCtoCommunicationClient::getInstance();
        $this->objSyncCtoProCommunicationClient = SyncCtoProCommunicationClient::getInstance();
        $this->objSyncCtoProDatabase            = SyncCtoProDatabase::getInstance();

        // Load all values from get param
        $this->initGetParams();
    }

    /**
     * Load the template list and go through the steps
     */
    public function run()
    {
        try
        {
            $this->initConnetcion();
            $this->loadExternDataFor($this->strTable, $this->intRowId);
            $this->loadLocalDataFor($this->strTable, $this->intRowId);

            $this->runDiff();

            $this->output();
        }
        catch (Exception $exc)
        {
            // TODO show error
            var_dump($exc->getMessage());
        }
    }

    ////////////////////////////////////////////////////////////////////////////
    // Core
    ////////////////////////////////////////////////////////////////////////////

    protected function initGetParams()
    {
        $this->intClientID  = $this->Input->get('id');
        $this->strDirection = $this->Input->get('direction');
        $this->strTable     = $this->Input->get('table');
        $this->intRowId     = $this->Input->get('row_id');
    }

    protected function initConnetcion()
    {
        $this->objSyncCtoCommunicationClient->setClientBy($this->intClientID);
    }

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

    protected function loadLocalDataFor($strTable, $intID)
    {
        $this->arrLocalData = $this->Database->prepare("SELECT * FROM $strTable WHERE id = ?")
                ->execute($intID)
                ->fetchAllAssoc();
    }

    protected function runDiff()
    {
        $options = array(
                //'ignoreWhitespace' => true,
                //'ignoreCase' => true,
        );

        foreach ($this->arrLocalData[0] as $strField => $mixValue)
        {
            $objDiff = new Diff(array($this->arrExternData['data'][0][$strField]), array($mixValue), $options);
            $renderer = new Diff_Renderer_Html_Array();

            $arrResult = $objDiff->Render($renderer);

            if (empty($arrResult))
            {
                continue;
            }

            $this->arrDiffData[$strField] = $arrResult;
        }
    }

    protected function output()
    {
        if (!is_array($this->arrDiffData) || empty($this->arrDiffData))
        {
            echo "<div>empty</div>";
        }
        else
        {
            foreach ($this->arrDiffData as $strField => $arrDiff)
            {
                echo "<div><h2>" . $strField . "</h2></div>";
                echo "<div>";
                echo "<h3>Server</h3>";
                echo implode("<br />", $arrDiff[0][0]['base']['lines']);
                echo "</div>";
                echo "<div>";
                echo "<h3>Client</h3>";
                echo implode("<br />", $arrDiff[0][0]['changed']['lines']);
                echo "</div> <hr />";
            }
        }
    }

}

/**
 * Instantiate controller
 */
$objPopup = new PopupSyncDiff();
$objPopup->run();
?>