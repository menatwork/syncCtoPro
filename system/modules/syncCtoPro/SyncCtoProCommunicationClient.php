<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2013 
 * @package    syncCto Pro
 * @license    EULA
 * @filesource
 */

/**
 * Communication Class
 */
class SyncCtoProCommunicationClient
{
    ////////////////////////////////////////////////////////////////////////////
    // Vars
    ////////////////////////////////////////////////////////////////////////////

    /**
     * @var SyncCtoProCommunicationClient 
     */
    protected static $objInstance = null;

    /**
     * @var SyncCtoCommunicationClient 
     */
    protected $objSyncCtoCommunicationClient;

    ////////////////////////////////////////////////////////////////////////////
    // Core
    ////////////////////////////////////////////////////////////////////////////

    /**
     * construct
     */
    protected function __construct()
    {
        // Helper
        $this->objSyncCtoCommunicationClient = SyncCtoCommunicationClient::getInstance();
    }

    /**
     * @return SyncCtoProCommunicationClient
     */
    public static function getInstance()
    {
        if (!is_object(self::$objInstance))
        {
            self::$objInstance = new self();
        }

        return self::$objInstance;
    }

    /**
     * Call the basic communication class
     * 
     * @param string $name function name
     * @param mix $arguments
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array(array($this->objSyncCtoCommunicationClient, $name), $arguments);
    }

    ////////////////////////////////////////////////////////////////////////////
    // RPC Calls
    ////////////////////////////////////////////////////////////////////////////

    /**
     * Import a single export (SE) into db on client side.
     * 
     * @param string $strPath Path on client side
     * 
     * @return mixed
     */
    public function importDatabaseSE($strPath)
    {
        $arrData = array(
            array(
                "name"  => "path",
                "value" => $strPath,
            ),
        );

        return $this->runServer('SYNCCTOPRO_DATABASE_SE_IMPORT', $arrData);
    }

    /**
     * Return a xml file with a db dump
     * 
     * @param string $strPath Path for saving the file
     * @param string $strTable Name of table
     * @param array $arrIds lsit of ids
     * @param array $arrFields list of field names
     * 
     * @return string save name
     */
    public function exportDatabaseSE($strPath, $strTable, $arrIds = null, $arrFields = null)
    {
        $arrData = array(
            array(
                "name"  => "path",
                "value" => $strPath,
            ),
            array(
                "name"  => "table",
                "value" => $strTable,
            ),
            array(
                "name"  => "ids",
                "value" => $arrIds,
            ),
            array(
                "name"  => "fields",
                "value" => $arrFields,
            ),
        );

        return $this->runServer('SYNCCTOPRO_DATABASE_SE_EXPORT', $arrData);
    }

    /**
     * Get hashes for a special tablenmae and/or special ids
     * 
     * @param string $strTable Name of Table
     * @param array $arrIds List with IDs
     * 
     * @return array List with hashes
     */
    public function getHashValueFor($strTable, $arrIds = array())
    {
        $arrData = array(
            array(
                "name"  => "table",
                "value" => $strTable,
            ),
            array(
                "name"  => "ids",
                "value" => $arrIds,
            )
        );

        return $this->runServer('SYNCCTOPRO_DATABASE_GET_HASHES', $arrData);
    }

    /**
     * Get hashes for a special tablenmae and/or special ids
     * 
     * @param string $strTable Name of Table
     * @param array $arrIds List with IDs
     * 
     * @return array List with hashes
     */
    public function deleteEntries($strTable, $arrIds = array())
    {
        $arrData = array(
            array(
                "name"  => "table",
                "value" => $strTable,
            ),
            array(
                "name"  => "ids",
                "value" => $arrIds,
            )
        );

        return $this->runServer('SYNCCTOPRO_DATABASE_SE_DELETE', $arrData);
    }

    /**
     * Check the ER
     * 
     * @return boolean
     */
    public function checkER()
    {
        return $this->runServer('SYNCCTOPRO_CHECK_ER');
    }

    /**
     * Check the ER
     * 
     * @return boolean
     */
    public function checkHash()
    {
        return $this->runServer('SYNCCTOPRO_CHECK_HASH');
    }
    
    /**
     * Refresh all hashes from client
     * 
     * @return void
     */
    public function updateSpecialTriggers($blnPage, $blnArticle, $blnContent, $blnUpdate = false)
    {
        $arrData = array(
            array(
                "name"  => "page",
                "value" => $blnPage,
            ),
            array(
                "name"  => "article",
                "value" => $blnArticle,
            ),
            array(
                "name"  => "content",
                "value" => $blnContent,
            ),
            array(
                "name"  => "update",
                "value" => $blnUpdate,
            ),
        );        
        
        return $this->runServer('SYNCCTOPRO_REFRESH_HASHES', $arrData);
    }

}

?>