<?php

namespace App\Service;

use App\Config\AppConfig;
use App\Repository\ProjectsRepository;
use ZipArchive;

class ProjectService
{
    private ProjectsRepository $repository;
    private AppConfig $config;
    private SystemVhostManager $systemVhostManager;

    public function __construct(ProjectsRepository $repository, AppConfig $config, SystemVhostManager $systemVhostManager)
    {
        $this->repository = $repository;
        $this->config = $config;
        $this->systemVhostManager = $systemVhostManager;
    }

    /**
     * Convertit un nom de projet en slug sûr pour le système de fichiers
     */
    private function slugify(string $text): string
    {
        // Convertir en minuscules
        $text = mb_strtolower($text, 'UTF-8');
        
        // Remplacer les caractères accentués
        $text = strtr($text, [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ý' => 'y', 'ÿ' => 'y',
            'ñ' => 'n', 'ç' => 'c',
        ]);
        
        // Remplacer les espaces et caractères spéciaux par des tirets
        $text = preg_replace('/[^a-z0-9]+/i', '-', $text);
        
        // Supprimer les tirets multiples
        $text = preg_replace('/-+/', '-', $text);
        
        // Supprimer les tirets en début et fin
        $text = trim($text, '-');
        
        return $text;
    }

    public function getTaskPriorities(): array
    {
        return $this->config->get('task_priorities', []);
    }

    public function getDefaultTaskPriority(): string
    {
        return $this->config->get('default_task_priority', 'none');
    }

    public function listProjects(): array
    {
        $overview = $this->repository->getProjectsOverview();
        $result = [];

        foreach ($overview as $name => $data) {
            $details = $this->repository->getProjectDetails($name);
            $result[$name] = array_merge($data, $details);
        }

        return $result;
    }

    public function getProject(string $name): ?array
    {
        $overview = $this->repository->getProjectsOverview();

        if (!isset($overview[$name])) {
            return null;
        }

        $details = $this->repository->getProjectDetails($name);
        return array_merge($overview[$name], $details);
    }

    public function updateProjectDetails(string $name, array $details): bool
    {
        $existing = $this->getProject($name);
        if ($existing === null) {
            return false;
        }

        return $this->repository->saveProjectDetails($name, array_merge($existing, $details));
    }

    public function createProject(string $name, string $description = '', array $tags = []): array
    {
        $projectName = trim($name);
        if ($projectName === '') {
            return ['success' => false, 'error' => 'Nom du projet requis.'];
        }

        $nameError = $this->ensureValidProjectName($projectName);
        if ($nameError !== null) {
            return ['success' => false, 'error' => $nameError];
        }

        if ($this->repository->hasProject($projectName)) {
            return ['success' => false, 'error' => 'Un projet avec ce nom existe déjà.'];
        }

        $timestamp = date('Y-m-d H:i:s');
        $normalizedTags = $this->normalizeTags($tags);
        $folderSlug = $this->slugify($projectName);

        $overview = [
            'name' => $projectName,
            'folder_slug' => $folderSlug,
            'description' => $description,
            'tags' => $normalizedTags,
            'favorite' => false,
            'created_at' => $timestamp,
            'last_accessed' => $timestamp
        ];

        $details = [
            'vhosts' => [],
            'tasks' => [],
            'notes' => '',
            'created_at' => $timestamp,
            'last_accessed' => $timestamp
        ];

        if (!$this->repository->ensureProjectDirectory($folderSlug)) {
            return ['success' => false, 'error' => 'Impossible de créer le dossier du projet.'];
        }

        if (!$this->repository->saveProjectOverview($projectName, $overview)) {
            return ['success' => false, 'error' => 'Impossible de sauvegarder le projet.'];
        }

        if (!$this->repository->saveProjectDetails($projectName, $details)) {
            $this->repository->deleteProjectOverview($projectName);
            return ['success' => false, 'error' => 'Impossible de sauvegarder les détails du projet.'];
        }

        return ['success' => true, 'project_name' => $projectName];
    }

    public function setFavorite(string $name, bool $favorite): bool
    {
        $overview = $this->repository->getProjectsOverview();

        if (!isset($overview[$name])) {
            return false;
        }

        $overview[$name]['favorite'] = $favorite;

        return $this->repository->saveProjectsOverview($overview);
    }

    public function trackAccess(string $name): bool
    {
        $overview = $this->repository->getProjectsOverview();

        if (!isset($overview[$name])) {
            return false;
        }

        $overview[$name]['last_accessed'] = date('Y-m-d H:i:s');

        return $this->repository->saveProjectsOverview($overview);
    }

