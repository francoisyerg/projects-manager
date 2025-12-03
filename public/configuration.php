<?php

use App\Config\AppConfig;
use App\Config\ConfigPersister;

define('CONFIG_PATH', __DIR__ . '/../config.php');
require __DIR__ . '/bootstrap.php';

if (!$config->get('installation.is_installed', false)) {
    header('Location: welcome.php');
    exit;
}

$errors = [];
$message = '';
$formValues = getConfigurationFormValues($config);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dbConfig = [
        'host' => trim($_POST['db_host'] ?? ''),
        'port' => trim($_POST['db_port'] ?? ''),
        'username' => trim($_POST['db_username'] ?? ''),
        'password' => $_POST['db_password'] ?? '',
        'schema' => trim($_POST['db_schema'] ?? ''),
    ];

    if ($dbConfig['schema'] === '') {
        $errors[] = 'Le nom du schéma est requis.';
    }

    $phpVersionResult = parsePhpVersionLines($_POST['php_versions'] ?? '');
    $errors = array_merge($errors, $phpVersionResult['errors']);

    $updates = [
        'database' => array_filter($dbConfig, static fn($value) => $value !== '' || $value === '0'),
        'base_projects_path' => trim($_POST['base_projects_path'] ?? ''),
        'vhosts_file' => trim($_POST['vhosts_file'] ?? ''),
        'hosts_file' => trim($_POST['hosts_file'] ?? ''),
        'app_title' => trim($_POST['app_title'] ?? ''),
        'default_php_version' => trim($_POST['default_php_version'] ?? ''),
        'php_versions' => $phpVersionResult['versions'],
    ];

    if (empty($errors)) {
        try {
            ConfigPersister::update(CONFIG_PATH, $updates);
            $config = AppConfig::fromFile(CONFIG_PATH);
            $formValues = getConfigurationFormValues($config);
            $message = 'Configuration mise à jour avec succès.';
        } catch (\Throwable $exception) {
            $errors[] = 'Impossible d\'enregistrer la configuration : ' . $exception->getMessage();
        }
    }
}

require __DIR__ . '/../views/configuration.php';

function getConfigurationFormValues(AppConfig $config): array
{
    $database = $config->get('database', []);
    return [
        'db_host' => $database['host'] ?? 'localhost',
        'db_port' => $database['port'] ?? '',
        'db_username' => $database['username'] ?? 'root',
        'db_password' => $database['password'] ?? '',
        'db_schema' => $database['schema'] ?? 'projects_manager',
        'base_projects_path' => $config->get('base_projects_path', ''),
        'vhosts_file' => $config->get('vhosts_file', ''),
        'hosts_file' => $config->get('hosts_file', ''),
        'app_title' => $config->get('app_title', 'Gestionnaire de Projets - WAMP'),
        'default_php_version' => $config->get('default_php_version', '8.3'),
        'php_versions' => $config->get('php_versions', []),
    ];
}

function parsePhpVersionLines(string $input): array
{
    $lines = preg_split('/\r?\n/', trim($input));
    $versions = [];
    $errors = [];

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        if (!str_contains($line, '=')) {
            $errors[] = sprintf('Ligne invalide pour les versions PHP : "%s" (attendu version=port).', $line);
            continue;
        }

        [$phpVersion, $port] = array_map('trim', explode('=', $line, 2));
        if ($phpVersion === '' || $port === '') {
            $errors[] = sprintf('Version PHP incomplète : "%s".', $line);
            continue;
        }

        $versions[$phpVersion] = $port;
    }

    return ['versions' => $versions, 'errors' => $errors];
}
