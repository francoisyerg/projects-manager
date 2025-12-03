<?php

namespace App\Config;

use PDO;
use PDOException;

class AppConfig
{
    private string $filePath;
    private array $data;
    private ?PDO $databaseConnection = null;

    private function __construct(string $filePath, array $data)
    {
        $this->filePath = $filePath;
        $this->data = $data;
    }

    public static function fromFile(string $path): self
    {
        if (!file_exists($path)) {
            throw new \InvalidArgumentException("Configuration file not found: $path");
        }

        $data = require $path;

        if (!is_array($data)) {
            throw new \RuntimeException("Configuration file must return an array");
        }

        if (isset($data['timezone'])) {
            date_default_timezone_set($data['timezone']);
        }

        return new self($path, $data);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->getNestedValue($this->data, $key);
        return $value ?? $default;
    }

    public function has(string $key): bool
    {
        return $this->getNestedValue($this->data, $key, '__CONFIG_NOT_FOUND__') !== '__CONFIG_NOT_FOUND__';
    }

    public function all(): array
    {
        return $this->data;
    }

    public function getPath(string $key, mixed $default = null): ?string
    {
        $path = $this->get($key, $default);
        if ($path === null) {
            return null;
        }

        return str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);
    }

    public function getConfigFile(): string
    {
        return $this->filePath;
    }

    public function getDatabaseConnection(): PDO
    {
        if ($this->databaseConnection instanceof PDO) {
            return $this->databaseConnection;
        }

        $dbConfig = $this->get('database', []);
        $dsn = $this->buildDsn($dbConfig, true);
        $username = $dbConfig['username'] ?? 'root';
        $password = $dbConfig['password'] ?? '';

        try {
            $this->databaseConnection = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $error) {
            $schema = $this->resolveSchema($dbConfig);
            if ($schema !== '' && $this->isUnknownDatabaseError($error)) {
                $this->createDatabaseIfMissing($dbConfig, $schema, $username, $password);
                $this->databaseConnection = new PDO($dsn, $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } else {
                throw new \RuntimeException('Impossible de se connecter à la base de données : ' . $error->getMessage(), 0, $error);
            }
        }

        return $this->databaseConnection;
    }

    private function buildDsn(array $dbConfig, bool $withSchema): string
    {
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = trim((string)($dbConfig['port'] ?? ''));
        $charset = $dbConfig['charset'] ?? 'utf8mb4';
        $schema = $this->resolveSchema($dbConfig);

        $dsn = "mysql:host={$host}";
        if ($port !== '') {
            $dsn .= ";port={$port}";
        }
        if ($withSchema && $schema !== '') {
            $dsn .= ";dbname={$schema}";
        }
        $dsn .= ";charset={$charset}";

        return $dsn;
    }

    private function resolveSchema(array $dbConfig): string
    {
        return trim((string)($dbConfig['schema'] ?? $dbConfig['database_name'] ?? ''));
    }

    private function createDatabaseIfMissing(array $dbConfig, string $schema, string $username, string $password): void
    {
        $dsn = $this->buildDsn($dbConfig, false);
        $charset = $dbConfig['charset'] ?? 'utf8mb4';
        $pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$schema}` CHARACTER SET {$charset} COLLATE {$charset}_unicode_ci");
    }

    private function isUnknownDatabaseError(PDOException $error): bool
    {
        $sqlState = $error->getCode();
        $driverCode = isset($error->errorInfo[1]) ? (int)$error->errorInfo[1] : null;

        if ($sqlState === 'HY000' && $driverCode === 1049) {
            return true;
        }

        $message = strtolower($error->getMessage());
        return str_contains($message, 'unknown database') || str_contains($message, "base '{$this->resolveSchema($this->get('database', []))}' inconnue");
    }

    private function getNestedValue(array $array, string $key, mixed $default = null): mixed
    {
        if (strpos($key, '.') === false) {
            return $array[$key] ?? $default;
        }

        $parts = explode('.', $key);
        $value = $array;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }

        return $value;
    }
}
