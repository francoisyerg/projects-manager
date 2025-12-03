<?php

namespace App\Service;

use App\Config\AppConfig;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

class SystemVhostManager
{
    private AppConfig $config;
    private string $vhostsFile;
    private string $hostsFile;
    private string $baseProjectsPath;
    private array $phpVersions;

    public function __construct(AppConfig $config)
    {
        $this->config = $config;
        $this->vhostsFile = $config->getPath('vhosts_file') ?? '';
        $this->hostsFile = $config->getPath('hosts_file') ?? '';
        $this->baseProjectsPath = $config->getPath('base_projects_path', '') ?? '';
        $this->phpVersions = $config->get('php_versions', []);
    }

    public function ensureDocumentRoot(string $projectSlug, string $servername, string $documentRoot, string $phpVersion = ''): array
    {
        $normalized = $this->normalizeDocumentRoot($documentRoot);
        if ($normalized === '') {
            return ['success' => false, 'error' => 'DocumentRoot invalide.'];
        }

        $filesystemPath = $this->toFilesystemPath($normalized);
        $dirPermissions = (int)$this->config->get('directory_permissions', 0755);
        $createIndexFile = (bool)$this->config->get('vhost_settings.create_index_file', true);
        $projectPath = $this->buildProjectPath($projectSlug);

        if ($projectPath !== '' && !is_dir($projectPath)) {
            if (!mkdir($projectPath, $dirPermissions, true)) {
                return ['success' => false, 'error' => "Impossible de créer le dossier du projet: $projectPath"];
            }
        }

        if (!is_dir($filesystemPath)) {
            if (!mkdir($filesystemPath, $dirPermissions, true)) {
                return ['success' => false, 'error' => "Impossible de créer le dossier DocumentRoot: $filesystemPath"];
            }
        }

        if ($createIndexFile) {
            $indexPath = rtrim($filesystemPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
            $content = $this->buildIndexContent($projectSlug, $servername, $documentRoot, $phpVersion);
            if (file_put_contents($indexPath, $content) === false) {
                return ['success' => false, 'error' => "Impossible de créer index.php pour $servername"];
            }
            $filePermissions = (int)$this->config->get('file_permissions', 0644);
            @chmod($indexPath, $filePermissions);
        }

        return ['success' => true, 'document_root' => $normalized];
    }

    public function addVhost(string $servername, string $documentRoot, string $phpVersion): bool
    {
        if ($this->vhostsFile === '') {
            return false;
        }

        $entry = $this->buildVhostEntry($servername, $documentRoot, $phpVersion);
        return file_put_contents($this->vhostsFile, $entry, FILE_APPEND | LOCK_EX) !== false;
    }

    public function updateVhost(string $servername, string $documentRoot, string $phpVersion): bool
    {
        if ($this->vhostsFile === '') {
            return false;
        }

        $lines = file_exists($this->vhostsFile) ? file($this->vhostsFile) : [];
        $cleanLines = $this->removeVhostBlock($lines, $servername);
        $cleanLines[] = $this->buildVhostEntry($servername, $documentRoot, $phpVersion);

        return file_put_contents($this->vhostsFile, implode('', $cleanLines)) !== false;
    }

    public function removeVhost(string $servername): bool
    {
        if ($this->vhostsFile === '' || !file_exists($this->vhostsFile)) {
            return true;
        }

        $lines = file($this->vhostsFile);
        $cleanLines = $this->removeVhostBlock($lines, $servername);

        return file_put_contents($this->vhostsFile, implode('', $cleanLines)) !== false;
    }

    public function deleteDocumentRoot(string $documentRoot): array
    {
        $normalized = $this->normalizeDocumentRoot($documentRoot);
        if ($normalized === '') {
            return ['success' => false, 'error' => 'DocumentRoot invalide.'];
        }

        $filesystemPath = $this->toFilesystemPath($normalized);
        $basePath = $this->normalizeDocumentRoot($this->baseProjectsPath);
        if ($basePath !== '') {
            $baseFilesystem = $this->toFilesystemPath($basePath);
            $allowed = $filesystemPath === $baseFilesystem || str_starts_with($filesystemPath, $baseFilesystem . DIRECTORY_SEPARATOR);
            if (!$allowed) {
                return ['success' => false, 'error' => 'DocumentRoot hors des limites autorisées.'];
            }
        }

        if (!is_dir($filesystemPath)) {
            return ['success' => false, 'error' => 'Dossier introuvable.'];
        }

        return $this->deleteDirectoryRecursive($filesystemPath);
    }

    public function updateHostsEntry(string $servername, string $action = 'add'): bool
    {
        if ($this->hostsFile === '') {
            return false;
        }

        $dir = dirname($this->hostsFile);
        if (!file_exists($this->hostsFile) && $dir !== '' && !is_writable($dir)) {
            return false;
        }

        $content = file_exists($this->hostsFile) ? file_get_contents($this->hostsFile) : '';
        $lines = preg_split('/\r?\n/', $content) ?: [];
        $newLines = [];
        $found = false;
        $escaped = preg_quote($servername, '/');

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === '') {
                if ($line === '') {
                    $newLines[] = '';
                } else {
                    $newLines[] = trim($line);
                }
                continue;
            }

            if ($action === 'delete' && preg_match("/^127\.0\.0\.1\s+{$escaped}(\s|$)/", $trimmed)) {
                $found = true;
                continue;
            }

            if (preg_match("/\s{$escaped}(\s|$)/", $trimmed)) {
                $found = true;
                if ($action === 'add') {
                    $newLines[] = "127.0.0.1\t$servername";
                    continue;
                }
                continue;
            }

            $newLines[] = $trimmed;
        }

