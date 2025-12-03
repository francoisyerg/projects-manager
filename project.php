<?php

$projectName = $_GET['name'] ?? null;
$target = 'public/project.php';
if ($projectName !== null && $projectName !== '') {
    $target .= '?name=' . urlencode($projectName);
}

header('Location: ' . $target);
exit;