    public function deleteProject(string $name, bool $removeDirectory = false): array
    {
        if (!$this->repository->hasProject($name)) {
            return ['success' => false, 'error' => 'Projet introuvable.'];
        }

        // Get the folder slug before deleting
        $overview = $this->repository->getProjectsOverview();
        $folderSlug = $overview[$name]['folder_slug'] ?? $this->slugify($name);

        $details = $this->repository->getProjectDetails($name);
        $vhosts = $details['vhosts'] ?? [];
        $vhostErrors = [];
        foreach ($vhosts as $vhost) {
            $servername = trim($vhost['servername'] ?? '');
            if ($servername === '') {
                continue;
            }

            if (!$this->systemVhostManager->removeVhost($servername)) {
                $vhostErrors[] = "Impossible de supprimer le Virtual Host $servername.";
            }

            if (!$this->systemVhostManager->updateHostsEntry($servername, 'delete')) {
                $vhostErrors[] = "Impossible de supprimer l'entrée hosts pour $servername.";
            }
        }

        if (!$this->repository->deleteProjectOverview($name)) {
            return ['success' => false, 'error' => 'Impossible de supprimer le projet dans l’overview.'];
        }

        if ($removeDirectory) {
            $deleted = $this->repository->removeProjectDirectory($folderSlug);
            return ['success' => true, 'folder_deleted' => $deleted, 'vhost_errors' => $vhostErrors];
        }

        $this->repository->deleteProjectDetailsFile($name);

        return ['success' => true, 'folder_deleted' => false, 'vhost_errors' => $vhostErrors];
    }

    public function saveVhost(string $projectName, array $payload): array
    {
        $project = $this->getProject($projectName);
        if (!$project) {
            return ['success' => false, 'error' => 'Projet introuvable.'];
        }

        // Get project slug for DocumentRoot
        $overview = $this->repository->getProjectsOverview();
        $projectSlug = $overview[$projectName]['folder_slug'] ?? $this->slugify($projectName);

        $details = $this->repository->getProjectDetails($projectName);
        $vhosts = $details['vhosts'] ?? [];

        $displayName = trim($payload['display_name'] ?? '');
        if ($displayName === '') {
            return ['success' => false, 'error' => 'Nom du Virtual Host requis.'];
        }

        $servername = trim($payload['servername'] ?? '');
        if ($servername === '') {
            return ['success' => false, 'error' => 'Hostname requis.'];
        }

        $documentroot = $this->normalizeDocumentRoot(trim($payload['documentroot'] ?? ''));
        if ($documentroot === '') {
            return ['success' => false, 'error' => 'DocumentRoot requis.'];
        }

        $description = trim($payload['description'] ?? '');
        $phpVersion = trim($payload['php_version'] ?? $this->config->get('default_php_version', '8.3'));
        $createDatabase = isset($payload['create_database']) && in_array(strtolower((string)$payload['create_database']), ['1', 'on', 'true'], true);
        $databaseName = trim($payload['database_name'] ?? '');
        $editMode = ($payload['edit_mode'] ?? '0') === '1';
        $originalName = trim($payload['original_servername'] ?? '');

        $ensureResult = $this->systemVhostManager->ensureDocumentRoot($projectSlug, $servername, $documentroot, $phpVersion);
        if (!$ensureResult['success']) {
            return ['success' => false, 'error' => $ensureResult['error'] ?? 'Impossible de préparer le DocumentRoot.'];
        }

        $documentroot = $ensureResult['document_root'] ?? $documentroot;

        if ($editMode) {
            $target = $originalName !== '' ? $originalName : $servername;
            $index = $this->findVhostIndex($vhosts, $target);
            if ($index === null) {
                return ['success' => false, 'error' => 'Virtual Host introuvable.'];
            }

            if ($servername !== $target && $this->findVhostIndex($vhosts, $servername) !== null) {
                return ['success' => false, 'error' => 'Un autre Virtual Host utilise déjà ce ServerName.'];
            }

            $serverRenamed = $target !== '' && $target !== $servername;
            if ($serverRenamed) {
                if (!$this->systemVhostManager->removeVhost($target)) {
                    return ['success' => false, 'error' => 'Impossible de supprimer l’ancien Virtual Host.'];
                }
                if (!$this->systemVhostManager->updateHostsEntry($target, 'delete')) {
                    return ['success' => false, 'error' => 'Impossible de mettre à jour le fichier hosts.'];
                }
            }

            if (!$this->systemVhostManager->updateVhost($servername, $documentroot, $phpVersion)) {
                return ['success' => false, 'error' => 'Impossible de mettre à jour le Virtual Host.'];
            }

            if (!$this->systemVhostManager->updateHostsEntry($servername, 'add')) {
                return ['success' => false, 'error' => 'Impossible de mettre à jour le fichier hosts.'];
            }

            $vhosts[$index]['display_name'] = $displayName;
            $vhosts[$index]['servername'] = $servername;
            $vhosts[$index]['documentroot'] = $documentroot;
            $vhosts[$index]['description'] = $description;
            $vhosts[$index]['php_version'] = $phpVersion;
            $vhosts[$index]['database_name'] = $createDatabase ? ($databaseName !== '' ? $databaseName : $this->generateDatabaseName($projectName, $servername)) : null;
            $vhosts[$index]['updated_at'] = date('Y-m-d H:i:s');
        } else {
            if ($this->findVhostIndex($vhosts, $servername) !== null) {
                return ['success' => false, 'error' => 'Ce ServerName existe déjà pour ce projet.'];
            }

            if (!$this->systemVhostManager->addVhost($servername, $documentroot, $phpVersion)) {
                return ['success' => false, 'error' => 'Impossible d\'écrire dans le fichier vhosts.'];
            }

            if (!$this->systemVhostManager->updateHostsEntry($servername, 'add')) {
                return ['success' => false, 'error' => 'Impossible de mettre à jour le fichier hosts.'];
            }

            $vhosts[] = [
                'display_name' => $displayName,
                'servername' => $servername,
                'actual_servername' => $servername,
                'documentroot' => $documentroot,
                'description' => $description,
                'php_version' => $phpVersion,
                'database_name' => $createDatabase ? ($databaseName !== '' ? $databaseName : $this->generateDatabaseName($projectName, $servername)) : null,
                'created_at' => date('Y-m-d H:i:s')
            ];
        }

        $details['vhosts'] = $vhosts;

        if (!$this->persistProjectDetails($projectName, $details)) {
            return ['success' => false, 'error' => 'Impossible de sauvegarder le Virtual Host.'];
        }

        return ['success' => true];
    }

