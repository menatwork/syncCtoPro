<?php 

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2013 
 * @package    syncCto Pro
 * @license    EULA
 * @filesource
 */

class SyncCtoProDatabase extends Backend
{

    /**
     * @var SyncCtoHelper
     */
    protected $objSyncCtoHelper;

    /**
     * @var SyncCtoProDatabase 
     */
    protected static $objInstance = null;

    /**
     * Construct
     */
    protected function __construct()
    {
        parent::__construct();

        // Init Helper
        $this->objSyncCtoHelper = SyncCtoHelper::getInstance();

        // Get Max mem usages
        $this->intMaxMemoryUsage = intval(str_replace(array("m", "M", "k", "K"), array("000000", "000000", "000", "000"), ini_get('memory_limit')));
        $this->intMaxMemoryUsage = $this->intMaxMemoryUsage / 100 * 80;
    }

    /**
     * @return SyncCtoProDatabase
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
    // Export Functions
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Get data for table x for fields y and id z
     * as gzip file.
     * 
     * @param string $strPath Path for writing the file, if empty a tmp path will be created
     * @param string $strTable Name of Table
     * @param array $arrIds List of ids
     * @param array $arrFields List of fields
     * 
     * @return boolean|string
     */
    public function getDataForAsFile($strPath, $strTable, $arrIds = null, $arrFields = null)
    {
        $objData = $this->getDataFor($strTable, $arrIds, $arrFields);

        if ($objData === false)
        {
            return false;
        }

        if (empty($strPath))
        {
            // Write some tempfiles
            $strRandomToken = substr(md5(time() . " | " . rand(0, 65535)), 0, 8);
            $strPath        = $this->objSyncCtoHelper->standardizePath($GLOBALS['SYC_PATH']['tmp'], "SyncCto-SE-$strRandomToken-" . standardize($strTable) . ".gzip");
        }

        return $this->writeXML($strPath, $objData, $strTable);
    }

    /**
     * Get data for table x for fields y and id z.
     * 
     * @param type $strTable
     * @param type $arrIds
     * @param type $arrFields
     * 
     * @return boolean|Database_Result
     */
    public function getDataFor($strTable, $arrIds = null, $arrFields = null)
    {
        if (!$this->Database->tableExists($strTable))
        {
            return false;
        }

        if ($arrFields == null)
        {
            $strFields = '*';
        }
        else
        {
            $strFields = "`" . implode("`, `", $arrFields) . "`";
        }

        if ($arrIds == null)
        {
            $strWhere = '';
        }
        else
        {
            $strWhere = "WHERE id IN(" . implode(', ', $arrIds) . ")";
        }

        $objData = $this->Database->query("SELECT $strFields FROM $strTable $strWhere ORDER BY id");

        return $objData;
    }

    public function setDataForAsFile($strPath)
    {
        // Import
        $arrData = $this->readXML($strPath);

        // Error
        if ($arrData === false)
        {
            throw new Exception('Error by reading the import file at ' . $strPath);
        }

        $mixSuccess = $this->setDataFor($arrData['table'], $arrData['data'], array_keys($arrData['fields']));

        // Error
        if ($mixSuccess === false)
        {
            throw new Exception('Error by importing the data into database for file: ' . $strPath);
        }

        return $mixSuccess;
    }

    public function setDataFor($strTable, $arrData, $arrInsertFields)
    {
        if (empty($strTable) || !$this->Database->tableExists($strTable))
        {
            throw new Exception('Error by import data. Unknown or empty tablename: ' . $arrData['table']);
        }

        if (empty($arrData))
        {
            return 'Import/Update 0 Rows';
        }

        // Check fields
        $arrKnownFields = $this->Database->getFieldNames($strTable);

        if (($mixKey = array_search('PRIMARY', $arrKnownFields)) !== false)
        {
            unset($arrKnownFields[$mixKey]);
        }

        $arrFieldsMissingInsert   = array_diff($arrKnownFields, $arrInsertFields);
        $arrFieldsMissingDatabase = array_diff($arrInsertFields, $arrKnownFields);

        if (!empty($arrFieldsMissingInsert) || !empty($arrFieldsMissingInsert))
        {
            $strError = 'We have missin missing fields.';
            $strError .= '|| Database:    ' . implode(", ", $arrFieldsMissingDatabase);
            $strError .= '|| Insert-File: ' . implode(", ", $arrFieldsMissingInsert);

            throw new Exception($strError);
        }

        // Import
        $arrKnownIDs = $this->Database->query("SELECT id FROM $strTable")->fetchEach('id');

        $arrUpdate = array();
        $arrInsert = array();

        // Split in update/insert
        foreach ($arrData as $mixKey => $arrValues)
        {
            if (in_array($arrValues['id'], $arrKnownIDs))
            {
                $arrUpdate[] = $arrValues;
            }
            else
            {
                $arrInsert[] = $arrValues;
            }

            unset($arrData[$mixKey]);
        }

        // Update
        foreach ($arrUpdate as $key => $arrValues)
        {
            $intID = $arrValues['id'];
            unset($arrValues['id']);

            $this->Database->prepare("UPDATE $strTable %s WHERE id=?")
                    ->set($arrValues)
                    ->execute($intID);
        }

        // Insert
        foreach ($arrInsert as $key => $arrValues)
        {
            $this->Database->prepare("INSERT INTO $strTable %s")
                    ->set($arrValues)
                    ->execute();
        }

        return true;
    }

