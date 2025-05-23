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
     * @var null|SyncCtoCommunicationClient
     */
    protected ?SyncCtoCommunicationClient $objSyncCtoCommunicationClient;

    ////////////////////////////////////////////////////////////////////////////
    // Core
    ////////////////////////////////////////////////////////////////////////////

    /**
     * construct
     */
    protected function __construct()
    {
        // Helper
        $this->objSyncCtoCommunicationClient = \SyncCtoCommunicationClient::getInstance();
    }

    /**
     * @return SyncCtoProCommunicationClient
     */
    public static function getInstance()
    {
        if (!is_object(self::$objInstance)) {
            self::$objInstance = new self();
        }

        return self::$objInstance;
    }

    /**
     * Call the basic communication class
     *
     * @param string $name function name
     *
     * @param mixed  $arguments
     *
     * @return mixed
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

        return $this
            ->objSyncCtoCommunicationClient
            ->run('SYNCCTOPRO_DATABASE_SE_IMPORT', $arrData)
        ;
    }

    /**
     * Return a xml file with a db dump.
     *
     * @param string     $strPath   Path for saving the file.
     *
     * @param string     $strTable  Name of table.
     *
     * @param array|null $arrIds    list of ids.
     *
     * @param array|null $arrFields list of field names.
     *
     * @return string save name
     *
     * @throws Exception
     */
    public function exportDatabaseSE(
        string $strPath,
        string $strTable,
        array  $arrIds = null,
        array  $arrFields = null
    ): string {
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

        return $this
            ->objSyncCtoCommunicationClient
            ->run('SYNCCTOPRO_DATABASE_SE_EXPORT', $arrData)
        ;
    }

    /**
     * Get hashes for a special tablenmae and/or special ids
     *
     * @param string $strTable Name of Table
     *
     * @param array  $arrIds   List with IDs
     *
     * @return array List with hashes
     *
     * @throws Exception
     */
    public function getHashValueFor(
        string $strTable,
        array  $arrIds = array()
    ): array {
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

        return $this
            ->objSyncCtoCommunicationClient
            ->run('SYNCCTOPRO_DATABASE_GET_HASHES', $arrData)
        ;
    }

    /**
     * Get hashes for a special tablenmae and/or special ids
     *
     * @param string $strTable Name of Table
     *
     * @param array  $arrIds   List with IDs
     *
     * @return array List with hashes
     *
     * @throws Exception
     */
    public function deleteEntries(
        string $strTable,
        array  $arrIds = array()
    ): array {
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

        return $this
            ->objSyncCtoCommunicationClient
            ->run('SYNCCTOPRO_DATABASE_SE_DELETE', $arrData)
        ;
    }

    /**
     * Check the ER
     *
     * @return boolean
     * @throws Exception
     */
    public function checkER(): bool
    {
        return $this
            ->objSyncCtoCommunicationClient
            ->run('SYNCCTOPRO_CHECK_ER')
        ;
    }

    /**
     * Check the ER
     *
     * @return boolean
     * @throws Exception
     */
    public function checkHash(): bool
    {
        return $this
            ->objSyncCtoCommunicationClient
            ->run('SYNCCTOPRO_CHECK_HASH')
        ;
    }
}
