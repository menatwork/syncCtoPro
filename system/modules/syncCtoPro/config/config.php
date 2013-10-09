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
$GLOBALS['TL_HOOKS']['sqlCompileCommands'][]        = array('SyncCtoProDatabase', 'clearDbInstaller');
$GLOBALS['TL_HOOKS']['sqlCompileCommands'][]        = array('SyncCtoProDatabase', 'updateTriggerFromHook');
$GLOBALS['TL_HOOKS']['syncAdditionalFunctions'][]   = array('SyncCtoStepDatabaseDiff', 'remoteUpdateHashes');
$GLOBALS['TL_HOOKS']['syncAdditionalFunctions'][]   = array('SyncCtoStepDatabaseDiff', 'localeUpdateTimestamp');
$GLOBALS['TL_HOOKS']['syncDBUpdateBeforeDrop'][]    = array('SyncCtoProSystem', 'preventBlacklistValues');

/**
 * Ignored fields for trigger / hash 
 */
$GLOBALS['SYC_CONFIG']['trigger_blacklist'] = array_merge_recursive((array) $GLOBALS['SYC_CONFIG']['trigger_blacklist'], array(
    'all' => array(
        'id',
        'pid',
        'sorting',
        'tstamp',
        'syncCto_hash',
        'PRIMARY'
    ),
    'tl_page' => array(
        'dns',
    ),
));

/**
 * Ignored fields for sync 
 */
$GLOBALS['SYC_CONFIG']['sync_blacklist'] = array_merge_recursive((array) $GLOBALS['SYC_CONFIG']['sync_blacklist'], array(
    'all' => array(
        'PRIMARY',
    ),
    'tl_page' => array(
        'dns',
    ),
));

/**
 * Ignored tables for sync database
 */
$GLOBALS['SYC_CONFIG']['table_hidden'] = array_merge((array) $GLOBALS['SYC_CONFIG']['table_hidden'], array(
    'tl_synccto_diff'
));

/**
 * CtoCommunication RPC Calls
 */

// Refresh all hashes
$GLOBALS["CTOCOM_FUNCTIONS"]["SYNCCTOPRO_REFRESH_HASHES"] = array(
    "class"     => "SyncCtoProDatabase",
    "function"  => "updateSpecialTriggers",
    "typ"       => "POST",
    "parameter" => array(
        "page",
        "article",
        "content",
        "update"
    ),
);

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
$GLOBALS["CTOCOM_FUNCTIONS"]["SYNCCTOPRO_DATABASE_SE_EXPORT"] = array(
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

// Delete SE
$GLOBALS["CTOCOM_FUNCTIONS"]["SYNCCTOPRO_DATABASE_SE_DELETE"] = array(
    "class"     => "SyncCtoProDatabase",
    "function"  => "deleteEntries",
    "typ"       => "POST",
    "parameter" => array(
        "table",
        "ids"
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

// Check ER status
$GLOBALS["CTOCOM_FUNCTIONS"]["SYNCCTOPRO_CHECK_ER"] = array(
    "class"     => "SyncCtoProSystem",
    "function"  => "checkERData",
    "typ"       => "GET",
    "parameter" => null
);

// Check ER status
$GLOBALS["CTOCOM_FUNCTIONS"]["SYNCCTOPRO_CHECK_HASH"] = array(
    "class"     => "SyncCtoProSystem",
    "function"  => "checkHash",
    "typ"       => "GET",
    "parameter" => null
);
?>