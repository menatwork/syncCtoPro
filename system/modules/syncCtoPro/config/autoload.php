<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2014 Leo Feyer
 *
 * @package SyncCtoPro
 * @link    https://contao.org
 * @license http://www.gnu.org/licenses/lgpl-3.0.html LGPL
 */


/**
 * Register the classes
 */
ClassLoader::addClasses(array
(
	'SyncCtoProDatabase'            => 'system/modules/syncCtoPro/SyncCtoProDatabase.php',
	'SyncCtoProSystem'              => 'system/modules/syncCtoPro/SyncCtoProSystem.php',
    'SyncCtoProTableSettings'       => 'system/modules/syncCtoPro/SyncCtoProTableSettings.php',
    'SyncCtoStepDatabaseDiff'       => 'system/modules/syncCtoPro/SyncCtoStepDatabaseDiff.php',
    'SyncCtoProCommunicationClient' => 'system/modules/syncCtoPro/SyncCtoProCommunicationClient.php',
    'SyncCtoProPopupDiff'           => 'system/modules/syncCtoPro/SyncCtoProPopupDiff.php',
));


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
    'be_syncCtoPro_form'               => 'system/modules/syncCtoPro/templates',
    'be_syncCtoPro_popup'              => 'system/modules/syncCtoPro/templates',
    'be_syncCtoPro_popup_all'          => 'system/modules/syncCtoPro/templates',
    'be_syncCtoPro_popup_detail'       => 'system/modules/syncCtoPro/templates',
    'be_syncCtoPro_popup_detail_moved' => 'system/modules/syncCtoPro/templates',
    'be_syncCtoPro_popup_detail_small' => 'system/modules/syncCtoPro/templates',
    'be_syncCtoPro_popup_overview'     => 'system/modules/syncCtoPro/templates',
));