    public function deleteVhost(string $projectName, string $servername, bool $deleteFolder = false, bool $deleteDatabase = false): array
    {
        $project = $this->getProject($projectName);
        if (!$project) {
            return ['success' => false, 'error' => 'Projet introuvable.'];
        }

        $details = $this->repository->getProjectDetails($projectName);
        $vhosts = $details['vhosts'] ?? [];
        $index = $this->findVhostIndex($vhosts, $servername);

        if ($index === null) {
            return ['success' => false, 'error' => 'Virtual Host introuvable.'];
        }
        $documentroot = $vhosts[$index]['documentroot'] ?? '';

        if (!$this->systemVhostManager->removeVhost($servername)) {
            return ['success' => false, 'error' => 'Impossible de supprimer le Virtual Host du fichier Apache.'];
        }

        if (!$this->systemVhostManager->updateHostsEntry($servername, 'delete')) {
            return ['success' => false, 'error' => 'Impossible de mettre à jour le fichier hosts.'];
        }

        unset($vhosts[$index]);
        $details['vhosts'] = array_values($vhosts);

        if (!$this->persistProjectDetails($projectName, $details)) {
            return ['success' => false, 'error' => 'Impossible de supprimer le Virtual Host.'];
        }

        $response = ['success' => true];

        if ($deleteFolder && $documentroot !== '') {
            $folderResult = $this->systemVhostManager->deleteDocumentRoot($documentroot);
            if ($folderResult['success']) {
                $response['folder_deleted'] = $folderResult['path'];
            } else {
                $response['folder_error'] = $folderResult['error'] ?? 'Impossible de supprimer le dossier du Virtual Host.';
            }
        }

        if ($deleteDatabase) {
            $response['database_deleted'] = false;
        }

        return $response;
    }