    ////////////////////////////////////////////////////////////////////////////
    // XML Functions
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Return data as gzip xml file
     *
     * @param Database_Result $objData
     * @param stirng $strTable
     * 
     * @return string
     */
    public function writeXML($strPath, Database_Result $objData, $strTable)
    {
        // Write gzip xml file
        $objGzFile = new File($strPath);
        $objGzFile->write("");
        $objGzFile->close();

        // Compression
        $objGzFile = gzopen(TL_ROOT . "/" . $strPath, "wb");

        // Create XML File
        $objXml = new XMLWriter();
        $objXml->openMemory();
        $objXml->setIndent(true);
        $objXml->setIndentString("\t");

        // XML Start
        $objXml->startDocument('1.0', 'UTF-8');
        $objXml->startElement('database');

        // Write meta (header)
        $objXml->startElement('metatags');
        $objXml->writeElement('table', $strTable);
        $objXml->writeElement('version', $GLOBALS['SYC_VERSION'] . ' - PRO Version');
        $objXml->writeElement('create_unix', time());
        $objXml->writeElement('create_date', date('Y-m-d', time()));
        $objXml->writeElement('create_time', date('H:i', time()));
        $objXml->endElement(); // End metatags

        foreach ($objData->fetchAllAssoc() as $arrRow)
        {
            if ($this->intMaxMemoryUsage < memory_get_usage(true))
            {
                $strXMLFlush = $objXml->flush(true);
                gzputs($objGzFile, $strXMLFlush, strlen($strXMLFlush));
            }

            $objXml->startElement('row');

            foreach ($arrRow as $strField => $mixvalue)
            {
                $objXml->startElement('data');
                $objXml->writeAttribute('name', $strField);
                $objXml->writeCdata($mixvalue);
                $objXml->endElement(); // End data
            }

            $objXml->endElement(); // End row
        }

        $objXml->endElement(); // End database

        $strXMLFlush = $objXml->flush(true);
        gzputs($objGzFile, $strXMLFlush, strlen($strXMLFlush));
        gzclose($objGzFile);

        return $strPath;
    }

    /**
     * Reading a import file for single export
     * 
     * @param string $strImportPath path to the file
     * 
     * @return array array('table' => [tablename] , 'data' => array([data]))
     * 
     * @throws Exception
     */
    public function readXML($strImportPath)
    {
        // Check we have a file
        if (!file_exists(TL_ROOT . "/" . $strImportPath))
        {
            throw new Exception('File not found: ' . $strImportPath);
        }

        // Write some tempfiles
        $strRandomToken = substr(md5(time() . " | " . rand(0, 65535)), 0, 8);
        $strTempPath    = $this->objSyncCtoHelper->standardizePath($GLOBALS['SYC_PATH']['tmp'], "SyncCtoPro-SE-$strRandomToken.xml");

        // Unzip XML
        $objGzFile = gzopen(TL_ROOT . "/" . $strImportPath, "r");

        // Write xml file
        $objXMLFile = new File($strTempPath);
        $objXMLFile->write("");
        $objXMLFile->close();

        while (true)
        {
            $strConten = gzread($objGzFile, 500000);

            if ($strConten == false || empty($strConten))
            {
                break;
            }

            $objXMLFile->append($strConten, "");
            $objXMLFile->close();
        }

        $arrData = array(
            'table' => '',
            'data'  => array(),
            'fields' => array()
        );
        $strCurrentAttribute = '';
        $blnInData           = false;
        $blnInTable          = false;
        $intI                = 0;

        // Read XML
        $objXMLReader = new XMLReader();
        $objXMLReader->open(TL_ROOT . "/" . $strTempPath);

        while ($objXMLReader->read())
        {
            switch ($objXMLReader->nodeType)
            {
                // Values
                case XMLReader::TEXT:
                case XMLReader::CDATA:
                    if ($blnInData)
                    {
                        $arrData['data'][$intI][$strCurrentAttribute] = $objXMLReader->value;
                    }
                    else if ($blnInTable)
                    {
                        $arrData['table'] = $objXMLReader->value;
                    }
                    break;

                // Element
                case XMLReader::ELEMENT:
                    switch ($objXMLReader->localName)
                    {
                        // Start data
                        case 'data':
                            $strCurrentAttribute                                    = $objXMLReader->getAttribute('name');
                            $arrData['fields'][$objXMLReader->getAttribute('name')] = 1;
                            $blnInData                                              = true;
                            break;

                        case 'table':
                            $blnInTable = true;
                            break;
                    }
                    break;

                // Element end
                case XMLReader::END_ELEMENT:
                    switch ($objXMLReader->localName)
                    {
                        // End data
                        case 'data':
                            $blnInData = false;
                            break;

                        // End of row
                        case 'row':
                            $intI++;
                            $blnInData = false;
                            break;

                        case 'table':
                            $blnInTable = false;
                            break;
                    }
                    break;
            }
        }

        $objXMLFile->delete();

        return $arrData;
    }

}

?>
