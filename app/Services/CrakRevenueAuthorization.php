<?php

declare(strict_types=1);

namespace LiveCamForge\Services;

use LiveCamForge\Core\Config;
use LiveCamForge\Repositories\SettingsRepository;

final class CrakRevenueAuthorization
{
    public const AUTHORIZED = 'authorized';
    public const CONFIGURED = 'configured';
    public const NOT_AUTHORIZED = 'not_authorized';
    public const ERROR = 'error';
    public const NOT_VERIFIED = 'not_verified';
    public const NOT_CONFIGURED = 'not_configured';

    private const SETTING_KEY = 'crakrevenue.authorization';
    private const SAVED_STATUSES = [self::AUTHORIZED, self::NOT_AUTHORIZED, self::ERROR];

    private bool $loaded = false;
    private array $cachedStored = [];

    public function __construct(private SettingsRepository $settings, private Config $config)
    {
    }

    public function configured(): bool
    {
        return $this->apiKey() !== '' && $this->token() !== '';
    }

    /** @param array<string, string> $statuses */
    public function save(array $statuses): void
    {
        if (!$this->configured()) {
            return;
        }
        $brands = [];
        foreach ($statuses as $brand => $status) {
            $brand = strtolower(trim((string) $brand));
            $status = strtolower(trim((string) $status));
            if (preg_match('/^[a-z0-9_-]{1,50}$/', $brand) === 1
                && in_array($status, self::SAVED_STATUSES, true)
            ) {
                $brands[$brand] = $status;
            }
        }
        $payload = [
            'credential_fingerprint' => $this->fingerprint(),
            'checked_at' => gmdate('c'),
            'brands' => $brands,
        ];
        $this->settings->set(self::SETTING_KEY, json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        ));
        $this->loaded = true;
        $this->cachedStored = $payload;
    }

    /** @return array<string, string> */
    public function statuses(): array
    {
        $stored = $this->stored();

        return is_array($stored['brands'] ?? null) ? $stored['brands'] : [];
    }

    public function statusForBrand(string $brand): string
    {
        if (!$this->configured()) {
            return self::NOT_CONFIGURED;
        }

        return $this->statuses()[strtolower(trim($brand))] ?? self::NOT_VERIFIED;
    }

    public function checkedAt(): ?string
    {
        $stored = $this->stored();
        $checkedAt = $stored['checked_at'] ?? null;

        return is_string($checkedAt) && $checkedAt !== '' ? $checkedAt : null;
    }

    /** @return array<string, mixed> */
    private function stored(): array
    {
        if ($this->loaded) {
            return $this->cachedStored;
        }
        $this->loaded = true;
        if (!$this->configured()) {
            return $this->cachedStored;
        }
        $raw = $this->settings->get(self::SETTING_KEY);
        if (!is_string($raw) || trim($raw) === '') {
            return $this->cachedStored;
        }
        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return $this->cachedStored;
        }
        if (!is_array($decoded)
            || !is_string($decoded['credential_fingerprint'] ?? null)
            || !hash_equals($this->fingerprint(), $decoded['credential_fingerprint'])
        ) {
            return $this->cachedStored;
        }

        $brands = [];
        foreach (is_array($decoded['brands'] ?? null) ? $decoded['brands'] : [] as $brand => $status) {
            if (is_string($brand) && is_string($status) && in_array($status, self::SAVED_STATUSES, true)) {
                $brands[$brand] = $status;
            }
        }
        $decoded['brands'] = $brands;

        $this->cachedStored = $decoded;

        return $this->cachedStored;
    }

    private function fingerprint(): string
    {
        return hash('sha256', $this->apiKey() . "\0" . $this->token());
    }

    private function apiKey(): string
    {
        return trim((string) $this->config->get('crakrevenue.api_key', ''));
    }

    private function token(): string
    {
        return trim((string) $this->config->get('crakrevenue.token', ''));
    }
}