    public function importProjectArchive(array $fileData, string $overrideName = ''): array
    {
        if (empty($fileData) || empty($fileData['tmp_name']) || empty($fileData['name'])) {
            return ['success' => false, 'error' => 'Archive ZIP requise.'];
        }

        if (($fileData['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return ['success' => false, 'error' => 'Erreur lors de l\'upload du fichier.'];
        }

        if (!class_exists(ZipArchive::class)) {
            return ['success' => false, 'error' => 'Support ZIP manquant.'];
        }

        $tmpPath = $fileData['tmp_name'];
        $zip = new ZipArchive();
        if ($zip->open($tmpPath) !== true) {
            return ['success' => false, 'error' => 'Le fichier ZIP est invalide.'];
        }

        $metadataRaw = $zip->getFromName('metadata.json');
        if ($metadataRaw === false) {
            $zip->close();
            return ['success' => false, 'error' => 'Métadonnées manquantes dans l\'archive.'];
        }

        $metadata = json_decode($metadataRaw, true);
        if (!is_array($metadata)) {
            $zip->close();
            return ['success' => false, 'error' => 'Les métadonnées sont illisibles.'];
        }

        $projectMeta = $metadata['project'] ?? [];
        $desiredName = trim($overrideName !== '' ? $overrideName : ($projectMeta['name'] ?? ''));
        $desiredName = $this->cleanProjectName($desiredName);
        if ($desiredName === '') {
            $desiredName = 'imported_project';
        }

        $projectName = $this->resolveImportProjectName($desiredName);
        $description = $projectMeta['description'] ?? '';
        $tags = $projectMeta['tags'] ?? [];

        $createResult = $this->createProject($projectName, $description, $tags);
        if (!$createResult['success']) {
            $zip->close();
            return ['success' => false, 'error' => 'Impossible de créer le projet importé : ' . ($createResult['error'] ?? 'Erreur.')];
        }

        $projectDir = $this->repository->getProjectDirectoryPath($projectName);
        $this->extractFilesFromZip($zip, 'files/', $projectDir);

        $details = $this->repository->getProjectDetails($projectName);
        $details['notes'] = $projectMeta['notes'] ?? ($metadata['details']['notes'] ?? '');
        $details['tasks'] = $metadata['details']['tasks'] ?? [];

        $originalProjectDir = $projectMeta['directory'] ?? '';
        $newVhosts = [];
        $vhostErrors = [];

        foreach ($metadata['details']['vhosts'] ?? [] as $vhostData) {
            $servername = trim($vhostData['servername'] ?? '');
            if ($servername === '') {
                continue;
            }

            $documentRoot = $this->mapImportedDocumentRoot(
                $vhostData['documentroot'] ?? '',
                $originalProjectDir,
                $projectDir
            );

            if ($documentRoot !== '' && !is_dir($this->toFilesystemPath($documentRoot))) {
                @mkdir($this->toFilesystemPath($documentRoot), 0755, true);
            }

            $phpVersion = $vhostData['php_version'] ?? $this->config->get('default_php_version', '8.3');

            if (!$this->systemVhostManager->addVhost($servername, $documentRoot, $phpVersion)) {
                $vhostErrors[] = "Impossible de créer le Virtual Host $servername.";
                continue;
            }

            if (!$this->systemVhostManager->updateHostsEntry($servername, 'add')) {
                $vhostErrors[] = "Impossible de mettre à jour le fichier hosts pour $servername.";
            }

            $newVhosts[] = [
                'servername' => $servername,
                'actual_servername' => $servername,
                'documentroot' => $documentRoot,
                'description' => $vhostData['description'] ?? '',
                'php_version' => $phpVersion,
                'database_name' => $vhostData['database_name'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => null,
            ];
        }

        $details['vhosts'] = $newVhosts;
        if (!$this->persistProjectDetails($projectName, $details)) {
            $vhostErrors[] = 'Impossible de sauvegarder les détails du projet importé.';
        }

        $databaseResults = [];
        $databases = array_unique(array_filter(array_map(fn ($vhost) => trim($vhost['database_name'] ?? ''), $metadata['details']['vhosts'] ?? [])));

        foreach ($databases as $database) {
            if ($database === '') {
                continue;
            }

            $entryName = 'databases/' . $this->sanitizeFileName($database) . '.sql';
            $dumpContent = $zip->getFromName($entryName);
            if ($dumpContent === false) {
                $databaseResults[$database] = ['success' => false, 'error' => 'Fichier SQL manquant dans l\'archive.'];
                continue;
            }

            $databaseResults[$database] = $this->importDatabaseDump($database, $dumpContent);
        }

        $zip->close();

        $response = [
            'success' => true,
            'project_name' => $projectName,
            'vhost_errors' => $vhostErrors,
            'database_results' => $databaseResults,
        ];

        return $response;
    }

    public function exportProject(string $projectName, bool $includeFiles = true, bool $includeVhosts = true, bool $includeDatabases = true): array
    {
        $project = $this->getProject($projectName);
        if (!$project) {
            return ['success' => false, 'error' => 'Projet introuvable.'];
        }

        $details = $this->repository->getProjectDetails($projectName);
        $exportsDir = $this->getExportDirectory();

        $projectDir = $this->repository->getProjectDirectoryPath($projectName);

        if (!is_dir($exportsDir) && !mkdir($exportsDir, 0755, true) && !is_dir($exportsDir)) {
            return ['success' => false, 'error' => 'Impossible de préparer le dossier d\'export.'];
        }

        if (!class_exists(ZipArchive::class)) {
            return ['success' => false, 'error' => 'Extension Zip manquante.'];
        }

        $timestamp = date('Ymd_His');
        $safeName = $this->sanitizeFileName($projectName) ?: 'project';
        $zipName = sprintf('%s_export_%s.zip', $safeName, $timestamp);
        $zipPath = $exportsDir . DIRECTORY_SEPARATOR . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return ['success' => false, 'error' => 'Impossible de créer l\'archive d\'export.'];
        }

        if ($includeFiles) {
            $this->addDirectoryToZip($zip, $projectDir, 'files');
        }

        if ($includeVhosts) {
            $vhosts = $details['vhosts'] ?? [];
            if (!empty($vhosts)) {
                $blocks = array_map([$this, 'buildVhostConfigBlock'], $vhosts);
                $zip->addFromString('configs/vhosts.conf', implode(PHP_EOL . PHP_EOL, $blocks));
            }
        }

        $metadata = [
            'project' => [
                'name' => $project['name'] ?? $projectName,
                'description' => $project['description'] ?? '',
                'tags' => $project['tags'] ?? [],
                'favorite' => $project['favorite'] ?? false,
                'created_at' => $project['created_at'] ?? null,
                'last_accessed' => $project['last_accessed'] ?? null,
                'notes' => $details['notes'] ?? '',
                'directory' => $projectDir,
            ],
            'details' => [
                'notes' => $details['notes'] ?? '',
                'tasks' => $details['tasks'] ?? [],
                'vhosts' => $details['vhosts'] ?? [],
            ],
            'exported_at' => date('c'),
            'options' => [
                'files' => $includeFiles,
                'vhosts' => $includeVhosts,
                'databases' => $includeDatabases,
            ],
        ];

        $zip->addFromString('metadata.json', json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($includeDatabases) {
            $databases = array_unique(array_filter(array_map(fn ($vhost) => trim($vhost['database_name'] ?? ''), $details['vhosts'] ?? [])));
            foreach ($databases as $database) {
                if ($database === '') {
                    continue;
                }

                $dump = $this->dumpDatabase($database);
                $entryName = 'databases/' . $this->sanitizeFileName($database);
                if ($dump['success']) {
                    $zip->addFromString($entryName . '.sql', $dump['content']);
                } else {
                    $zip->addFromString($entryName . '-error.txt', $dump['error'] ?? 'Erreur inconnue lors du dump.');
                }
            }
        }

        $zip->close();

        return [
            'success' => true,
            'download_url' => 'export.php?file=' . urlencode(basename($zipPath)),
            'file_name' => basename($zipPath),
        ];
    }

    public function saveTask(string $projectName, array $payload): array
    {
        $project = $this->getProject($projectName);
        if (!$project) {
            return ['success' => false, 'error' => 'Projet introuvable.'];
        }

        $details = $this->repository->getProjectDetails($projectName);
        $tasks = $details['tasks'] ?? [];

        $taskTitle = trim($payload['task_title'] ?? '');
        if ($taskTitle === '') {
            return ['success' => false, 'error' => 'Titre de tâche requis.'];
        }

        $taskId = trim($payload['task_id'] ?? '');
        $status = $this->normalizeTaskStatus(trim($payload['task_status'] ?? ''));
        $dueDate = trim($payload['task_due_date'] ?? '');
        $description = trim($payload['task_description'] ?? '');
        $priority = $this->normalizeTaskPriority($payload['task_priority'] ?? null);

        if ($taskId === '') {
            $taskId = $this->generateTaskId();
            $tasks[] = [
                'id' => $taskId,
                'title' => $taskTitle,
                'status' => $status,
                'due_date' => $dueDate,
                'description' => $description,
                'priority' => $priority,
                'created_at' => date('Y-m-d H:i:s')
            ];
        } else {
            $updated = false;
            foreach ($tasks as &$task) {
                if ($task['id'] === $taskId) {
                    $task['title'] = $taskTitle;
                    $task['status'] = $status;
                    $task['due_date'] = $dueDate;
                    $task['description'] = $description;
                    $task['priority'] = $priority;
                    $task['updated_at'] = date('Y-m-d H:i:s');
                    $updated = true;
                    break;
                }
            }
            unset($task);

            if (!$updated) {
                return ['success' => false, 'error' => 'Tâche introuvable.'];
            }
        }

        $details['tasks'] = $tasks;

        if (!$this->persistProjectDetails($projectName, $details)) {
            return ['success' => false, 'error' => 'Impossible de sauvegarder la tâche.'];
        }

        return ['success' => true, 'task_id' => $taskId];
    }

    public function deleteTask(string $projectName, string $taskId): array
    {
        $project = $this->getProject($projectName);
        if (!$project) {
            return ['success' => false, 'error' => 'Projet introuvable.'];
        }

        $details = $this->repository->getProjectDetails($projectName);
        $tasks = $details['tasks'] ?? [];
        $filtered = array_filter($tasks, fn ($task) => ($task['id'] ?? '') !== $taskId);

        if (count($filtered) === count($tasks)) {
            return ['success' => false, 'error' => 'Tâche introuvable.'];
        }

        $details['tasks'] = array_values($filtered);

        if (!$this->persistProjectDetails($projectName, $details)) {
            return ['success' => false, 'error' => 'Impossible de supprimer la tâche.'];
        }

        return ['success' => true];
    }

    public function editProject(string $currentName, string $newName, string $description = '', array $tags = []): array
    {
        $currentName = trim($currentName);
        $newName = trim($newName);

        if ($newName === '') {
            return ['success' => false, 'error' => 'Nom du projet requis.'];
        }

        $nameError = $this->ensureValidProjectName($newName);
        if ($nameError !== null) {
            return ['success' => false, 'error' => $nameError];
        }

        $overview = $this->repository->getProjectsOverview();

        if (!isset($overview[$currentName])) {
            return ['success' => false, 'error' => 'Projet introuvable.'];
        }

        $currentSlug = $overview[$currentName]['folder_slug'] ?? $this->slugify($currentName);
        $targetKey = $currentName;

        if ($newName !== $currentName) {
            if (isset($overview[$newName])) {
                return ['success' => false, 'error' => 'Un projet avec ce nom existe déjà.'];
            }

            $newSlug = $this->slugify($newName);
            
            // Renommer le dossier uniquement si explicitement demandé
            $renameFolder = isset($payload['rename_folder']) && $payload['rename_folder'] === '1';
            if ($renameFolder && $currentSlug !== $newSlug) {
                if (!$this->repository->renameProjectDirectory($currentSlug, $newSlug)) {
                    return ['success' => false, 'error' => 'Impossible de renommer le dossier du projet.'];
                }
                // Mettre à jour le slug seulement si le dossier a été renommé
                $overview[$currentName]['folder_slug'] = $newSlug;
            }

            $overview[$newName] = $overview[$currentName];
            unset($overview[$currentName]);
            $targetKey = $newName;
        }

        $overview[$targetKey]['name'] = $targetKey;
        $overview[$targetKey]['description'] = $description;
        $overview[$targetKey]['tags'] = $this->normalizeTags($tags);
        $overview[$targetKey]['last_accessed'] = date('Y-m-d H:i:s');

        if (!$this->repository->saveProjectsOverview($overview)) {
            return ['success' => false, 'error' => 'Impossible de sauvegarder les modifications.'];
        }

        return ['success' => true, 'new_project_name' => $targetKey];
    }

    public function saveNotes(string $projectName, string $notes): array
    {
        $project = $this->getProject($projectName);
        if (!$project) {
            return ['success' => false, 'error' => 'Projet introuvable.'];
        }

        $details = $this->repository->getProjectDetails($projectName);
        $details['notes'] = $notes;

        if (!$this->persistProjectDetails($projectName, $details)) {
            return ['success' => false, 'error' => 'Impossible de sauvegarder les notes.'];
        }

        return ['success' => true];
    }

    private function persistProjectDetails(string $projectName, array $details): bool
    {
        $details['last_accessed'] = date('Y-m-d H:i:s');
        return $this->repository->saveProjectDetails($projectName, $details);
    }

    private function findVhostIndex(array $vhosts, string $servername): ?int
    {
        $servername = trim($servername);
        $fallbackIndex = null;

        foreach ($vhosts as $index => $vhost) {
            $registered = $vhost['servername'] ?? '';
            if ($registered === $servername) {
                return $index;
            }

            if ($fallbackIndex === null && $registered === '' && ($vhost['actual_servername'] ?? '') === $servername) {
                $fallbackIndex = $index;
            }
        }

        return $fallbackIndex;
    }

    private function normalizeTags(array $tags): array
    {
        $clean = array_filter(array_map('trim', $tags));
        return array_values(array_unique($clean));
    }

    private function ensureValidProjectName(string $name): ?string
    {
        $maxLength = (int)$this->config->get('max_project_name_length', 100);

        if (strlen($name) > $maxLength) {
            return sprintf('Le nom du projet doit contenir au maximum %d caractères.', $maxLength);
        }

        if (trim($name) === '') {
            return 'Le nom du projet ne peut pas être vide.';
        }

        // Vérifier que le slug généré n'est pas vide
        $slug = $this->slugify($name);
        if ($slug === '') {
            return 'Le nom du projet doit contenir au moins un caractère alphanumé rique.';
        }

        return null;
    }

    private function normalizeTaskStatus(string $status): string
    {
        if ($status === '') {
            return $this->config->get('default_task_status', 'pending');
        }

        $allowed = array_keys($this->config->get('tasks', []));
        if (!in_array($status, $allowed, true)) {
            return $this->config->get('default_task_status', 'pending');
        }

        return $status;
    }

    private function generateTaskId(): string
    {
        return bin2hex(random_bytes(8));
    }

    private function normalizeTaskPriority(mixed $value): string
    {
        if ($value === null || $value === '') {
            return $this->getDefaultTaskPriority();
        }

        $strval = (string)$value;
        $priorities = $this->getTaskPriorities();

        return isset($priorities[$strval]) ? $strval : $this->getDefaultTaskPriority();
    }

    private function generateDatabaseName(string $projectName, string $servername): string
    {
        $cleanProject = preg_replace('/[^a-z0-9_]/', '_', strtolower($projectName));
        $cleanProject = preg_replace('/_+/', '_', trim($cleanProject, '_'));

        $cleanServer = strtolower($servername);
        $cleanServer = preg_replace('/^(https?:\/\/)?(www\.)?/', '', $cleanServer);
        $cleanServer = preg_replace('/[^a-z0-9_]/', '_', $cleanServer);
        $cleanServer = preg_replace('/_+/', '_', trim($cleanServer, '_'));

        $name = trim($cleanProject . '_' . $cleanServer, '_');

        if (preg_match('/^[0-9]/', $name)) {
            $name = 'db_' . $name;
        }

        if (strlen($name) > 64) {
            $name = substr($name, 0, 64);
        }

        return $name;
    }

    private function normalizeDocumentRoot(string $path): string
    {
        $value = trim($path);
        if ($value === '') {
            return '';
        }

        $value = str_replace(['\\', '/'], '/', $value);
        return rtrim($value, '/');
    }



    private function getExportDirectory(): string
    {
        if (defined('PROJECT_EXPORT_PATH')) {
            return PROJECT_EXPORT_PATH;
        }

        return dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'exports';
    }

    private function addDirectoryToZip(ZipArchive $zip, string $directory, string $prefix): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $normalized = rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $relativePath = str_replace('\\', '/', substr($item->getPathname(), strlen($normalized)));
            $localName = trim($prefix . '/' . $relativePath, '/');

            if ($item->isDir()) {
                $zip->addEmptyDir($localName);
                continue;
            }

            $zip->addFile($item->getPathname(), $localName);
        }
    }

