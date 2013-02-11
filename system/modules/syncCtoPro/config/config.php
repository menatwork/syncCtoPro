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
 * Hooks
 */
$GLOBALS['TL_HOOKS']['sqlCompileCommands'][] = array('SyncCtoProDatabase', 'updateTriggerFromHook');

/**
 * Ignored fields for trigger 
 */
$GLOBALS['SYC_CONFIG']['trigger_blacklist'] = array_merge((array) $GLOBALS['SYC_CONFIG']['trigger_blacklist'], array(
    'id',
    'tstamp',
    'synccto_hash',
    'PRIMARY'
));

/**
 * CtoCommunication RPC Calls
 */

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

// Get hashes
$GLOBALS["CTOCOM_FUNCTIONS"]["SYNCCTOPRO_DATABASE_GET_HASHES"] = array(
    "class"     => "SyncCtoProDatabase",
    "function"  => "getHashValueFor",
    "typ"       => "POST",
    "parameter" => array(
        "table",
        "ids",
    ),
);
?>