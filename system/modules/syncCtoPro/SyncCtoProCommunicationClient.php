<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2013 
 * @package    syncCto
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

        return $this->runServer('SYNCCTOPRO_DATABASE_SE_EMPORT', $arrData);
    }

}

?>