<?php

require __DIR__ . '/bootstrap.php';

use App\Controller\ProjectController;

if (!$config->get('installation.is_installed', false)) {
    header('Location: welcome.php');
    exit;
}

if ($service === null) {
    header('Location: welcome.php');
    exit;
}

$projectName = $_GET['name'] ?? null;
if ($projectName === null || $projectName === '') {
    header('Location: projects.php');
    exit;
}

$controller = new ProjectController($service, $config);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = $controller->handleAction($projectName, $action, $_POST);
    echo json_encode($response);
    exit;
}

$controller->show($projectName);