    private function buildVhostConfigBlock(array $vhost): string
    {
        $servername = $vhost['servername'] ?? '';
        $documentroot = $this->normalizeDocumentRoot($vhost['documentroot'] ?? '');
        $phpVersion = $vhost['php_version'] ?? $this->config->get('default_php_version', '8.3');
        $listenAddress = $this->config->get('vhost_settings.listen_address', '*:80');
        $allowOverride = $this->config->get('vhost_settings.directory_permissions.AllowOverride', 'All');
        $require = $this->config->get('vhost_settings.directory_permissions.Require', 'local');
        $description = trim($vhost['description'] ?? '');

        $escapedDocumentRoot = $documentroot !== '' ? rtrim(str_replace('"', '\\"', $documentroot), '/') : '';
        $directoryPath = $escapedDocumentRoot !== '' ? $escapedDocumentRoot . '/' : $escapedDocumentRoot;

        $descriptionComment = $description !== '' ? "    # Description: {$description}\n" : '';

        return <<<CONF
<VirtualHost {$listenAddress}>
    ServerName {$servername}
    DocumentRoot "{$escapedDocumentRoot}"
    <Directory "{$directoryPath}">
        AllowOverride {$allowOverride}
        Require {$require}
    </Directory>
{$descriptionComment}    # PHP version: {$phpVersion}
</VirtualHost>
CONF;
    }

