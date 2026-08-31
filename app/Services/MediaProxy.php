<?php

declare(strict_types=1);

namespace LiveCamForge\Services;

final class MediaProxy
{
    private const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'image/avif',
    ];

    public static function serve(
        string $url,
        string $cacheDirectory,
        int $ttlSeconds = 120,
        int $timeoutSeconds = 8,
        bool $cacheEnabled = true
    ): bool
    {
        if (!self::isSafeRemoteUrl($url)) {
            return false;
        }

        $cacheKey = hash('sha256', $url);
        $dataPath = rtrim($cacheDirectory, '/\\') . '/' . $cacheKey . '.bin';
        $metaPath = rtrim($cacheDirectory, '/\\') . '/' . $cacheKey . '.json';
        $ttlSeconds = max(30, min(3600, $ttlSeconds));

        if ($cacheEnabled && self::cached($dataPath, $metaPath, $ttlSeconds)) {
            return self::output($dataPath, $metaPath, $ttlSeconds);
        }

        $context = stream_context_create(['http' => [
            'timeout' => max(2, min(15, $timeoutSeconds)),
            'user_agent' => 'LiveCamForge-MediaProxy/1.0.1',
            'header' => "Accept: image/avif,image/webp,image/png,image/jpeg,image/gif\r\n",
            'follow_location' => 0,
            'ignore_errors' => false,
        ]]);
        $maximumBytes = 6 * 1024 * 1024;
        $body = @file_get_contents($url, false, $context, 0, $maximumBytes);
        if ($body === false || $body === '' || strlen($body) >= $maximumBytes) {
            return $cacheEnabled && is_file($dataPath) && is_file($metaPath)
                ? self::output($dataPath, $metaPath, 30)
                : false;
        }

        $mimeType = self::detectMimeType($body);
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return false;
        }

        if ($cacheEnabled && (is_dir($cacheDirectory) || @mkdir($cacheDirectory, 0775, true)) && is_writable($cacheDirectory)) {
            @file_put_contents($dataPath, $body, LOCK_EX);
            @file_put_contents($metaPath, json_encode(['mime_type' => $mimeType], JSON_UNESCAPED_SLASHES), LOCK_EX);
        }

        header('Content-Type: ' . $mimeType);
        // The disk cache is server-side only. Public/CDN caching could serve a
        // geographically restricted image without running the request filter.
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        echo $body;

        return true;
    }

    public static function purgeUrls(array $urls, string $cacheDirectory): void
    {
        foreach (array_unique(array_filter(array_map('strval', $urls))) as $url) {
            $cacheKey = hash('sha256', $url);
            @unlink(rtrim($cacheDirectory, '/\\') . '/' . $cacheKey . '.bin');
            @unlink(rtrim($cacheDirectory, '/\\') . '/' . $cacheKey . '.json');
        }
    }

    private static function cached(string $dataPath, string $metaPath, int $ttlSeconds): bool
    {
        return is_file($dataPath)
            && is_file($metaPath)
            && filemtime($dataPath) !== false
            && filemtime($dataPath) >= time() - $ttlSeconds;
    }

    private static function output(string $dataPath, string $metaPath, int $maxAge): bool
    {
        $meta = json_decode((string) @file_get_contents($metaPath), true);
        $mimeType = is_array($meta) ? (string) ($meta['mime_type'] ?? '') : '';
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            return false;
        }

        header('Content-Type: ' . $mimeType);
        header('Cache-Control: private, no-store');
        header('X-Content-Type-Options: nosniff');
        readfile($dataPath);

        return true;
    }

    private static function detectMimeType(string $body): string
    {
        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            return (string) $finfo->buffer($body);
        }

        $details = @getimagesizefromstring($body);

        return is_array($details) ? (string) ($details['mime'] ?? '') : '';
    }

    private static function isSafeRemoteUrl(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && $host !== ''
            && strlen($host) <= 253
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && filter_var($host, FILTER_VALIDATE_IP) === false;
    }

}
