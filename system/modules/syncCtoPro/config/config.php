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
 * Backend
 */
$GLOBALS['BE_MOD']['syncCto']['synccto_clients']['icon'] = 'system/modules/syncCtoPro/html/icons/iconClients.png';

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['sqlCompileCommands'][] = array('SyncCtoProDatabase', 'clearDbInstaller');
$GLOBALS['TL_HOOKS']['sqlCompileCommands'][] = array('SyncCtoProDatabase', 'updateTriggerFromHook');
$GLOBALS['TL_HOOKS']['syncExecuteFinalOperations'][] = array('SyncCtoProDatabase', 'updateTriggerFromHook');

/**
 * Ignored fields for trigger 
 */
$GLOBALS['SYC_CONFIG']['trigger_blacklist'] = array_merge((array) $GLOBALS['SYC_CONFIG']['trigger_blacklist'], array(
    'id',
    'tstamp',
    'syncCto_hash',
    'PRIMARY'
));

/**
 * Ignored tables for sync database
 */
$GLOBALS['SYC_CONFIG']['table_hidden'] = array_merge( (array) $GLOBALS['SYC_CONFIG']['table_hidden'], array(
    'tl_synccto_diff'
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