    private function dumpDatabase(string $database): array
    {
        $dbConfig = $this->config->get('database', []);
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = trim((string)($dbConfig['port'] ?? ''));
        $username = $dbConfig['username'] ?? 'root';
        $password = $dbConfig['password'] ?? '';
        $mysqldump = $this->config->get('mysql_tools.mysqldump') ?? $this->config->get('mysqldump_path') ?? 'mysqldump';

        $parts = [
            escapeshellcmd($mysqldump),
            '--host=' . escapeshellarg($host),
            '--user=' . escapeshellarg($username),
        ];

        if ($password !== '') {
            $parts[] = '--password=' . escapeshellarg($password);
        }

        if ($port !== '') {
            $parts[] = '--port=' . escapeshellarg($port);
        }

        $parts[] = '--databases ' . escapeshellarg($database);
        $parts[] = '--single-transaction';
        $parts[] = '--skip-lock-tables';
        $parts[] = '--routines';
        $parts[] = '--triggers';

        $command = implode(' ', $parts);
        $descriptors = [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['success' => false, 'error' => 'Impossible de lancer mysqldump.'];
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $message = trim($error ?: 'Erreur lors de l\'export de la base.');
            return ['success' => false, 'error' => $message, 'debug' => $command];
        }

        return ['success' => true, 'content' => $output];
    }

