<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final class InstallerPreflight
{
    /**
     * @return array<int,array{key:string,ok:bool,detail:string}>
     */
    public static function checks(string $root): array
    {
        self::ensureStorageDirectories($root);
        $checks = [];
        $checks[] = [
            'key' => 'php',
            'ok' => version_compare(PHP_VERSION, '8.2.0', '>='),
            'detail' => PHP_VERSION,
        ];
        $checks[] = [
            'key' => 'pdo',
            'ok' => extension_loaded('pdo') && extension_loaded('pdo_mysql'),
            'detail' => extension_loaded('pdo_mysql') ? 'PDO MySQL' : 'PDO MySQL unavailable',
        ];
        $checks[] = [
            'key' => 'schema',
            'ok' => is_readable($root . '/database/schema.sql'),
            'detail' => 'database/schema.sql',
        ];
        $checks[] = [
            'key' => 'config',
            'ok' => is_dir($root . '/config') && is_writable($root . '/config'),
            'detail' => 'config/',
        ];
        $checks[] = [
            'key' => 'storage',
            'ok' => self::directoriesWritable([
                $root . '/storage',
                $root . '/storage/locks',
                $root . '/storage/cache',
                $root . '/storage/logs',
                $root . '/storage/branding',
            ]),
            'detail' => 'storage/',
        ];

        return $checks;
    }

    /** @param array<int,array{key:string,ok:bool,detail:string}> $checks */
    public static function ready(array $checks): bool
    {
        foreach ($checks as $check) {
            if (!$check['ok']) {
                return false;
            }
        }

        return true;
    }

    /** @param string[] $paths */
    private static function directoriesWritable(array $paths): bool
    {
        foreach ($paths as $path) {
            if (!is_dir($path) || !is_writable($path)) {
                return false;
            }
        }

        return true;
    }
    private static function ensureStorageDirectories(string $root): void
    {
        foreach (['storage', 'storage/locks', 'storage/cache', 'storage/logs', 'storage/branding'] as $relative) {
            $path = $root . '/' . $relative;
            if (!is_dir($path)) {
                @mkdir($path, 0775, true);
            }
        }
    }

}
