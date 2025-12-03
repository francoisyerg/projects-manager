<?php

namespace App\Config;

class ConfigPersister
{
    public static function update(string $path, array $updates): void
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Configuration file not found: {$path}");
        }

        $existing = require $path;
        if (!is_array($existing)) {
            throw new \RuntimeException('Le fichier de configuration est invalide.');
        }

        $merged = self::mergeRecursive($existing, $updates);
        self::writeConfig($path, $merged);
    }

    private static function mergeRecursive(array $base, array $updates): array
    {
        foreach ($updates as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key])) {
                $base[$key] = self::mergeRecursive($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }

        return $base;
    }

    private static function writeConfig(string $path, array $data): void
    {
        $export = var_export($data, true);
        $content = "<?php\n\nreturn {$export};\n";
        file_put_contents($path, $content);
    }
}
