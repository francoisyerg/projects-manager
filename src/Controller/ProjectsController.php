<?php

namespace App\Controller;

use App\Config\AppConfig;
use App\Service\ProjectService;

class ProjectsController
{
    private ProjectService $service;
    private AppConfig $config;

    public function __construct(ProjectService $service, AppConfig $config)
    {
        $this->service = $service;
        $this->config = $config;
    }

    public function list(): void
    {
        $projects = $this->sortProjects($this->service->listProjects());
        $unique_tags = $this->collectUniqueTags($projects);
        $projectStatuses = $this->config->get('tasks', []);
        $app_title = $this->config->get('app_title', 'Gestionnaire de Projets - WAMP');
        $app_icon = $this->config->get('app_icon', '🖥️');
        $phpmyadmin_url = $this->config->get('phpmyadmin_url', '/phpmyadmin/');
        $footer_message = $this->config->get('footer_message', 'Restez concentré sur vos projets.');
        $language = $this->config->get('language', 'fr');
        $assetsBasePath = '../assets';
        $baseProjectsPath = $this->config->getPath('base_projects_path', '.');

        require __DIR__ . '/../../views/layouts/projects/list.php';
    }

    public function handleAction(string $action, array $input, array $files = []): array
    {
        switch ($action) {
            case 'create_project':
                return $this->handleCreateProject($input);
            case 'set_favorite':
                return $this->handleSetFavorite($input);
            case 'delete_project':
                return $this->handleDeleteProject($input);
            case 'track_access':
                return $this->handleTrackAccess($input);
            case 'import_project':
                return $this->handleImportProject($input, $files);
            default:
                return ['success' => false, 'error' => 'Action non reconnue.'];
        }
    }

    private function handleCreateProject(array $input): array
    {
        $projectName = trim($input['project_name'] ?? '');
        $description = trim($input['project_description'] ?? '');
        $tags = $this->parseTags($input['project_tags'] ?? '');

        return $this->service->createProject($projectName, $description, $tags);
    }

    private function handleSetFavorite(array $input): array
    {
        $projectName = trim($input['project_name'] ?? '');
        if ($projectName === '') {
            return ['success' => false, 'error' => 'Nom du projet requis.'];
        }

        $favorite = isset($input['favorite']) && ((string)$input['favorite'] === '1' || (string)$input['favorite'] === 'true');
        $success = $this->service->setFavorite($projectName, $favorite);

        if ($success) {
            return ['success' => true];
        }

        return ['success' => false, 'error' => 'Impossible de mettre à jour le statut favori.'];
    }

    private function handleDeleteProject(array $input): array
    {
        $projectName = trim($input['project_name'] ?? '');
        if ($projectName === '') {
            return ['success' => false, 'error' => 'Nom du projet requis.'];
        }

        $removeDirectory = isset($input['delete_folder']) && ((string)$input['delete_folder'] === '1' || (string)$input['delete_folder'] === 'true');
        return $this->service->deleteProject($projectName, $removeDirectory);
    }

    private function handleTrackAccess(array $input): array
    {
        $projectName = trim($input['project_name'] ?? '');
        if ($projectName === '') {
            return ['success' => false, 'error' => 'Nom du projet requis.'];
        }

        $success = $this->service->trackAccess($projectName);

        return ['success' => $success];
    }

    private function handleImportProject(array $input, array $files): array
    {
        $overrideName = trim($input['override_project_name'] ?? '');
        $zipFile = $files['project_zip'] ?? [];
        return $this->service->importProjectArchive($zipFile, $overrideName);
    }

    private function sortProjects(array $projects): array
    {
        uasort($projects, function ($a, $b) {
            $favA = !empty($a['favorite']);
            $favB = !empty($b['favorite']);

            if ($favA && !$favB) {
                return -1;
            }
            if ($favB && !$favA) {
                return 1;
            }

            $timeA = strtotime($a['last_accessed'] ?? 0);
            $timeB = strtotime($b['last_accessed'] ?? 0);

            return $timeB <=> $timeA;
        });

        return $projects;
    }

    private function collectUniqueTags(array $projects): array
    {
        $tags = [];
        foreach ($projects as $project) {
            if (!empty($project['tags']) && is_array($project['tags'])) {
                $tags = array_merge($tags, $project['tags']);
            }
        }

        $tags = array_filter(array_map('trim', $tags));
        $tags = array_unique($tags);
        sort($tags);
        return array_values($tags);
    }

    private function parseTags(string $raw): array
    {
        $parts = array_filter(array_map('trim', explode(',', $raw)));
        return array_values(array_unique($parts));
    }
}
