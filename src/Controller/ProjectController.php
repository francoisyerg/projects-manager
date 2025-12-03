<?php

namespace App\Controller;

use App\Config\AppConfig;
use App\Service\ProjectService;

class ProjectController
{
    private ProjectService $service;
    private AppConfig $config;

    public function __construct(ProjectService $service, AppConfig $config)
    {
        $this->service = $service;
        $this->config = $config;
    }

    public function show(string $projectName): void
    {
        $project = $this->service->getProject($projectName);
        if (!$project) {
            header('Location: projects.php');
            exit;
        }

        $projectStatuses = $this->config->get('tasks', []);
        $task_priorities = $this->service->getTaskPriorities();
        $tasks = $this->sortTasksByPriority($project['tasks'] ?? []);

        $task_counts = [
            'pending' => 0,
            'done' => 0,
            'total' => count($tasks)
        ];

        foreach ($tasks as $task) {
            $status = $task['status'] ?? '';
            if ($status === 'pending') {
                $task_counts['pending']++;
            }
            if ($status === 'done') {
                $task_counts['done']++;
            }
        }

        $ordered_statuses = array_keys($projectStatuses);
        if (empty($ordered_statuses)) {
            $ordered_statuses = ['pending', 'in_progress', 'waiting_for_deployment', 'on_hold', 'done'];
        }

        $grouped_tasks = [];
        foreach ($ordered_statuses as $status) {
            $grouped_tasks[$status] = [];
        }

        $unknown_tasks = [];
        foreach ($tasks as $task) {
            $status = $task['status'] ?? 'pending';
            if (isset($grouped_tasks[$status])) {
                $grouped_tasks[$status][] = $task;
                continue;
            }
            $unknown_tasks[] = $task;
        }

        if (!empty($unknown_tasks)) {
            $grouped_tasks['_unknown'] = $unknown_tasks;
            $ordered_statuses[] = '_unknown';
        } else {
            $grouped_tasks['_unknown'] = [];

            $projectTasks = $tasks;
        }

        $app_title = $this->config->get('app_title', 'Gestionnaire de Projets - WAMP');
        $footer_message = $this->config->get('footer_message', 'Restez concentré sur vos projets.');
        $language = $this->config->get('language', 'fr');
        $assetsBasePath = '../assets';
        $app_icon = $this->config->get('app_icon', '🖥️');
        $phpmyadmin_url = $this->config->get('phpmyadmin_url', '/phpmyadmin/');
        $date_format = $this->config->get('date_format', 'd/m/Y H:i');
        $editor_type = $this->config->get('editor_type', 'textarea');
        $editor_placeholder = $this->config->get('editor_placeholder', '');
        $php_versions = $this->config->get('php_versions', []);
        $default_php_version = $this->config->get('default_php_version', '8.3');
        $default_task_status = $this->config->get('default_task_status', 'pending');
        $defaultTaskPriority = $this->service->getDefaultTaskPriority();
        $baseProjectsPath = $this->config->getPath('base_projects_path', '.');
        $projectSlug = $project['folder_slug'] ?? $projectName;

        $tinymce_cdn_url = $this->config->get('tinymce_cdn_url', 'https://cdn.tiny.cloud/1/');
        $tinymce_api_key = $this->config->get('tinymce_api_key', 'no-api-key');
        $tinymce_cdn = rtrim($tinymce_cdn_url, '/');
        $tinymce_script = $tinymce_cdn . '/' . $tinymce_api_key . '/tinymce/6/tinymce.min.js';

        require __DIR__ . '/../../views/layouts/project/detail.php';
    }

    private function sortTasksByPriority(array $tasks): array
    {
        $priorities = $this->config->get('task_priorities', []);
        $priorityOrder = array_keys($priorities);
        $orderMap = array_flip($priorityOrder);
        $defaultPriority = $this->config->get('default_task_priority', 'none');

        usort($tasks, function ($a, $b) use ($orderMap, $defaultPriority) {
            $aPriority = $a['priority'] ?? $defaultPriority;
            $bPriority = $b['priority'] ?? $defaultPriority;

            $aOrder = $orderMap[$aPriority] ?? 999;
            $bOrder = $orderMap[$bPriority] ?? 999;

            if ($aOrder !== $bOrder) {
                return $aOrder - $bOrder;
            }

            // Si même priorité, trier par date de création
            $aDate = $a['created_at'] ?? '';
            $bDate = $b['created_at'] ?? '';
            return strcmp($aDate, $bDate);
        });

        return $tasks;
    }

    public function handleAction(string $projectName, string $action, array $input): array
    {
        switch ($action) {
            case 'save_vhost':
                return $this->service->saveVhost($projectName, $input);
            case 'delete_vhost':
                $servername = trim($input['servername'] ?? '');
                if ($servername === '') {
                    return ['success' => false, 'error' => 'ServerName requis.'];
                }
                $deleteFolder = in_array(strtolower(trim($input['delete_folder'] ?? '0')), ['1', 'on', 'true'], true);
                $deleteDatabase = in_array(strtolower(trim($input['delete_database'] ?? '0')), ['1', 'on', 'true'], true);
                return $this->service->deleteVhost($projectName, $servername, $deleteFolder, $deleteDatabase);
            case 'export_project':
                $includeFiles = $this->parseBooleanFlag($input['include_files'] ?? '1');
                $includeVhosts = $this->parseBooleanFlag($input['include_vhosts'] ?? '1');
                $includeDatabases = $this->parseBooleanFlag($input['include_databases'] ?? '1');
                return $this->service->exportProject($projectName, $includeFiles, $includeVhosts, $includeDatabases);
            case 'save_task':
                return $this->service->saveTask($projectName, $input);
            case 'delete_task':
                $taskId = trim($input['task_id'] ?? '');
                if ($taskId === '') {
                    return ['success' => false, 'error' => 'ID de tâche requis.'];
                }
                return $this->service->deleteTask($projectName, $taskId);
            case 'edit_project':
                $newName = trim($input['new_project_name'] ?? '');
                $description = trim($input['new_project_description'] ?? '');
                $tags = $this->parseTags($input['new_project_tags'] ?? '');
                return $this->service->editProject($projectName, $newName, $description, $tags);
            case 'save_notes':
                $notes = $input['notes'] ?? '';
                return $this->service->saveNotes($projectName, $notes);
            case 'delete_project':
                $deleteFolder = ($input['delete_folder'] ?? 'no') === 'yes';
                return $this->service->deleteProject($projectName, $deleteFolder);
            default:
                return ['success' => false, 'error' => 'Action non reconnue.'];
        }
    }

    private function parseTags(string $raw): array
    {
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        return array_values(array_unique($parts));
    }

    private function parseBooleanFlag(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'on', 'true', 'yes'], true);
    }
}
