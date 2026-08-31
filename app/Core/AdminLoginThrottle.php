<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final class AdminLoginThrottle
{
    public function __construct(
        private string $directory,
        private int $maxAttempts = 5,
        private int $windowSeconds = 300,
        private int $lockoutSeconds = 600,
    ) {
        $this->maxAttempts = max(1, min(50, $this->maxAttempts));
        $this->windowSeconds = max(60, min(86400, $this->windowSeconds));
        $this->lockoutSeconds = max(60, min(86400, $this->lockoutSeconds));
    }

    public function isBlocked(string $clientIdentifier): bool
    {
        $state = $this->read($clientIdentifier);
        return (int) ($state['blocked_until'] ?? 0) > time();
    }

    public function registerFailure(string $clientIdentifier): void
    {
        $now = time();
        $state = $this->read($clientIdentifier);
        $windowStartedAt = (int) ($state['window_started_at'] ?? 0);
        $attempts = (int) ($state['attempts'] ?? 0);

        if ($windowStartedAt <= 0 || $windowStartedAt < $now - $this->windowSeconds) {
            $windowStartedAt = $now;
            $attempts = 0;
        }

        $attempts++;
        $blockedUntil = (int) ($state['blocked_until'] ?? 0);
        if ($attempts >= $this->maxAttempts) {
            $blockedUntil = $now + $this->lockoutSeconds;
            $attempts = 0;
            $windowStartedAt = $now;
        }

        $this->write($clientIdentifier, [
            'window_started_at' => $windowStartedAt,
            'attempts' => $attempts,
            'blocked_until' => $blockedUntil,
        ]);
    }

    public function clear(string $clientIdentifier): void
    {
        $path = $this->path($clientIdentifier);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function read(string $clientIdentifier): array
    {
        $path = $this->path($clientIdentifier);
        if (!is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) @file_get_contents($path), true);
        if (!is_array($decoded)) {
            return [];
        }

        if ((int) ($decoded['blocked_until'] ?? 0) <= time()
            && (int) ($decoded['window_started_at'] ?? 0) < time() - $this->windowSeconds
        ) {
            @unlink($path);
            return [];
        }

        return $decoded;
    }

    private function write(string $clientIdentifier, array $state): void
    {
        if (!is_dir($this->directory) && !@mkdir($this->directory, 0770, true) && !is_dir($this->directory)) {
            return;
        }
        if (!is_writable($this->directory)) {
            return;
        }

        @file_put_contents(
            $this->path($clientIdentifier),
            json_encode($state, JSON_UNESCAPED_SLASHES),
            LOCK_EX
        );
    }

    private function path(string $clientIdentifier): string
    {
        $identifier = trim($clientIdentifier) !== '' ? trim($clientIdentifier) : 'unknown';
        $hash = hash('sha256', 'livecamforge-admin-login|' . $identifier);
        return rtrim($this->directory, '/\\') . '/' . $hash . '.json';
    }
}
