<?php

require __DIR__ . '/bootstrap.php';

use App\Controller\ProjectsController;

if (!$config->get('installation.is_installed', false)) {
    header('Location: welcome.php');
    exit;
}

if ($service === null) {
    header('Location: welcome.php');
    exit;
}

$controller = new ProjectsController($service, $config);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? '';
    $response = $controller->handleAction($action, $_POST, $_FILES);
    echo json_encode($response);
    exit;
}

$controller->list();
