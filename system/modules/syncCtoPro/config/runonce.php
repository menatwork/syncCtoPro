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
 * SyncCtoProRunOnce
 */
class SyncCtoProRunOnce extends Backend
{

    /**
     * Initialize object (do not remove)
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function run()
    {
        $blnTableExists = $this->Database->tableExists('tl_synccto_diff');

        if ($blnTableExists == false)
        {
            $strCreate = "
               CREATE TABLE `tl_synccto_diff` (
                  `id` int(10) unsigned NOT NULL auto_increment,
                  `table` varchar(255) NOT NULL default '',
                  `row_id` int(10) unsigned NOT NULL default '0',
                  `hash` text NULL,
                  PRIMARY KEY  (`id`),
                  UNIQUE KEY `keys` (`table`,`row_id`)
                ) ENGINE=MyISAM DEFAULT CHARSET=utf8";

            $this->Database->query($strCreate);
        }

        $blnFieldExists = $this->Database->fieldExists('syncCto_hash', 'tl_page') && $this->Database->fieldExists('syncCto_hash', 'tl_article') && $this->Database->fieldExists('syncCto_hash', 'tl_content');

        $objSyncCtoProDatabase = SyncCtoProDatabase::getInstance();
        $objSyncCtoProDatabase->updateTrigger($blnFieldExists);
    }

}

$SyncCtoProRunOnce = new SyncCtoProRunOnce();
$SyncCtoProRunOnce->run();
?>
