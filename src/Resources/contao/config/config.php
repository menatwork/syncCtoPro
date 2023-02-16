<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2014
 * @package    syncCtoPro
 * @license    EULA
 * @filesource
 */

/**
 * Backend
 */
$GLOBALS['BE_MOD']['syncCto']['synccto_clients']['icon'] = 'system/modules/syncCtoPro/assets/icons/iconClients.png';

/**
 * Permissions
 */
$GLOBALS['TL_PERMISSIONS'][] = 'syncCto_force_diff';
$GLOBALS['TL_PERMISSIONS'][] = 'syncCto_pagemounts';

/**
 * Hooks
 */
$GLOBALS['TL_HOOKS']['syncAdditionalFunctions'][] = array('SyncCtoStepDatabaseDiff', 'localeUpdateTimestamp');
$GLOBALS['TL_HOOKS']['syncDBUpdateBeforeDrop'][]  = array('SyncCtoProSystem', 'preventBlacklistValues');

/**
 * Ignored fields for trigger / hash
 */
$GLOBALS['SYC_CONFIG']['trigger_blacklist'] = array_merge_recursive(
    (array)($GLOBALS['SYC_CONFIG']['trigger_blacklist'] ?? [])
    , array(
        'all'     => array
        (
            'id',
            'tstamp',
            'syncCto_hash',
            'PRIMARY'
        ),
        'tl_page' => array
        (
            'dns',
        ),
    )
);

/**
 * Ignored fields for sync
 */
$GLOBALS['SYC_CONFIG']['sync_blacklist'] = array_merge_recursive(
    (array)($GLOBALS['SYC_CONFIG']['sync_blacklist'] ?? []),
    array(
        'all'     => array
        (
            'PRIMARY',
        ),
        'tl_page' => array
        (
            'dns',
        ),
    )
);

/**
 * Ignored tables for sync database
 */
$GLOBALS['SYC_CONFIG']['table_hidden'] = array_merge(
    (array)($GLOBALS['SYC_CONFIG']['table_hidden'] ?? []),
    array(
        'tl_synccto_diff'
    )
);

/**
 * CtoCommunication RPC Calls
 */

// Import as SE file
$GLOBALS["CTOCOM_FUNCTIONS"]["SYNCCTOPRO_DATABASE_SE_IMPORT"] = array
(
    "class"     => "SyncCtoProDatabase",
    "function"  => "setDataForAsFile",
    "typ"       => "POST",
    "parameter" => array
    (
        "path"
    ),
);

// Export as SE file
$GLOBALS["CTOCOM_FUNCTIONS"]["SYNCCTOPRO_DATABASE_SE_EXPORT"] = array
(
    "class"     => "SyncCtoProDatabase",
    "function"  => "getDataForAsFile",
    "typ"       => "POST",
    "parameter" => array
    (
        "path",
        "table",
        "ids",
        "fields",
    ),
);

// Delete SE
$GLOBALS["CTOCOM_FUNCTIONS"]["SYNCCTOPRO_DATABASE_SE_DELETE"] = array
(
    "class"     => "SyncCtoProDatabase",
    "function"  => "deleteEntries",
    "typ"       => "POST",
    "parameter" => array
    (
        "table",
        "ids"
    ),
);

// Get hashes
$GLOBALS["CTOCOM_FUNCTIONS"]["SYNCCTOPRO_DATABASE_GET_HASHES"] = array
(
    "class"     => "SyncCtoProDatabase",
    "function"  => "getHashValueFor",
    "typ"       => "POST",
    "parameter" => array
    (
        "table",
        "ids",
    ),
);

// Check ER status
$GLOBALS["CTOCOM_FUNCTIONS"]["SYNCCTOPRO_CHECK_ER"] = array
(
    "class"     => "SyncCtoProSystem",
    "function"  => "checkERData",
    "typ"       => "GET",
    "parameter" => null
);

// Check ER status
$GLOBALS["CTOCOM_FUNCTIONS"]["SYNCCTOPRO_CHECK_HASH"] = array
(
    "class"     => "SyncCtoProSystem",
    "function"  => "checkHash",
    "typ"       => "GET",
    "parameter" => null
);