        if ($action === 'add' && !$found) {
            $newLines[] = "127.0.0.1\t$servername";
        }

        $payload = implode(PHP_EOL, $newLines);
        if ($payload !== '' && !str_ends_with($payload, PHP_EOL)) {
            $payload .= PHP_EOL;
        }

        return file_put_contents($this->hostsFile, $payload) !== false;
    }

    private function buildVhostEntry(string $servername, string $documentRoot, string $phpVersion): string
    {
        $listenAddress = $this->config->get('vhost_settings.listen_address', '*:80');
        $allowOverride = $this->config->get('vhost_settings.directory_permissions.AllowOverride', 'All');
        $require = $this->config->get('vhost_settings.directory_permissions.Require', 'local');
        $documentRoot = rtrim(str_replace('"', '\\"', $documentRoot), '/');
        $directoryPath = $documentRoot . '/';

        return "\n<VirtualHost $listenAddress>\n" .
            "    ServerName $servername\n" .
            "    DocumentRoot \"$documentRoot\"\n" .
            "    <Directory \"$directoryPath\">\n" .
            "        AllowOverride $allowOverride\n" .
            "        Require $require\n" .
            "    </Directory>\n" .
            "</VirtualHost>\n";
    }

    private function removeVhostBlock(array $lines, string $servername): array
    {
        $filtered = [];
        $skip = false;
        $current = '';

        foreach ($lines as $line) {
            if (preg_match('/^\s*<VirtualHost/', $line)) {
                $skip = false;
                $current = '';
            }

            if (preg_match('/^\s*ServerName\s+([^\s]+)/', $line, $matches)) {
                $current = trim($matches[1]);
                if ($current === $servername) {
                    $skip = true;
                    if (!empty($filtered) && preg_match('/^\s*<VirtualHost/', end($filtered))) {
                        array_pop($filtered);
                    }
                    continue;
                }
            }

            if (preg_match('/^\s*<\/VirtualHost>/', $line) && $skip) {
                $skip = false;
                continue;
            }

            if (!$skip) {
                $filtered[] = $line;
            }
        }

        return $filtered;
    }

    private function normalizeDocumentRoot(string $value): string
    {
        $clean = trim($value);
        if ($clean === '') {
            return '';
        }

        $clean = str_replace(['\\', '/'], '/', $clean);
        return rtrim($clean, '/');
    }

    private function toFilesystemPath(string $value): string
    {
        return str_replace('/', DIRECTORY_SEPARATOR, $value);
    }

    private function buildProjectPath(string $projectSlug): string
    {
        $base = $this->normalizeDocumentRoot($this->baseProjectsPath);
        if ($base === '') {
            return '';
        }

        $projectSegment = trim($projectSlug);
        if ($projectSegment === '') {
            return $this->toFilesystemPath($base);
        }

        return $this->toFilesystemPath($base . '/' . $projectSegment);
    }

    private function deleteDirectoryRecursive(string $path): array
    {
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isDir()) {
                    if (!rmdir($item->getPathname())) {
                        return ['success' => false, 'error' => 'Impossible de supprimer ' . $item->getPathname()];
                    }
                    continue;
                }

                if (!unlink($item->getPathname())) {
                    return ['success' => false, 'error' => 'Impossible de supprimer ' . $item->getPathname()];
                }
            }

            if (!rmdir($path)) {
                return ['success' => false, 'error' => 'Impossible de supprimer le dossier parent.'];
            }

            return ['success' => true, 'path' => $path];
        } catch (Throwable $error) {
            return ['success' => false, 'error' => $error->getMessage()];
        }
    }

    private function buildIndexContent(string $projectName, string $servername, string $documentRoot, string $phpVersion = ''): string
    {
        $dateFormat = $this->config->get('date_format', 'd/m/Y H:i');
        $projectTitle = htmlspecialchars($projectName, ENT_QUOTES, 'UTF-8');
        $serverTitle = htmlspecialchars($servername, ENT_QUOTES, 'UTF-8');
        $footer = date($dateFormat);
        $phpVersion = $phpVersion !== '' ? $phpVersion : $this->config->get('default_php_version', '8.3');
        $documentRootDisplay = $this->normalizeDocumentRoot($documentRoot);

        $primaryColor = '#ec8b00';
        $template = <<<'HTML'
<?php
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue sur {{SERVER_TITLE}}</title>
    <style>
        :root {
            font-family: "Segoe UI", "Calibri", sans-serif;
            color: #0f0f0f;
        }
        body {
            margin: 0;
            min-height: 100vh;
            background: radial-gradient(circle at top, #fff0c4, #ffe9c4 40%, #f8f9fb 80%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .vhost-card {
            background: #ffffff;
            border-radius: 18px;
            max-width: 520px;
            width: 100%;
            padding: 2.5rem;
            box-shadow: 0 20px 60px rgba(15, 23, 42, 0.15);
            border: 1px solid rgba(236, 139, 0, 0.2);
            text-align: center;
        }
        .vhost-card h1 {
            margin: 0;
            font-size: 2.6rem;
            color: #111827;
        }
        .vhost-card h2 {
            margin: 0.8rem 0 0.4rem;
            font-size: 1.1rem;
            color: #6b7280;
            letter-spacing: 0.08em;
            font-weight: 500;
        }
        .vhost-card p {
            margin: 0.5rem 0;
            color: #374151;
            line-height: 1.6;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #ecfdf5;
            color: #0f766e;
            border-radius: 999px;
            padding: 0.35rem 1rem;
            font-size: 0.8rem;
            font-weight: 600;
            letter-spacing: 0.06em;
            margin-bottom: 1rem;
        }
        .download-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            border: none;
            background: {{PRIMARY_COLOR}};
            color: white;
            padding: 0.85rem 1.5rem;
            border-radius: 999px;
            font-size: 1rem;
            font-weight: 600;
            text-decoration: none;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }
        .download-btn:hover,
        .download-btn:focus-visible {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(236, 139, 0, 0.35);
        }
        .meta {
            margin-top: 2rem;
            color: #6b7280;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="vhost-card">
        <div class="badge">Projet: {{PROJECT_TITLE}}</div>
        <h1><span aria-hidden="true">🚀</span> {{SERVER_TITLE}}</h1>
        <h2>Votre Virtual Host est prêt à servir vos fichiers</h2>
        <p>Le dossier cible est disponible sous <strong>{{DOCUMENT_ROOT}}</strong> et sera servi via Apache avec PHP {{PHP_VERSION}}.</p>
        <p>Besoin d’un point de départ ? Téléchargez la dernière version francophone de WordPress et lancez votre site immédiatement.</p>
        <a class="download-btn" href="https://fr.wordpress.org/latest-fr_FR.zip" target="_blank" rel="noopener noreferrer">
            📥 Télécharger WordPress
        </a>
        <div class="meta">Créé le {{FOOTER}}</div>
    </div>
</body>
</html>
HTML;

        $content = strtr($template, [
            '{{SERVER_TITLE}}' => $serverTitle,
            '{{PROJECT_TITLE}}' => $projectTitle,
            '{{DOCUMENT_ROOT}}' => htmlspecialchars($documentRootDisplay, ENT_QUOTES, 'UTF-8'),
            '{{PHP_VERSION}}' => htmlspecialchars($phpVersion, ENT_QUOTES, 'UTF-8'),
            '{{FOOTER}}' => $footer,
            '{{PRIMARY_COLOR}}' => $primaryColor,
        ]);

        return $content;
    }

}
