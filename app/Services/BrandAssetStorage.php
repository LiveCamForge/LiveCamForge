<?php

declare(strict_types=1);

namespace LiveCamForge\Services;

use RuntimeException;

final class BrandAssetStorage
{
    private const MAX_BYTES = 2097152;

    private const TYPES = [
        'logo' => [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ],
        'favicon' => [
            'image/png' => 'png',
            'image/x-icon' => 'ico',
            'image/vnd.microsoft.icon' => 'ico',
        ],
    ];

    public function __construct(private string $directory)
    {
    }

    public function save(array $upload, string $kind): string
    {
        if (!isset(self::TYPES[$kind])
            || ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_string($upload['tmp_name'] ?? null)
            || !is_uploaded_file($upload['tmp_name'])
            || (int) ($upload['size'] ?? 0) < 1
            || (int) $upload['size'] > self::MAX_BYTES
        ) {
            throw new RuntimeException('upload');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
        $extension = is_string($mime) ? (self::TYPES[$kind][$mime] ?? null) : null;
        if ($extension === null) {
            throw new RuntimeException('upload');
        }

        if (!is_dir($this->directory) && !mkdir($this->directory, 0755, true) && !is_dir($this->directory)) {
            throw new RuntimeException('directory');
        }

        $filename = $kind . '-' . bin2hex(random_bytes(12)) . '.' . $extension;
        if (!move_uploaded_file($upload['tmp_name'], $this->directory . '/' . $filename)) {
            throw new RuntimeException('move');
        }
        return $filename;
    }

    public function remove(?string $filename): void
    {
        $path = $this->path($filename);
        if ($path !== null && is_file($path)) {
            @unlink($path);
        }
    }

    public function serve(?string $filename): bool
    {
        $path = $this->path($filename);
        if ($path === null || !is_file($path)) {
            return false;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!is_string($mime) || !str_starts_with($mime, 'image/')) {
            return false;
        }

        header('Content-Type: ' . $mime);
        header('Content-Length: ' . (string) filesize($path));
        header('Cache-Control: public, max-age=86400');
        header('X-Content-Type-Options: nosniff');
        readfile($path);
        return true;
    }

    private function path(?string $filename): ?string
    {
        if (!is_string($filename) || $filename === '' || basename($filename) !== $filename) {
            return null;
        }
        return rtrim($this->directory, '/') . '/' . $filename;
    }
}
