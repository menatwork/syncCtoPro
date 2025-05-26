<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2014
 * @package    syncCto
 * @license    GNU/LGPL
 * @filesource
 */

/**
 * Class for syncCto settings
 */
class SyncCtoProTableSettings extends \Backend
{

    // Diff / Trigger ----------------------------------------------------------

    /**
     * Return a list with default values and
     * user values.
     *
     * @param array $mixValues
     *
     * @return array
     */
    public function loadDiffBlacklist($mixValues)
    {
        $mixValues      = (array)deserialize($mixValues);
        $arrReturn      = array();
        $arrKnownValues = array();

        // Get basic settings
        foreach ($GLOBALS['SYC_CONFIG']['trigger_blacklist'] as $strTableName => $arrValues) {
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
        $arrReturn = array_merge($arrReturn, $mixValues);

        // Return
        return $arrReturn;
    }

    /**
     * Return a list with default values and
     * user values.
     *
     * @param array $mixValues
     *
     * @return array
     */
    public function saveDiffBlacklist($mixValues)
    {
        $mixValues      = (array)deserialize($mixValues);
        $arrKnownValues = array();

        // Get basic settings
        foreach ($GLOBALS['SYC_CONFIG']['trigger_blacklist'] as $strTableName => $arrValues) {
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
     * @param array $mixValues
     *
     * @return array
     */
    public function loadSyncBlacklist($mixValues)
    {
        $mixValues      = (array)deserialize($mixValues);
        $arrReturn      = array();
        $arrKnownValues = array();

        // Get basic settings
        foreach ($GLOBALS['SYC_CONFIG']['sync_blacklist'] as $strTableName => $arrValues) {
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
        $arrReturn = array_merge($arrReturn, $mixValues);

        // Return
        return $arrReturn;
    }

    /**
     * Return a list with default values and
     * user values.
     *
     * @param array $mixValues
     *
     * @return array
     */
    public function saveSyncBlacklist($mixValues)
    {
        $mixValues      = (array)deserialize($mixValues);
        $arrKnownValues = array();
        $arrReturn      = array();

        // Get basic settings
        foreach ($GLOBALS['SYC_CONFIG']['sync_blacklist'] as $strTableName => $arrValues) {
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
