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
 * Blacklists
 */
/**
 * CtoCommunication RPC Calls
 */
// Database single import/export -----------------------------------------------
// Import as SE file
$GLOBALS["CTOCOM_FUNCTIONS"]["SYNCCTOPRO_DATABASE_SE_IMPORT"] = array(
    "class"     => "SyncCtoProDatabase",
    "function"  => "setDataForAsFile",
    "typ"       => "POST",
    "parameter" => array(
        "path"
    ),
);
// Export as SE file
$GLOBALS["CTOCOM_FUNCTIONS"]["SYNCCTOPRO_DATABASE_SE_EMPORT"] = array(
    "class"     => "SyncCtoProDatabase",
    "function"  => "getDataForAsFile",
    "typ"       => "POST",
    "parameter" => array(
        "path",
        "table",
        "ids",
        "fields",
    ),
);
?>