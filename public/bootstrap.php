<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Config\AppConfig;
use App\Repository\ProjectsRepository;
use App\Service\ProjectService;
use App\Service\SystemVhostManager;

$config = AppConfig::fromFile(__DIR__ . '/../config.php');
if (!defined('INSTALLATION_FLAG_FILE')) {
	define('INSTALLATION_FLAG_FILE', __DIR__ . '/../storage/.db_initialized');
}

if (!defined('PROJECT_EXPORT_PATH')) {
	define('PROJECT_EXPORT_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'exports');
}

$repository = null;
$service = null;

$isSetupMode = defined('APP_SETUP_MODE') && APP_SETUP_MODE === true;
$isInstalled = $config->get('installation.is_installed', false);

if (!$isSetupMode && $isInstalled) {
	$repository = new ProjectsRepository($config);
	$systemVhostManager = new SystemVhostManager($config);
	$service = new ProjectService($repository, $config, $systemVhostManager);
}
