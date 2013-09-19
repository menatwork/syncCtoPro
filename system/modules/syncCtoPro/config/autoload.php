<?php

/**
 * Contao Open Source CMS
 *
 * Copyright (c) 2005-2013 Leo Feyer
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
	'SyncCtoProCommunicationClient' => 'system/modules/syncCtoPro/SyncCtoProCommunicationClient.php',
	'SyncCtoStepDatabaseDiff'       => 'system/modules/syncCtoPro/SyncCtoStepDatabaseDiff.php',
	'SyncCtoProSystem'              => 'system/modules/syncCtoPro/SyncCtoProSystem.php',
	'SyncCtoProDatabase'            => 'system/modules/syncCtoPro/SyncCtoProDatabase.php',
));


/**
 * Register the templates
 */
TemplateLoader::addFiles(array
(
	'be_syncCtoPro_form'               => 'system/modules/syncCtoPro/templates',
	'be_syncCtoPro_popup_overview'     => 'system/modules/syncCtoPro/templates',
	'be_syncCtoPro_popup_detail'       => 'system/modules/syncCtoPro/templates',
	'be_syncCtoPro_popup_all'          => 'system/modules/syncCtoPro/templates',
	'be_syncCtoPro_popup_detail_small' => 'system/modules/syncCtoPro/templates',
	'be_syncCtoPro_popup'              => 'system/modules/syncCtoPro/templates',
));
