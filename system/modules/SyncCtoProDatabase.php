<?php

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
     * @param string $strTable
     * @param array $arrIds
     * @param array $arrFields
     * 
     * @return boolean|string
     */
    public function getDataForAsFile($strTable, $arrIds = null, $arrFields = null)
    {
        $objData = $this->getDataFor($strTable, $arrIds, $arrFields);

        if ($objData === false)
        {
            return false;
        }

        return $this->writeXML($objData, $strTable);
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
    protected function writeXML(Database_Result $objData, $strTable)
    {
        // Write some tempfiles
        $strRandomToken = md5(time() . " | " . rand(0, 65535));
        $strPath        = $this->objSyncCtoHelper->standardizePath($GLOBALS['SYC_PATH']['tmp'], "SyncCtoPro-SingleTableExport.$strRandomToken");

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

    public function readXML($strFile)
    {
        // Vars
        $strTableName = "";

        // Write some tempfiles
        $strRandomToken = md5(time() . " | " . rand(0, 65535));
        $strPath        = $this->objSyncCtoHelper->standardizePath($GLOBALS['SYC_PATH']['tmp'], "SyncCtoPro-SingleTableExport-$strRandomToken.xml");

        if (!file_exists(TL_ROOT . "/" . $strFile))
        {
            return false;
        }

        // Unzip XML
        $objGzFile = gzopen(TL_ROOT . "/" . $strFile, "r");

        $objXMLFile = new File($strPath);
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

        // Read XML
        $objXMLReader = new XMLReader();
        $objXMLReader->open(TL_ROOT . "/" . $strPath);

        while ($objXMLReader->read())
        {
            switch ($objXMLReader->nodeType)
            {
                case XMLReader::ELEMENT:
                    switch ($this->objXMLReader->localName)
                    {
                        case "table":
                            $strTableName = $this->objXMLReader->value;
                            break;
                    }
                    break;
            }
        }

        $objXMLFile->delete();


        return $arrRestoreTables;
    }

}

?>
