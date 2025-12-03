<?php

require __DIR__ . '/bootstrap.php';

$file = $_GET['file'] ?? '';
if (trim($file) === '') {
    http_response_code(400);
    exit;
}

$exportsDir = defined('PROJECT_EXPORT_PATH')
    ? PROJECT_EXPORT_PATH
    : dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'exports';

$exportDirReal = realpath($exportsDir);
if ($exportDirReal === false) {
    http_response_code(404);
    exit;
}

$requested = realpath($exportDirReal . DIRECTORY_SEPARATOR . basename($file));
if ($requested === false || str_starts_with($requested, $exportDirReal) === false || !file_exists($requested)) {
    http_response_code(404);
    exit;
}

header('Content-Type: application/zip');
header('Content-Length: ' . filesize($requested));
header('Content-Disposition: attachment; filename="' . basename($requested) . '"');
readfile($requested);
exit;