    private function sanitizeFileName(string $value): string
    {
        $clean = preg_replace('/[^A-Za-z0-9_-]/', '_', $value);
        $clean = preg_replace('/_+/', '_', $clean);
        return trim($clean, '_');
    }

    private function cleanProjectName(string $value): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_-]/', '_', $value);
        $clean = preg_replace('/_+/', '_', $clean);
        return trim($clean, '_');
    }

    private function resolveImportProjectName(string $desired): string
    {
        $name = $desired;
        $suffix = 1;
        while ($this->repository->hasProject($name)) {
            $name = $desired . '_import' . $suffix++;
        }
        return $name;
    }

    private function extractFilesFromZip(ZipArchive $zip, string $prefix, string $destination): void
    {
        if (!is_dir($destination)) {
            @mkdir($destination, 0755, true);
        }

        $normalizedDestination = rtrim($destination, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->getNameIndex($i);
            if ($entry === false || !str_starts_with($entry, $prefix)) {
                continue;
            }

            $relative = substr($entry, strlen($prefix));
            $relative = str_replace(['\\', '/'], '/', $relative);
            if ($relative === '' || str_contains($relative, '..')) {
                continue;
            }

            $targetPath = $normalizedDestination . str_replace('/', DIRECTORY_SEPARATOR, $relative);

            if (str_ends_with($relative, '/')) {
                @mkdir($targetPath, 0755, true);
                continue;
            }

            $content = $zip->getFromIndex($i);
            if ($content === false) {
                continue;
            }

            $dir = dirname($targetPath);
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }

            file_put_contents($targetPath, $content);
        }
    }

    private function mapImportedDocumentRoot(string $original, string $originalProjectDir, string $newProjectDir): string
    {
        $normalizedOriginal = $this->normalizeDocumentRoot($original);
        $normalizedOriginalDir = $this->normalizeDocumentRoot($originalProjectDir);

        if ($normalizedOriginal === '') {
            return $this->normalizeDocumentRoot($newProjectDir);
        }

        if ($normalizedOriginalDir !== '' && str_starts_with($normalizedOriginal, $normalizedOriginalDir)) {
            $suffix = ltrim(substr($normalizedOriginal, strlen($normalizedOriginalDir)), '/');
            $mapped = $newProjectDir . ($suffix !== '' ? '/' . $suffix : '');
            return $this->normalizeDocumentRoot($mapped);
        }

        $basename = basename(str_replace('\\', '/', $normalizedOriginal));
        $target = $newProjectDir . '/' . $basename;
        return $this->normalizeDocumentRoot($target);
    }

    private function importDatabaseDump(string $database, string $sql): array
    {
        $dbConfig = $this->config->get('database', []);
        $mysql = $this->config->get('mysql_tools.mysql') ?? $this->config->get('mysql_path') ?? 'mysql';
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = trim((string)($dbConfig['port'] ?? ''));
        $username = $dbConfig['username'] ?? 'root';
        $password = $dbConfig['password'] ?? '';

        $parts = [
            escapeshellcmd($mysql),
            '--host=' . escapeshellarg($host),
            '--user=' . escapeshellarg($username),
            '--default-character-set=utf8mb4',
            escapeshellarg($database),
        ];

        if ($password !== '') {
            $parts[] = '--password=' . escapeshellarg($password);
        }

        if ($port !== '') {
            $parts[] = '--port=' . escapeshellarg($port);
        }

        $command = implode(' ', $parts);
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            return ['success' => false, 'error' => 'Impossible de lancer le client mysql.'];
        }

        fwrite($pipes[0], $sql);
        fclose($pipes[0]);

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            return ['success' => false, 'error' => trim($error ?: 'Erreur lors de l\'import de la base.'), 'debug' => $command];
        }

        return ['success' => true, 'output' => trim($output)];
    }

    private function toFilesystemPath(string $value): string
    {
        $normalized = str_replace('\\', DIRECTORY_SEPARATOR, $value);
        return str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    }
}
