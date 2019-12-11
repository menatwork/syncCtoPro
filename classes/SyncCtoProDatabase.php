<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2013
 * @package    syncCto Pro
 * @license    EULA
 * @filesource
 */
class SyncCtoProDatabase extends \Backend
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
        $this->intMaxMemoryUsage = intval(str_replace(array("m", "M", "k", "K"),
            array("000000", "000000", "000", "000"), ini_get('memory_limit')));
        $this->intMaxMemoryUsage = $this->intMaxMemoryUsage / 100 * 80;

        // Load languages
        $this->loadLanguageFile('default');
        $this->loadLanguageFile('tl_syncCto_check');
    }

    /**
     * @return SyncCtoProDatabase
     */
    public static function getInstance()
    {
        if (!is_object(self::$objInstance)) {
            self::$objInstance = new self();
        }

        return self::$objInstance;
    }

    ////////////////////////////////////////////////////////////////////////////
    // Delete Functions
    ////////////////////////////////////////////////////////////////////////////

    public function deleteEntries($strTable, $arrIds = array())
    {
        if (empty($arrIds)) {
            return false;
        }

        if (!\Database::getInstance()->tableExists($strTable)) {
            return false;
        }

        $strQuery = "DELETE FROM $strTable WHERE id IN (" . implode(", ", $arrIds) . ")";
        \Database::getInstance()->query($strQuery);
    }

    ////////////////////////////////////////////////////////////////////////////
    // Export Functions
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Get data for table x for fields y and id z
     * as gzip file.
     *
     * @param string $strPath   Path for writing the file, if empty a tmp path will be created
     * @param string $strTable  Name of Table
     * @param array  $arrIds    List of ids
     * @param array  $arrFields List of fields
     *
     * @return boolean|string
     */
    public function getDataForAsFile($strPath, $strTable, $arrIds = null, $arrFields = null, $arrOnlyInsert = array())
    {
        $objData = $this->getDataFor($strTable, $arrIds, $arrFields);

        if ($objData === false) {
            return false;
        }

        if (empty($strPath)) {
            // Write some tempfiles
            $strRandomToken = substr(md5(time() . " | " . rand(0, 65535)), 0, 8);
            $strPath        = $this->objSyncCtoHelper->standardizePath($GLOBALS['SYC_PATH']['tmp'],
                "SyncCto-SE-$strRandomToken-" . standardize($strTable) . ".gzip");
        }

        return $this->writeXML($strPath, $objData, $strTable, $arrOnlyInsert);
    }

    /**
     * Get data for table x for fields y and id z.
     *
     * @param string $strTable
     * @param array  $arrIds
     * @param array  $arrFields
     *
     * @return boolean|\Database_Result
     */
    public function getDataFor($strTable, $arrIds = null, $arrFields = null)
    {
        if (!\Database::getInstance()->tableExists($strTable)) {
            return false;
        }

        if ($arrFields == null) {
            $strFields = '*';
        } else {
            $strFields = "`" . implode("`, `", $arrFields) . "`";
        }

        if ($arrIds == null) {
            $strWhere = '';
        } else {
            $strWhere = "WHERE id IN(" . implode(', ', $arrIds) . ")";
        }

        $objData = \Database::getInstance()->query("SELECT $strFields FROM $strTable $strWhere ORDER BY id");

        return $objData;
    }

    public function setDataForAsFile($strPath)
    {
        // Import
        $arrData = $this->readXML($strPath);

        // Error
        if ($arrData === false) {
            throw new Exception('Error by reading the import file at ' . $strPath);
        }

        $mixSuccess = $this->setDataFor($arrData['table'], $arrData['data'], array_keys($arrData['fields']));

        // Error
        if ($mixSuccess === false) {
            throw new Exception('Error by importing the data into database for file: ' . $strPath);
        }

        return $mixSuccess;
    }

    public function setDataFor($strTable, $arrData, $arrInsertFields)
    {
        if (empty($strTable) || !\Database::getInstance()->tableExists($strTable)) {
            throw new Exception('Error by import data. Unknown or empty tablename: ' . $arrData['table']);
        }

        if (empty($arrData)) {
            return 'Import/Update 0 Rows';
        }

        // Check fields
        $arrKnownFields = \Database::getInstance()->getFieldNames($strTable);

        if (($mixKey = array_search('PRIMARY', $arrKnownFields)) !== false) {
            unset($arrKnownFields[$mixKey]);
        }

        $arrFieldsMissingInsert   = array_diff($arrKnownFields, $arrInsertFields);
        $arrFieldsMissingDatabase = array_diff($arrInsertFields, $arrKnownFields);

        if (!empty($arrFieldsMissingInsert) || !empty($arrFieldsMissingInsert)) {
            $strError = 'We have missin missing fields.';
            $strError .= '|| Database:    ' . implode(", ", $arrFieldsMissingDatabase);
            $strError .= '|| Insert-File: ' . implode(", ", $arrFieldsMissingInsert);

            throw new Exception($strError);
        }

        // Import
        $arrKnownIDs = \Database::getInstance()->query("SELECT id FROM $strTable")->fetchEach('id');

        $arrUpdate = array();
        $arrInsert = array();

        // Split in update/insert
        foreach ($arrData as $mixKey => $arrValues) {
            if (in_array($arrValues['update']['id'], $arrKnownIDs)) {
                $arrUpdate[] = $arrValues['update'];
            } else {
                $arrInsert[] = $arrValues['insert'];
            }

            unset($arrData[$mixKey]);
        }

        $arrErrors = array();
        $intCount  = 0;

        // Update
        foreach ($arrUpdate as $key => $arrValues) {
            try {
                $intID = $arrValues['id'];
                unset($arrValues['id']);

                \Database::getInstance()->prepare("UPDATE $strTable %s WHERE id=?")
                         ->set($arrValues)
                         ->execute($intID);

                $intCount++;
            } catch (Exception $e) {
                $arrErrors[] = $e->getMessage();
            }
        }

        // Insert
        foreach ($arrInsert as $key => $arrValues) {
            try {
                \Database::getInstance()->prepare("INSERT INTO $strTable %s")
                         ->set($arrValues)
                         ->execute();

                $intCount++;
            } catch (Exception $e) {
                $arrErrors[] = $e->getMessage();
            }
        }

        if (empty($arrErrors)) {
            return true;
        } else {
            $arrErrors = array_keys(array_flip($arrErrors));
            throw new Exception(implode(' | ', $arrErrors));
        }
    }

    ////////////////////////////////////////////////////////////////////////////
    // XML Functions
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Return data as gzip xml file
     *
     * @param string          $strPath
     *
     * @param Database\Result $objData
     *
     * @param string          $strTable
     *
     * @param array           $arrOnlyInsert
     *
     * @return string
     */
    public function writeXML($strPath, $objData, $strTable, $arrOnlyInsert = array())
    {
        // Get fields
        $arrDatabaseFields = \Database::getInstance()->listFields($strTable);

        $arrDatabaseFieldsMeta = array();
        foreach ($arrDatabaseFields as $value) {
            if ($value["type"] == "index") {
                continue;
            }

            $arrDatabaseFieldsMeta[$value["name"]] = $value;
        }

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

        foreach ($objData->fetchAllAssoc() as $arrRow) {
            // Write to xml if memory limit hit
            if ($this->intMaxMemoryUsage < memory_get_usage(true)) {
                $strXMLFlush = $objXml->flush(true);
                gzputs($objGzFile, $strXMLFlush, strlen($strXMLFlush));
            }

            // Get the id for filtering
            $arrFieldListForInsert = array();
            if (array_key_exists('all', $arrOnlyInsert)) {
                $arrFieldListForInsert = array_merge($arrFieldListForInsert, $arrOnlyInsert['all']);
            }

            if (array_key_exists($arrRow['id'], $arrOnlyInsert)) {
                $arrFieldListForInsert = array_merge($arrFieldListForInsert, $arrOnlyInsert[$arrRow['id']]);
            }

            $objXml->startElement('row');

            foreach ($arrRow as $strField => $mixvalue) {
                $objXml->startElement('data');
                $objXml->writeAttribute('name', $strField);

                // Adding flag for only insert
                if (in_array($strField, $arrFieldListForInsert)) {
                    $objXml->writeAttribute('onlyInsert', true);
                }

                // Write empty data.
                if ($mixvalue == '') {
                    if ($arrDatabaseFieldsMeta[$strField]['default'] === null) {
                        $objXml->writeAttribute("type", "null");
                        $objXml->text('');
                    } else {
                        switch (strtolower($arrDatabaseFieldsMeta[$strField]['type'])) {
                            case 'binary':
                            case 'varbinary':
                            case 'blob':
                            case 'tinyblob':
                            case 'mediumblob':
                            case 'longblob':
                                $objXml->writeAttribute("type", "blob");
                                $objXml->writeCdata(base64_encode($arrDatabaseFieldsMeta[$strField]['default']));
                                break;

                            case 'tinyint':
                            case 'smallint':
                            case 'mediumint':
                            case 'int':
                            case 'integer':
                            case 'bigint':
                                $objXml->writeAttribute("type", "int");
                                $objXml->text($arrDatabaseFieldsMeta[$strField]['default']);
                                break;

                            case 'float':
                            case 'double':
                            case 'real':
                            case 'decimal':
                            case 'numeric':
                                $objXml->writeAttribute("type", "decimal");
                                $objXml->text($arrDatabaseFieldsMeta[$strField]['default']);
                                break;

                            case 'date':
                            case 'datetime':
                            case 'timestamp':
                            case 'time':
                            case 'year':
                                $objXml->writeAttribute("type", "date");
                                $objXml->text($arrDatabaseFieldsMeta[$strField]['default']);
                                break;

                            case 'char':
                            case 'varchar':
                            case 'text':
                            case 'tinytext':
                            case 'mediumtext':
                            case 'longtext':
                            case 'enum':
                            case 'set':
                                $objXml->writeAttribute("type", "text");
                                $objXml->writeCdata(base64_encode($arrDatabaseFieldsMeta[$strField]['default']));
                                break;

                            default:
                                $objXml->writeAttribute("type", "default");
                                $objXml->writeCdata(base64_encode($arrDatabaseFieldsMeta[$strField]['default']));
                                break;
                        }
                    }
                } // Write data.
                else {
                    switch (strtolower($arrDatabaseFieldsMeta[$strField]['type'])) {
                        case 'binary':
                        case 'varbinary':
                        case 'blob':
                        case 'tinyblob':
                        case 'mediumblob':
                        case 'longblob':
                            $objXml->writeAttribute("type", "blob");
                            $objXml->writeCdata(base64_encode($mixvalue));
                            break;

                        case 'tinyint':
                        case 'smallint':
                        case 'mediumint':
                        case 'int':
                        case 'integer':
                        case 'bigint':
                            $objXml->writeAttribute("type", "int");
                            $objXml->text($mixvalue);
                            break;

                        case 'float':
                        case 'double':
                        case 'real':
                        case 'decimal':
                        case 'numeric':
                            $objXml->writeAttribute("type", "decimal");
                            $objXml->text($mixvalue);
                            break;

                        case 'date':
                        case 'datetime':
                        case 'timestamp':
                        case 'time':
                        case 'year':
                            $objXml->writeAttribute("type", "date");
                            $objXml->text($mixvalue);
                            break;

                        case 'char':
                        case 'varchar':
                        case 'text':
                        case 'tinytext':
                        case 'mediumtext':
                        case 'longtext':
                        case 'enum':
                        case 'set':
                            $objXml->writeAttribute("type", "text");
                            $objXml->writeCdata(base64_encode($mixvalue));
                            break;

                        default:
                            $objXml->writeAttribute("type", "default");
                            $objXml->writeCdata(base64_encode($mixvalue));
                            break;
                    }
                }

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
        if (!file_exists(TL_ROOT . "/" . $strImportPath)) {
            throw new Exception('File not found: ' . $strImportPath);
        }

        // Write some tempfiles
        $strRandomToken = substr(md5(time() . " | " . rand(0, 65535)), 0, 8);
        $strTempPath    = $this->objSyncCtoHelper->standardizePath(
            $GLOBALS['SYC_PATH']['tmp'],
            "SyncCtoPro-SE-$strRandomToken.xml"
        );

        // Unzip XML
        $objGzFile = gzopen(TL_ROOT . "/" . $strImportPath, "r");

        // Write xml file
        $objXMLFile = new File($strTempPath);
        $objXMLFile->write("");

        while (true) {
            $strConten = gzread($objGzFile, 500000);

            if ($strConten == false || empty($strConten)) {
                break;
            }

            $objXMLFile->append($strConten, "");
        }

        // Close zip archive
        $objXMLFile->close();
        gzclose($objGzFile);

        $arrData = array(
            'table'  => '',
            'data'   => array(),
            'fields' => array()
        );

        $strCurrentAttribute     = '';
        $strCurrentAttributeType = '';
        $blnOnlyInsert           = false;
        $blnInData               = false;
        $blnInTable              = false;
        $intI                    = 0;

        //  Check if the document is valid.
        $objXMLReaderValidate = new XMLReader();
        $objXMLReaderValidate->open(TL_ROOT . "/" . $strTempPath);
        $objXMLReaderValidate->setParserProperty(XMLReader::VALIDATE, true);
        if (!$objXMLReaderValidate->isValid()) {
            throw new \RuntimeException('Not a valid XML.');
        }
        $objXMLReaderValidate->close();

        // Read XML
        $objXMLReader = new XMLReader();
        $objXMLReader->open(TL_ROOT . "/" . $strTempPath);

        while ($objXMLReader->read()) {
            switch ($objXMLReader->nodeType) {
                // Values
                case XMLReader::TEXT:
                case XMLReader::CDATA:
                    if ($blnInData) {
                        if (in_array($strCurrentAttributeType, array("null", "empty"))) {
                            // Nothing to do
                        } elseif (in_array($strCurrentAttributeType, array("text", "blob", "default"))) {
                            $mixValue                                               = base64_decode($objXMLReader->value);
                            $arrData['data'][$intI]['insert'][$strCurrentAttribute] = $mixValue;

                            if (!$blnOnlyInsert) {
                                $arrData['data'][$intI]['update'][$strCurrentAttribute] = $mixValue;
                            }
                        } else {
                            $arrData['data'][$intI]['insert'][$strCurrentAttribute] = $objXMLReader->value;

                            if (!$blnOnlyInsert) {
                                $arrData['data'][$intI]['update'][$strCurrentAttribute] = $objXMLReader->value;
                            }
                        }
                    } elseif ($blnInTable) {
                        $arrData['table'] = $objXMLReader->value;
                    }
                    break;

                // Element
                case XMLReader::ELEMENT:
                    switch ($objXMLReader->localName) {
                        // Start data
                        case 'data':
                            // Get meta data.
                            $strCurrentAttribute                                    = $objXMLReader->getAttribute('name');
                            $strCurrentAttributeType                                = $objXMLReader->getAttribute("type");
                            $arrData['fields'][$objXMLReader->getAttribute('name')] = 1;
                            $blnInData                                              = true;

                            // Check if we have only the insert mode for this field.
                            if ($objXMLReader->getAttribute('onlyInsert') == true) {
                                $blnOnlyInsert = true;
                            } else {
                                $blnOnlyInsert = false;
                            }

                            // If empty is set, add the empty value to the array.
                            if ($strCurrentAttributeType == 'empty') {
                                $arrData['data'][$intI]['insert'][$strCurrentAttribute] = '';

                                if (!$blnOnlyInsert) {
                                    $arrData['data'][$intI]['update'][$strCurrentAttribute] = '';
                                }
                            } // If empty is set, add the empty value to the array.
                            elseif ($strCurrentAttributeType == 'null') {
                                $arrData['data'][$intI]['insert'][$strCurrentAttribute] = null;

                                if (!$blnOnlyInsert) {
                                    $arrData['data'][$intI]['update'][$strCurrentAttribute] = null;
                                }
                            }
                            break;

                        case 'table':
                            $blnInTable = true;
                            break;
                    }
                    break;

                // Element end
                case XMLReader::END_ELEMENT:
                    switch ($objXMLReader->localName) {
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

    ////////////////////////////////////////////////////////////////////////////
    // Hash Functions
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Get hashes for a table and/or special ids
     *
     * @param string $strTable Name of table
     *
     * @param array  $arrIds   List with ids
     *
     * @return array The list with all id's as key and the hashes as values.
     */
    public function getHashValueFor($strTable, $arrIds = array())
    {
        // Get a list for ignored fields
        $arrFieldFilter = $this->getIgnoredFieldsFor($strTable);
        $arrFields      = \Database::getInstance()->getFieldNames($strTable);
        foreach ($arrFieldFilter as $strField) {
            if (($strKey = array_search($strField, $arrFields)) !== false) {
                unset($arrFields[$strKey]);
            }
        }

        // Build array with 'IFNULL'
        $arrExtendedFields = array();
        foreach ($arrFields as $strField) {
            $arrExtendedFields[] = "IFNULL(`$strField`, '')";
        }

        // Build base SQL.
        $sql = sprintf
        (
            "SELECT id AS row_id, md5(CONCAT_WS('|', %s)) AS hash FROM %s",
            implode(", ", $arrExtendedFields),
            $strTable
        );

        // Build where with the id's.
        if (is_array($arrIds) && !empty($arrIds)) {
            $sql .= ' WHERE `id` IN (' . implode(', ', $arrIds) . ')';
        }

        // Get data from the database.
        $result = \Database::getInstance()
                           ->prepare($sql)
                           ->execute()
                           ->fetchAllAssoc();

        // Build a nice array.
        $return = array();
        foreach ($result as $row) {
            $id          = $row['row_id'];
            $return[$id] = array_merge(array('table' => $strTable), $row);
        }

        return $return;
    }

    ////////////////////////////////////////////////////////////////////////////
    // Trigger Functions
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Try to remove all triggers.
     *
     * @param mixed $mixValues
     *
     * @return mixed
     */
    public function removeTriggerFromHook($mixValues = null)
    {
        try {
            $this->runRemoveTrigger('tl_page');
            $this->runRemoveTrigger('tl_article');
            $this->runRemoveTrigger('tl_content');
        } catch (Exception $exc) {
            $this->addErrorMessage($GLOBALS['TL_LANG']['ERR']['trigger_delete'] . '<br/>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['trigger_information']);
            $this->log('There was an error by deleting the triggers for SyncCtoPro. Error: ' . $exc->getMessage(),
                __CLASS__ . " | " . __FUNCTION__, TL_ERROR);
        }

        return $mixValues;
    }

    /**
     * Remove all triggers from a chosen tabel.
     *
     * @param string $strTable Name of the tabel.
     */
    protected function runRemoveTrigger($strTable)
    {
        // Drop
        $strQuery = "DROP TRIGGER IF EXISTS `" . $strTable . "_AfterUpdateHashRefresh`";
        \Database::getInstance()->query($strQuery);

        // Drop
        $strQuery = "DROP TRIGGER IF EXISTS `" . $strTable . "_AfterInsertHashRefresh`";
        \Database::getInstance()->query($strQuery);

        // Drop
        $strQuery = "DROP TRIGGER IF EXISTS `" . $strTable . "_AfterDeleteHashRefresh`";
        \Database::getInstance()->query($strQuery);
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

}
