<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2014
 * @package    syncCto
 * @license    GNU/LGPL
 * @filesource
 */

use Contao\Backend;

/**
 * Class for syncCto settings
 */
class SyncCtoProTableSettings extends Backend
{

    // Diff / Trigger ----------------------------------------------------------

    /**
     * Return a list with default values and
     * user values.
     *
     * @param string|array|null $mixValues
     *
     * @return array
     */
    public function loadDiffBlacklist(string|array|null $mixValues): array
    {
        if ($mixValues === null) {
            $mixValues = array();
        } elseif (!is_array($mixValues)) {
            $mixValues = (array) unserialize($mixValues);
        }

        $arrReturn = array();
        $arrKnownValues = array();

        // Get basic settings
        foreach ($GLOBALS['SYC_CONFIG']['trigger_blacklist'] ?? [] as $strTableName => $arrValues) {
            foreach ($arrValues as $strField) {
                $arrReturn[] = array(
                    'table' => $strTableName,
                    'entry' => $strField
                );

                $arrKnownValues[] = $strTableName . '::' . $strField;
            }
        }

        // Unset basic settings
        foreach ($mixValues as $key => $arrValues) {
            $strCheckValue = $arrValues['table'] . '::' . $arrValues['entry'];

            if (in_array($strCheckValue, $arrKnownValues)) {
                unset($mixValues[$key]);
            }
        }

        // Merge return.
        return array_merge($arrReturn, $mixValues);
    }

    /**
     * Return a list with default values and
     * user values.
     *
     * @param string|array|null $mixValues
     *
     * @return string
     */
    public function saveDiffBlacklist(string|array|null $mixValues): string
    {
        if ($mixValues === null) {
            $mixValues = array();
        } elseif (!is_array($mixValues)) {
            $mixValues = (array) unserialize($mixValues);
        }

        $arrKnownValues = array();

        // Get basic settings
        foreach ($GLOBALS['SYC_CONFIG']['trigger_blacklist'] ?? [] as $strTableName => $arrValues) {
            foreach ($arrValues as $strField) {
                $arrKnownValues[] = $strTableName . '::' . $strField;
            }
        }

        // Unset basic settings
        foreach ($mixValues as $key => $arrValues) {
            $strCheckValue = $arrValues['table'] . '::' . $arrValues['entry'];

            if (in_array($strCheckValue, $arrKnownValues)) {
                unset($mixValues[$key]);
            }
        }

        // Return
        return serialize($mixValues);
    }

    // Sync --------------------------------------------------------------------

    /**
     * Return a list with default values and
     * user values.
     *
     * @param string|array|null $mixValues
     *
     * @return array
     */
    public function loadSyncBlacklist(string|array|null $mixValues): array
    {
        if ($mixValues === null) {
            $mixValues = array();
        } elseif (!is_array($mixValues)) {
            $mixValues = (array) unserialize($mixValues);
        }

        $arrReturn = array();
        $arrKnownValues = array();

        // Get basic settings
        foreach ($GLOBALS['SYC_CONFIG']['sync_blacklist'] ?? [] as $strTableName => $arrValues) {
            foreach ($arrValues as $strField) {
                $arrReturn[] = array(
                    'table' => $strTableName,
                    'entry' => $strField
                );

                $arrKnownValues[] = $strTableName . '::' . $strField;
            }
        }

        // Unset basic settings
        foreach ($mixValues as $key => $arrValues) {
            $strCheckValue = $arrValues['table'] . '::' . $arrValues['entry'];

            if (in_array($strCheckValue, $arrKnownValues)) {
                unset($mixValues[$key]);
            }
        }

        // Merge
        return array_merge($arrReturn, $mixValues);
    }

    /**
     * Return a list with default values and
     * user values.
     *
     * @param string|array|null $mixValues
     *
     * @return string
     */
    public function saveSyncBlacklist(string|array|null $mixValues): string
    {
        if ($mixValues === null) {
            $mixValues = array();
        } elseif (!is_array($mixValues)) {
            $mixValues = (array) unserialize($mixValues);
        }

        $arrKnownValues = array();
        $arrReturn = array();

        // Get basic settings
        foreach ($GLOBALS['SYC_CONFIG']['sync_blacklist'] ?? [] as $strTableName => $arrValues) {
            foreach ($arrValues as $strField) {
                $arrKnownValues[] = $strTableName . '::' . $strField;
            }
        }

        // Unset basic settings
        foreach ($mixValues as $key => $arrValues) {
            $strCheckValue = $arrValues['table'] . '::' . $arrValues['entry'];

            if (!in_array($strCheckValue, $arrKnownValues)) {
                $arrReturn[] = $arrValues;
            }
        }

        // Return
        return serialize($arrReturn);
    }
}
