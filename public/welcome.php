<?php

use App\Config\AppConfig;
use App\Config\ConfigPersister;

define('CONFIG_PATH', __DIR__ . '/../config.php');
define('APP_SETUP_MODE', true);
require __DIR__ . '/bootstrap.php';

if ($config->get('installation.is_installed', false)) {
    header('Location: projects.php');
    exit;
}

$errors = [];
$message = '';

$dbDefaults = getDatabaseDefaults($config);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'create_database') {
        $provided = [
            'host' => trim($_POST['db_host'] ?? ''),
            'port' => trim($_POST['db_port'] ?? ''),
            'username' => trim($_POST['db_username'] ?? ''),
            'password' => $_POST['db_password'] ?? '',
            'charset' => trim($_POST['db_charset'] ?? ''),
            'schema' => trim($_POST['db_schema'] ?? ''),
        ];

        if ($provided['schema'] === '') {
            $errors[] = 'Veuillez fournir un nom de schéma MySQL.';
        }

        if (empty($errors)) {
            try {
                $filtered = array_filter($provided, static fn($value) => $value !== '');
                ConfigPersister::update(CONFIG_PATH, [
                    'database' => $filtered,
                    'installation' => ['is_installed' => true],
                ]);
                $config = AppConfig::fromFile(CONFIG_PATH);
                $dbDefaults = getDatabaseDefaults($config);
                $config->getDatabaseConnection();
                ensureInstallationFlag(INSTALLATION_FLAG_FILE);
                $message = 'La base de données est prête. Vous pouvez maintenant accéder à l\'interface principale.';
            } catch (\Throwable $exception) {
                $errors[] = 'Impossible de créer la base de données : ' . $exception->getMessage();
            }
        }
    }
}

function ensureInstallationFlag(string $path): void
{
    $directory = dirname($path);
    if (!is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    file_put_contents($path, date('c'));
}

function getDatabaseDefaults(AppConfig $config): array
{
    $defaults = [
        'host' => 'localhost',
        'port' => '',
        'username' => 'root',
        'password' => '',
        'charset' => 'utf8mb4',
        'schema' => 'projects_manager',
    ];

    $configured = $config->get('database', []);
    return array_merge($defaults, array_filter($configured, static fn($value) => $value !== '' || $value === 0));
}

require __DIR__ . '/../views/welcome.php';
