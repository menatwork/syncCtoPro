<?php

/**
 * Contao Open Source CMS
 *
 * @copyright  MEN AT WORK 2013
 * @package    syncCto Pro
 * @license    EULA
 * @filesource
 */
class RunonceSyncCtoPro extends Backend
{

    public function __construct()
    {
        parent::__construct();
    }

    public function run()
    {
        // Check referer
        if (SyncCtoProSystem::getInstance()->checkERData()) {
            // Generate hash
            $strHash = md5($GLOBALS['TL_CONFIG']['encryptionKey'] . "|" . $GLOBALS['TL_CONFIG']['encryptionKey']);

            // Save hash
            if (array_key_exists('syncCtoPro_hash', $GLOBALS['TL_CONFIG'])) {
                Config::getInstance()->update("\$GLOBALS['TL_CONFIG']['syncCtoPro_hash']", $strHash);
            } else {
                Config::getInstance()->add("\$GLOBALS['TL_CONFIG']['syncCtoPro_hash']", $strHash);
            }
        }

        // Remove all trigger.
        $database = SyncCtoProDatabase::getInstance();
        $database->removeTriggerFromHook();
    }

}

$objRunner = new RunonceSyncCtoPro();
$objRunner->run();
