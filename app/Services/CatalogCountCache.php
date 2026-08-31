<?php

declare(strict_types=1);

namespace LiveCamForge\Services;

/** Cache catalog and landing counts by the latest synchronization revision. */
final class CatalogCountCache
{
    public static function get(string $root, string $namespace, array $context): mixed
    {
        $path = self::path($root, $namespace, $context);
        if (!is_file($path)) {
            return null;
        }
        return json_decode((string) file_get_contents($path), true);
    }

    public static function put(string $root, string $namespace, array $context, mixed $value): void
    {
        $directory = self::directory($root);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
        if (!is_dir($directory) || !is_writable($directory)) {
            return;
        }
        @file_put_contents(self::path($root, $namespace, $context), json_encode($value), LOCK_EX);
    }

    public static function invalidate(string $root): void
    {
        $directory = self::directory($root);
        if (!is_dir($directory)) {
            @mkdir($directory, 0775, true);
        }
        if (!is_dir($directory) || !is_writable($directory)) {
            return;
        }
        @file_put_contents($directory . '/revision', (string) microtime(true), LOCK_EX);
        foreach (glob($directory . '/*.json') ?: [] as $cachePath) {
            @unlink($cachePath);
        }
    }

    private static function path(string $root, string $namespace, array $context): string
    {
        $revisionPath = self::directory($root) . '/revision';
        $revision = is_file($revisionPath) ? (string) file_get_contents($revisionPath) : 'initial';
        $key = hash('sha256', serialize(['revision' => $revision, 'context' => $context]));
        $safeNamespace = preg_replace('/[^a-z0-9_-]/i', '', $namespace) ?: 'catalog';

        return self::directory($root) . '/' . $safeNamespace . '-' . $key . '.json';
    }

    private static function directory(string $root): string
    {
        return rtrim($root, '/\\') . '/storage/cache/catalog-counts';
    }
}
