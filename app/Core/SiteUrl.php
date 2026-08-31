<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final class SiteUrl
{
    private string $basePath;
    private string $origin;

    public function __construct(Config $config, array $server)
    {
        $configured = rtrim(trim((string) $config->get('seo.base_url', '')), '/');
        if ($configured !== '' && filter_var($configured, FILTER_VALIDATE_URL)) {
            $parts = parse_url($configured);
            $this->origin = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? 'localhost')
                . (isset($parts['port']) ? ':' . $parts['port'] : '');
            $this->basePath = rtrim((string) ($parts['path'] ?? ''), '/');
            return;
        }

        $https = !empty($server['HTTPS']) && strtolower((string) $server['HTTPS']) !== 'off';
        $scheme = $https ? 'https' : 'http';
        $host = preg_match('/^[A-Za-z0-9.:-]+$/', (string) ($server['HTTP_HOST'] ?? '')) === 1
            ? (string) $server['HTTP_HOST']
            : 'localhost';
        $scriptDirectory = str_replace('\\', '/', dirname((string) ($server['SCRIPT_NAME'] ?? '/index.php')));
        if (basename($scriptDirectory) === 'public') {
            $scriptDirectory = str_replace('\\', '/', dirname($scriptDirectory));
        }
        $this->origin = $scheme . '://' . $host;
        $this->basePath = $scriptDirectory === '/' ? '' : rtrim($scriptDirectory, '/');
    }

    public function path(string $path = ''): string
    {
        $path = ltrim($path, '/');
        return ($this->basePath !== '' ? $this->basePath : '') . '/' . $path;
    }

    public function absolute(string $path = ''): string
    {
        return $this->origin . $this->path($path);
    }

    public function asset(string $path): string
    {
        return $this->path('public/assets/' . ltrim($path, '/'));
    }

    public function landing(string $slug): string
    {
        return $this->path('cams/' . rawurlencode($slug) . '/');
    }

    public function model(string $provider, string $username): string
    {
        return $this->path('model/' . rawurlencode($provider) . '/' . rawurlencode($username) . '/');
    }
}
