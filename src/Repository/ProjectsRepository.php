<?php

namespace App\Repository;

use App\Config\AppConfig;
use PDO;
use PDOException;

class ProjectsRepository
{
    private const TABLE_PROJECTS = 'projects';
    private const TABLE_TASKS = 'tasks';
    private const TABLE_VHOSTS = 'vhosts';

    private AppConfig $config;
    private PDO $connection;
    private string $baseProjectsPath;
    private string $projectDetailsFilename;
    private bool $schemaEnsured = false;

    public function __construct(AppConfig $config)
    {
        $this->config = $config;
        $this->connection = $config->getDatabaseConnection();
        $this->baseProjectsPath = $config->getPath('base_projects_path', '') ?? '';
        $this->projectDetailsFilename = $config->get('project_details_filename', 'project-details.json');
        $this->ensureSchema();
    }

    public function getProjectsOverview(): array
    {
        $query = sprintf(
            'SELECT id, name, folder_slug, description, tags, favorite, notes, created_at, last_accessed FROM %s ORDER BY name',
            self::TABLE_PROJECTS
        );

        $statement = $this->connection->query($query);
        $overview = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $overview[$row['name']] = $this->hydrateProjectOverview($row);
        }

        return $overview;
    }

    public function saveProjectsOverview(array $overview): bool
    {
        try {
            $this->connection->beginTransaction();

            foreach ($overview as $name => $data) {
                if (!$this->upsertProjectOverview($name, $data)) {
                    throw new PDOException('Impossible de sauvegarder le projet ' . $name);
                }
            }

            $this->connection->commit();
            return true;
        } catch (\Throwable $error) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            return false;
        }
    }

    public function getProjectDetails(string $projectName): array
    {
        $projectId = $this->fetchProjectId($projectName);

        if ($projectId === null) {
            return $this->createDefaultProjectDetails();
        }

        $projectRow = $this->fetchProjectRowByName($projectName);
        $details = $this->createDefaultProjectDetails();
        $details['notes'] = $projectRow['notes'] ?? '';
        $details['created_at'] = $projectRow['created_at'] ?? $details['created_at'];
        $details['last_accessed'] = $projectRow['last_accessed'] ?? $details['last_accessed'];

        $details['tasks'] = $this->fetchTasks($projectId);
        $details['vhosts'] = $this->fetchVhosts($projectId);

        return $details;
    }

    public function saveProjectDetails(string $projectName, array $details): bool
    {
        $projectId = $this->fetchProjectId($projectName);

        if ($projectId === null) {
            return false;
        }

        $lastAccessed = $details['last_accessed'] ?? date('Y-m-d H:i:s');
        $notes = $details['notes'] ?? '';

        try {
            $this->connection->beginTransaction();

            $statement = $this->connection->prepare(
                'UPDATE ' . self::TABLE_PROJECTS . ' SET notes = :notes, last_accessed = :last_accessed WHERE id = :id'
            );
            $statement->execute([
                ':notes' => $notes,
                ':last_accessed' => $lastAccessed,
                ':id' => $projectId,
            ]);

            $this->replaceTasks($projectId, $details['tasks'] ?? []);
            $this->replaceVhosts($projectId, $details['vhosts'] ?? []);

            $this->connection->commit();
            return true;
        } catch (\Throwable $error) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            return false;
        }
    }

    public function ensureProjectDirectory(string $folderSlug): bool
    {
        $dir = rtrim($this->baseProjectsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $folderSlug;

        if (!is_dir($dir)) {
            return mkdir($dir, 0755, true);
        }

        return true;
    }

    public function hasProject(string $projectName): bool
    {
        return $this->fetchProjectId($projectName) !== null;
    }

    public function saveProjectOverview(string $projectName, array $overview): bool
    {
        return $this->upsertProjectOverview($projectName, $overview);
    }

    public function deleteProjectOverview(string $projectName): bool
    {
        $statement = $this->connection->prepare('DELETE FROM ' . self::TABLE_PROJECTS . ' WHERE name = :name');
        $statement->execute([':name' => $projectName]);
        return $statement->rowCount() > 0;
    }

    public function deleteProjectDetailsFile(string $projectName): bool
    {
        $projectId = $this->fetchProjectId($projectName);
        if ($projectId === null) {
            return true;
        }

        return $this->deleteProjectDetailsRecords($projectId);
    }

    public function removeProjectDirectory(string $folderSlug): bool
    {
        $directory = rtrim($this->baseProjectsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $folderSlug;
        return $this->deleteDirectoryRecursively($directory);
    }

    public function getBaseProjectsPath(): string
    {
        return $this->baseProjectsPath;
    }

    public function getProjectDirectoryPath(string $projectName): string
    {
        return $this->getProjectDirectory($projectName);
    }

    public function renameProjectDirectory(string $currentSlug, string $newSlug): bool
    {
        $currentDir = rtrim($this->baseProjectsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $currentSlug;
        $newDir = rtrim($this->baseProjectsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $newSlug;

        if (!is_dir($currentDir)) {
            return true;
        }

        if (is_dir($newDir)) {
            return false;
        }

        return rename($currentDir, $newDir);
    }

    private function ensureSchema(): void
    {
        if ($this->schemaEnsured) {
            return;
        }

        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE_PROJECTS . ' (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(100) NOT NULL UNIQUE,
                folder_slug VARCHAR(100) NOT NULL,
                description TEXT,
                tags JSON,
                favorite TINYINT(1) NOT NULL DEFAULT 0,
                notes LONGTEXT,
                created_at DATETIME NOT NULL,
                last_accessed DATETIME NOT NULL,
                INDEX idx_folder_slug (folder_slug)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE_TASKS . ' (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                uuid VARCHAR(64) NOT NULL,
                title TEXT NOT NULL,
                status VARCHAR(50) NOT NULL,
                due_date DATE DEFAULT NULL,
                description LONGTEXT,
                priority VARCHAR(50) NOT NULL DEFAULT \'none\',
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE KEY project_task (project_id, uuid),
                FOREIGN KEY (project_id) REFERENCES ' . self::TABLE_PROJECTS . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->connection->exec(
            'CREATE TABLE IF NOT EXISTS ' . self::TABLE_VHOSTS . ' (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                display_name VARCHAR(255) NOT NULL DEFAULT "",
                servername VARCHAR(255) NOT NULL,
                actual_servername VARCHAR(255),
                documentroot TEXT,
                description TEXT,
                php_version VARCHAR(10),
                database_name VARCHAR(64),
                created_at DATETIME NOT NULL,
                updated_at DATETIME DEFAULT NULL,
                UNIQUE KEY project_vhost (project_id, servername),
                FOREIGN KEY (project_id) REFERENCES ' . self::TABLE_PROJECTS . ' (id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->schemaEnsured = true;
    }

    private function hydrateProjectOverview(array $row): array
    {
        return [
            'name' => $row['name'],
            'folder_slug' => $row['folder_slug'] ?? '',
            'description' => $row['description'] ?? '',
            'tags' => $this->decodeTags($row['tags'] ?? null),
            'favorite' => !empty($row['favorite']),
            'created_at' => $row['created_at'],
            'last_accessed' => $row['last_accessed'],
        ];
    }

    private function upsertProjectOverview(string $projectName, array $overview): bool
    {
        $name = trim($projectName);
        if ($name === '') {
            return false;
        }

        $row = $this->fetchProjectRowByName($name);
        $folderSlug = $overview['folder_slug'] ?? $row['folder_slug'] ?? '';
        $createdAt = $overview['created_at'] ?? $row['created_at'] ?? date('Y-m-d H:i:s');
        $lastAccessed = $overview['last_accessed'] ?? $row['last_accessed'] ?? date('Y-m-d H:i:s');
        $tags = $this->encodeTags($overview['tags'] ?? []);
        $favorite = !empty($overview['favorite']) ? 1 : 0;
        $description = $overview['description'] ?? $row['description'] ?? '';

        $notes = $overview['notes'] ?? $row['notes'] ?? '';

        $statement = $this->connection->prepare(
            'INSERT INTO ' . self::TABLE_PROJECTS . ' (name, folder_slug, description, tags, favorite, notes, created_at, last_accessed)
                VALUES (:name, :folder_slug, :description, :tags, :favorite, :notes, :created_at, :last_accessed)
                ON DUPLICATE KEY UPDATE
                    folder_slug = VALUES(folder_slug),
                    description = VALUES(description),
                    tags = VALUES(tags),
                    favorite = VALUES(favorite),
                    notes = VALUES(notes),
                    last_accessed = VALUES(last_accessed)'
        );

        return $statement->execute([
            ':name' => $name,
            ':folder_slug' => $folderSlug,
            ':description' => $description,
            ':tags' => $tags,
            ':favorite' => $favorite,
            ':notes' => $notes,
            ':created_at' => $createdAt,
            ':last_accessed' => $lastAccessed,
        ]);
    }

    private function fetchProjectId(string $projectName): ?int
    {
        $statement = $this->connection->prepare('SELECT id FROM ' . self::TABLE_PROJECTS . ' WHERE name = :name');
        $statement->execute([':name' => $projectName]);
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result['id'] ?? null;
    }

    private function fetchProjectRowByName(string $projectName): array
    {
        $statement = $this->connection->prepare('SELECT * FROM ' . self::TABLE_PROJECTS . ' WHERE name = :name');
        $statement->execute([':name' => $projectName]);
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($result) ? $result : [];
    }

    private function fetchTasks(int $projectId): array
    {
        $statement = $this->connection->prepare(
            'SELECT uuid, title, status, due_date, description, priority, created_at, updated_at
                FROM ' . self::TABLE_TASKS . ' WHERE project_id = :project_id 
                ORDER BY created_at ASC'
        );
        $statement->execute([':project_id' => $projectId]);
        $tasks = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $tasks[] = [
                'id' => $row['uuid'],
                'title' => $row['title'],
                'status' => $row['status'],
                'due_date' => $row['due_date'],
                'description' => $row['description'],
                'priority' => $row['priority'] ?? 'none',
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        return $tasks;
    }

    private function fetchVhosts(int $projectId): array
    {
        $statement = $this->connection->prepare(
            'SELECT display_name, servername, actual_servername, documentroot, description, php_version, database_name, created_at, updated_at
                FROM ' . self::TABLE_VHOSTS . ' WHERE project_id = :project_id ORDER BY created_at ASC'
        );
        $statement->execute([':project_id' => $projectId]);
        $vhosts = [];

        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $vhosts[] = [
                'display_name' => $row['display_name'] ?? '',
                'servername' => $row['servername'],
                'actual_servername' => $row['actual_servername'],
                'documentroot' => $row['documentroot'],
                'description' => $row['description'],
                'php_version' => $row['php_version'],
                'database_name' => $row['database_name'],
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        }

        return $vhosts;
    }

    private function replaceTasks(int $projectId, array $tasks): void
    {
        $delete = $this->connection->prepare('DELETE FROM ' . self::TABLE_TASKS . ' WHERE project_id = :project_id');
        $delete->execute([':project_id' => $projectId]);

        if (empty($tasks)) {
            return;
        }

        $insert = $this->connection->prepare(
            'INSERT INTO ' . self::TABLE_TASKS . ' (project_id, uuid, title, status, due_date, description, priority, created_at, updated_at)
                VALUES (:project_id, :uuid, :title, :status, :due_date, :description, :priority, :created_at, :updated_at)'
        );

        foreach ($tasks as $task) {
            $taskId = trim($task['id'] ?? $task['uuid'] ?? '');
            if ($taskId === '') {
                $taskId = bin2hex(random_bytes(8));
            }

            $insert->execute([
                ':project_id' => $projectId,
                ':uuid' => $taskId,
                ':title' => $task['title'] ?? '',
                ':status' => $task['status'] ?? 'pending',
                ':due_date' => $this->normalizeDate($task['due_date'] ?? ''),
                ':description' => $task['description'] ?? '',
                ':priority' => $task['priority'] ?? 'none',
                ':created_at' => $task['created_at'] ?? date('Y-m-d H:i:s'),
                ':updated_at' => $task['updated_at'] ?? null,
            ]);
        }
    }

    private function replaceVhosts(int $projectId, array $vhosts): void
    {
        $delete = $this->connection->prepare('DELETE FROM ' . self::TABLE_VHOSTS . ' WHERE project_id = :project_id');
        $delete->execute([':project_id' => $projectId]);

        if (empty($vhosts)) {
            return;
        }

        $insert = $this->connection->prepare(
            'INSERT INTO ' . self::TABLE_VHOSTS . ' (project_id, display_name, servername, actual_servername, documentroot, description, php_version, database_name, created_at, updated_at)
                VALUES (:project_id, :display_name, :servername, :actual_servername, :documentroot, :description, :php_version, :database_name, :created_at, :updated_at)'
        );

        foreach ($vhosts as $vhost) {
            $insert->execute([
                ':project_id' => $projectId,
                ':display_name' => $vhost['display_name'] ?? '',
                ':servername' => $vhost['servername'] ?? '',
                ':actual_servername' => $vhost['actual_servername'] ?? $vhost['servername'] ?? '',
                ':documentroot' => $vhost['documentroot'] ?? '',
                ':description' => $vhost['description'] ?? '',
                ':php_version' => $vhost['php_version'] ?? '',
                ':database_name' => $vhost['database_name'] ?? null,
                ':created_at' => $vhost['created_at'] ?? date('Y-m-d H:i:s'),
                ':updated_at' => $vhost['updated_at'] ?? null,
            ]);
        }
    }

    private function normalizeDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }

        return $value;
    }

    private function deleteProjectDetailsRecords(int $projectId): bool
    {
        $deletedTasks = $this->connection->prepare('DELETE FROM ' . self::TABLE_TASKS . ' WHERE project_id = :project_id');
        $deletedTasks->execute([':project_id' => $projectId]);

        $deletedVhosts = $this->connection->prepare('DELETE FROM ' . self::TABLE_VHOSTS . ' WHERE project_id = :project_id');
        $deletedVhosts->execute([':project_id' => $projectId]);

        return true;
    }

    private function decodeTags(?string $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $filtered = array_filter(array_map('trim', $decoded));
        return array_values(array_unique($filtered));
    }

    private function encodeTags(array $tags): string
    {
        $filtered = array_filter(array_map('trim', $tags));
        return json_encode(array_values(array_unique($filtered)), JSON_UNESCAPED_UNICODE);
    }

    private function createDefaultProjectDetails(): array
    {
        $now = date('Y-m-d H:i:s');
        return [
            'vhosts' => [],
            'tasks' => [],
            'notes' => '',
            'created_at' => $now,
            'last_accessed' => $now,
        ];
    }

    private function fetchProjectSlug(string $projectName): string
    {
        $statement = $this->connection->prepare('SELECT folder_slug FROM ' . self::TABLE_PROJECTS . ' WHERE name = :name');
        $statement->execute([':name' => $projectName]);
        $result = $statement->fetch(PDO::FETCH_ASSOC);
        return $result['folder_slug'] ?? $projectName;
    }

    private function getProjectDirectory(string $projectName): string
    {
        $slug = $this->fetchProjectSlug($projectName);
        return rtrim($this->baseProjectsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $slug;
    }

    private function deleteDirectoryRecursively(string $path): bool
    {
        if (!is_dir($path)) {
            return true;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        return @rmdir($path);
    }
}

