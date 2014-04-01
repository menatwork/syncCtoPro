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
        $this->intMaxMemoryUsage = intval(str_replace(array("m", "M", "k", "K"), array("000000", "000000", "000", "000"), ini_get('memory_limit')));
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
        if (!is_object(self::$objInstance))
        {
            self::$objInstance = new self();
        }

        return self::$objInstance;
    }

    ////////////////////////////////////////////////////////////////////////////
    // DbInstaller Hooks
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Clear the DbInstaller from contao
     * 
     * @param array $arrComments
     * @return array
     */
    public function clearDbInstaller($arrComments)
    {
        if (count($arrComments['ALTER_CHANGE']))
        {
            foreach ($arrComments['ALTER_CHANGE'] as $key => $value)
            {
                if ($value == "ALTER TABLE `tl_synccto_diff` DROP INDEX `keys`, ADD UNIQUE KEY `keys` (`table`,`row_id`);")
                {
                    unset($arrComments['ALTER_CHANGE'][$key]);
                }
            }

            if (count($arrComments['ALTER_CHANGE']) == 0)
            {
                unset($arrComments['ALTER_CHANGE']);
            }
        }

        return $arrComments;
    }

    ////////////////////////////////////////////////////////////////////////////
    // Delete Functions
    ////////////////////////////////////////////////////////////////////////////

    public function deleteEntries($strTable, $arrIds = array())
    {
        if (empty($arrIds))
        {
            return false;
        }

        if (!\Database::getInstance()->tableExists($strTable))
        {
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
     * @param string $strPath Path for writing the file, if empty a tmp path will be created
     * @param string $strTable Name of Table
     * @param array $arrIds List of ids
     * @param array $arrFields List of fields
     * 
     * @return boolean|string
     */
    public function getDataForAsFile($strPath, $strTable, $arrIds = null, $arrFields = null, $arrOnlyInsert = array())
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

        return $this->writeXML($strPath, $objData, $strTable, $arrOnlyInsert);
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
        if (!\Database::getInstance()->tableExists($strTable))
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

        $objData = \Database::getInstance()->query("SELECT $strFields FROM $strTable $strWhere ORDER BY id");

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


        if (empty($strTable) || !\Database::getInstance()->tableExists($strTable))
        {
            throw new Exception('Error by import data. Unknown or empty tablename: ' . $arrData['table']);
        }

        if (empty($arrData))
        {
            return 'Import/Update 0 Rows';
        }

        // Check fields
        $arrKnownFields = \Database::getInstance()->getFieldNames($strTable);

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
        $arrKnownIDs = \Database::getInstance()->query("SELECT id FROM $strTable")->fetchEach('id');

        $arrUpdate = array();
        $arrInsert = array();

        // Split in update/insert
        foreach ($arrData as $mixKey => $arrValues)
        {
            if (in_array($arrValues['update']['id'], $arrKnownIDs))
            {
                $arrUpdate[] = $arrValues;
            }
            else
            {
                $arrInsert[] = $arrValues;
            }

            unset($arrData[$mixKey]);
        }

        $arrErrors = array();
        $intCount = 0;

        // Update
        foreach ($arrUpdate as $key => $arrValues)
        {
            try
            {
                $intID = $arrValues['update']['id'];
                unset($arrValues['update']['id']);

                \Database::getInstance()->prepare("UPDATE $strTable %s WHERE id=?")
                    ->set($arrValues['update'])
                    ->execute($intID);
                $intCount++;
            }
            catch (Exception $e)
            {
                $arrErrors[] = $e->getMessage();
            }
        }

        // Insert
        foreach ($arrInsert as $key => $arrValues)
        {
            try
            {
                \Database::getInstance()->prepare("INSERT INTO $strTable %s")
                    ->set($arrValues['insert'])
                    ->execute();
                $intCount++;
            }
            catch (Exception $e)
            {
                $arrErrors[] = $e->getMessage();
            }
        }



        if(empty($arrErrors))
        {
            return true;
        }
        else
        {
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
     * @param Database_Result $objData
     * @param stirng $strTable
     * 
     * @return string
     */
    public function writeXML($strPath, $objData, $strTable, $arrOnlyInsert = array())
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
            // Write to xml if memory limit hit
            if ($this->intMaxMemoryUsage < memory_get_usage(true))
            {
                $strXMLFlush = $objXml->flush(true);
                gzputs($objGzFile, $strXMLFlush, strlen($strXMLFlush));
            }

            // Get the id for filtering            
            $arrFieldListForInsert = array();
            if (array_key_exists('all', $arrOnlyInsert))
            {
                $arrFieldListForInsert = array_merge($arrFieldListForInsert, $arrOnlyInsert['all']);
            }

            if (array_key_exists($arrRow['id'], $arrOnlyInsert))
            {
                $arrFieldListForInsert = array_merge($arrFieldListForInsert, $arrOnlyInsert[$arrRow['id']]);
            }

            $objXml->startElement('row');

            foreach ($arrRow as $strField => $mixvalue)
            {
                $objXml->startElement('data');
                $objXml->writeAttribute('name', $strField);

                // Adding flag for only insert
                if (in_array($strField, $arrFieldListForInsert))
                {
                    $objXml->writeAttribute('onlyInsert', true);
                }

                $objXml->writeCdata(base64_encode($mixvalue));
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

        // Close zip archive
        gzclose($objGzFile);

        $arrData = array(
            'table' => '',
            'data'  => array(),
            'fields' => array()
        );
        $strCurrentAttribute = '';
        $blnOnlyInsert       = false;
        $blnInData           = false;
        $blnInTable          = false;
        $intI                = 0;

        //  Check if the document is valid.
        $objXMLReaderValidate = new XMLReader();
        $objXMLReaderValidate->open(TL_ROOT . "/" . $strTempPath);
        $objXMLReaderValidate->setParserProperty(XMLReader::VALIDATE, true);
        if(!$objXMLReaderValidate->isValid())
        {
            throw new \RuntimeException('Not a valid XML.');
        }
        $objXMLReaderValidate->close();

        // Read XML
        $objXMLReader = new XMLReader();
        $objXMLReader->open(TL_ROOT . "/" . $strTempPath);

        while ($objXMLReader->read())
        {
            $arrfooBaa[] = $objXMLReader->nodeType;
            switch ($objXMLReader->nodeType)
            {
                // Values
                case XMLReader::TEXT:
                case XMLReader::CDATA:
                    if ($blnInData)
                    {
                        // Check if the field is only for insert
                        if ($blnOnlyInsert)
                        {
                            $arrData['data'][$intI]['insert'][$strCurrentAttribute] = base64_decode($objXMLReader->value);
                        }
                        else
                        {
                            $mixValue = base64_decode($objXMLReader->value);
                            $arrData['data'][$intI]['insert'][$strCurrentAttribute] = $mixValue;
                            $arrData['data'][$intI]['update'][$strCurrentAttribute] = $mixValue;
                        }
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

                            if ($objXMLReader->getAttribute('onlyInsert') == true)
                            {
                                $blnOnlyInsert = true;
                            }
                            else
                            {
                                $blnOnlyInsert = false;
                            }
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
        $objXMLFile->close();

        return $arrData;
    }

    ////////////////////////////////////////////////////////////////////////////
    // Hash Functions
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Get hashes for a table and/or special ids
     * 
     * @param string $strTable Name of table
     * @param array $arrIds List with ids
     * @return type
     */
    public function getHashValueFor($strTable, $arrIds = array())
    {
        // Build Where
        $strWhere = "WHERE `table` = '$strTable'";

        if (is_array($arrIds) && !empty($arrIds))
        {
            $strWhere.= ' AND `row_id` IN (' . implode(', ', $arrIds) . ')';
        }

        // DB
        $arrResult = $this->Database
                ->prepare("SELECT * FROM tl_synccto_diff $strWhere")
                ->execute()
                ->fetchAllAssoc();

        $arrReturn = array();

        foreach ($arrResult as $arrValues)
        {
            $arrReturn[$arrValues['row_id']] = $arrValues;
        }

        return $arrReturn;
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
        try
        {
            $this->runRemoveTrigger('tl_page');
            $this->runRemoveTrigger('tl_article');
            $this->runRemoveTrigger('tl_content');
        }
        catch (Exception $exc)
        {
            $this->addErrorMessage($GLOBALS['TL_LANG']['ERR']['trigger_delete'] . '<br/>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['trigger_information']);
            $this->log('There was an error by deleting the triggers for SyncCtoPro. Error: ' . $exc->getMessage(), __CLASS__ . " | " . __FUNCTION__, TL_ERROR);
        }

        if ($mixValues != null)
        {
            return $mixValues;
        }
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
     * Call this for hooks
     */
    public function updateTriggerFromHook($mixValues = null)
    {
        try
        {
            $this->updateTrigger(true);
        }
        catch (Exception $exc)
        {
            $this->addErrorMessage($GLOBALS['TL_LANG']['ERR']['trigger_update'] . '<br/>' . $GLOBALS['TL_LANG']['tl_syncCto_check']['trigger_information']);
            $this->log('There was an error by updating the triggers for SyncCtoPro. Error: ' . $exc->getMessage(), __CLASS__ . " | " . __FUNCTION__, TL_ERROR);
        }

        if($mixValues != null)
        {
            return $mixValues;
        }
    }

    /**
     * Update all trigger
     * 
     * @param boolean $blnUpdate
     */
    public function updateTrigger($blnUpdate = false)
    {
        $this->triggerPage($blnUpdate);
        $this->triggerArticle($blnUpdate);
        $this->triggerContent($blnUpdate);
    }
    
    /**
     * Update all trigger
     * 
     * @param boolean $blnUpdate
     */
    public function updateSpecialTriggers($blnPage, $blnArticle, $blnContent, $blnUpdate = false)
    {
        if($blnPage)
        {
            $this->triggerPage($blnUpdate);
        }
        
        if($blnArticle)
        {
            $this->triggerArticle($blnUpdate);
        }
        
        if($blnContent)
        {
            $this->triggerContent($blnUpdate);
        }
    }

    /**
     * Update Trigger for tl_page
     * 
     * @param boolean $blnUpdate
     */
    protected function triggerPage($blnUpdate = false)
    {
        // Get a list for ignored fields
        $arrIgnoredFields = $this->getIgnoredFieldsFor('tl_page');

        // Update trigger
        $this->runUpdateTrigger('tl_page', $arrIgnoredFields);
        $this->runInsertTrigger('tl_page', $arrIgnoredFields);
        $this->runDeleteTrigger('tl_page');

        if ($blnUpdate)
        {
            $this->runUpdateHashes('tl_page');
        }
    }

    /**
     * Update Trigger for tl_page
     * 
     * @param boolean $blnUpdate
     */
    protected function triggerArticle($blnUpdate = false)
    {
        // Get a list for ignored fields
        $arrIgnoredFields = $this->getIgnoredFieldsFor('tl_article');

        // Update trigger
        $this->runUpdateTrigger('tl_article', $arrIgnoredFields);
        $this->runInsertTrigger('tl_article', $arrIgnoredFields);
        $this->runDeleteTrigger('tl_article');

        if ($blnUpdate)
        {
            $this->runUpdateHashes('tl_article');
        }
    }

    /**
     * Update Trigger for tl_page
     * 
     * @param boolean $blnUpdate
     */
    protected function triggerContent($blnUpdate = false)
    {
        // Get a list for ignored fields
        $arrIgnoredFields = $this->getIgnoredFieldsFor('tl_content');

        // Update trigger
        $this->runUpdateTrigger('tl_content', $arrIgnoredFields);
        $this->runInsertTrigger('tl_content', $arrIgnoredFields);
        $this->runDeleteTrigger('tl_content');

        if ($blnUpdate)
        {
            $this->runUpdateHashes('tl_content');
        }
    }

    /**
     * Run a update for each row in page/article/content
     * 
     * @param string $strTable
     */
    protected function runUpdateHashes($strTable)
    {
        if (!\Database::getInstance()->fieldExists('syncCto_hash', $strTable))
        {
            return;
        }

        $strQuery = "UPDATE " . $strTable . " SET syncCto_hash = now()";
        \Database::getInstance()->query($strQuery);
    }

    /**
     * Update the trigger
     * 
     * @param string $strTable Name of table
     * @param array $arrFieldFilter List with ignored fields
     * @param boolean $blnUpdate Run update after refresh trigger
     */
    protected function runUpdateTrigger($strTable, $arrFieldFilter)
    {
        // Get field list
        $arrFields = \Database::getInstance()->getFieldNames($strTable);
        foreach ($arrFieldFilter as $strField)
        {
            if (($strKey = array_search($strField, $arrFields)) !== false)
            {
                unset($arrFields[$strKey]);
            }
        }

        // Build array with 'IFNULL'
        $arrExtendetFields = array();
        foreach ($arrFields as $strField)
        {
            $arrExtendetFields[] = "IFNULL(`$strField`, '')";
        }

        // Drop
        $strQuery = "DROP TRIGGER IF EXISTS `" . $strTable . "_AfterUpdateHashRefresh`";
        \Database::getInstance()->query($strQuery);

        // Create
        $strQuery = "
            CREATE TRIGGER `" . $strTable . "_AfterUpdateHashRefresh` AFTER UPDATE ON $strTable FOR EACH ROW
            BEGIN
            
            INSERT INTO tl_synccto_diff (`table`,`row_id`,`hash`) 
            VALUES ('" . $strTable . "', NEW.id, (SELECT md5(CONCAT_WS('|', " . implode(", ", $arrExtendetFields) . ")) FROM " . $strTable . " WHERE id = NEW.id)) 
            ON DUPLICATE KEY UPDATE hash = (SELECT md5(CONCAT_WS('|', " . implode(", ", $arrExtendetFields) . ")) FROM " . $strTable . " WHERE id = NEW.id); 

            END
            ";
        \Database::getInstance()->query($strQuery);
    }

    /**
     * Update the trigger
     * 
     * @param string $strTable Name of table
     * @param array $arrFieldFilter List with ignored fields
     * @param boolean $blnUpdate Run update after refresh trigger
     */
    protected function runInsertTrigger($strTable, $arrFieldFilter)
    {
        // Get field list
        $arrFields = \Database::getInstance()->getFieldNames($strTable);
        foreach ($arrFieldFilter as $strField)
        {
            if (($strKey = array_search($strField, $arrFields)) !== false)
            {
                unset($arrFields[$strKey]);
            }
        }

        // Build array with 'IFNULL'
        $arrExtendetFields = array();
        foreach ($arrFields as $strField)
        {
            $arrExtendetFields[] = "IFNULL(`$strField`, '')";
        }

        // Drop
        $strQuery = "DROP TRIGGER IF EXISTS `" . $strTable . "_AfterInsertHashRefresh`";
        \Database::getInstance()->query($strQuery);

        // Create
        $strQuery = "
            CREATE TRIGGER `" . $strTable . "_AfterInsertHashRefresh` AFTER INSERT ON $strTable FOR EACH ROW
            BEGIN
            
            INSERT INTO tl_synccto_diff (`table`,`row_id`,`hash`) 
            VALUES ('$strTable', NEW.id, (SELECT md5(CONCAT_WS('|', " . implode(", ", $arrExtendetFields) . ")) FROM " . $strTable . " WHERE id = NEW.id)) 
            ON DUPLICATE KEY UPDATE hash = (SELECT md5(CONCAT_WS('|', " . implode(", ", $arrExtendetFields) . ")) FROM " . $strTable . " WHERE id = NEW.id); 

            END
            ";
        \Database::getInstance()->query($strQuery);
    }

    /**
     * Update the trigger
     * 
     * @param string $strTable Name of table
     * @param array $arrFieldFilter List with ignored fields
     * @param boolean $blnUpdate Run update after refresh trigger
     */
    protected function runDeleteTrigger($strTable)
    {
        // Drop
        $strQuery = "DROP TRIGGER IF EXISTS `" . $strTable . "_AfterDeleteHashRefresh`";
        \Database::getInstance()->query($strQuery);

        // Create
        $strQuery = "
            CREATE TRIGGER `" . $strTable . "_AfterDeleteHashRefresh` AFTER DELETE ON $strTable FOR EACH ROW
            BEGIN
            
            DELETE FROM tl_synccto_diff WHERE `row_id` = OLD.id AND `table` = '$strTable';
            
            END
            ";
        \Database::getInstance()->query($strQuery);
    }

    ////////////////////////////////////////////////////////////////////////////
    // Helper Functions
    ////////////////////////////////////////////////////////////////////////////

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
        if (array_key_exists('all', $GLOBALS['SYC_CONFIG']['trigger_blacklist']))
        {
            $arrReturn = array_merge($arrReturn, $GLOBALS['SYC_CONFIG']['trigger_blacklist']['all']);
        }

        // Get special Values
        if (array_key_exists($strTable, $GLOBALS['SYC_CONFIG']['trigger_blacklist']))
        {
            $arrReturn = array_merge($arrReturn, $GLOBALS['SYC_CONFIG']['trigger_blacklist'][$strTable]);
        }

        $arrUserSettings = array();
        foreach ((array) deserialize($GLOBALS['TL_CONFIG']['syncCto_diff_blacklist']) as $key => $value)
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

        return array_unique($arrReturn);
    }

}