<?php

declare(strict_types=1);

namespace LiveCamForge\Providers;

use LiveCamForge\Core\PerformerCountry;
use LiveCamForge\Models\Performer;

final class DemoProvider implements ProviderInterface
{
    public function __construct(
        private string $fixturePath,
        private string $providerName = 'demo',
        private string $providerLabel = 'Demo',
    ) {
    }

    public function name(): string { return $this->providerName; }
    public function displayName(): string { return $this->providerLabel; }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            embed: false,
            roomStatus: false,
            age: true,
            viewers: true,
            tags: true,
            mediaProxy: true,
            affiliateLinks: false,
            offlineFallback: false,
        );
    }

    public function fetch(): array
    {
        $rows = json_decode((string) file_get_contents($this->fixturePath), true, 512, JSON_THROW_ON_ERROR);

        return array_map(function (array $row): Performer {
            $localAsset = trim((string) ($row['asset'] ?? ''));
            $imageUrl = $localAsset !== ''
                ? 'demo://' . $this->providerName . '/' . $localAsset
                : (string) ($row['image_url'] ?? '');
            $previewUrl = $localAsset !== ''
                ? $imageUrl
                : (string) ($row['preview_url'] ?? $imageUrl);

            return new Performer(
            provider: $this->providerName,
            providerId: $row['provider_id'],
            username: $row['username'],
            displayName: $row['display_name'],
            gender: $row['gender'],
            age: $row['age'],
            imageUrl: $imageUrl,
            previewUrl: $previewUrl,
            embedUrl: null,
            roomStatus: 'public',
            roomUrl: '#',
            viewers: $row['viewers'],
            tags: $row['tags'],
            online: true,
            providerNew: array_key_exists('provider_is_new', $row) ? (bool) $row['provider_is_new'] : null,
            countryCode: PerformerCountry::normalize($row['country_code'] ?? null),
            );
        }, $rows);
    }

    public function isEmbedUrlAllowed(string $url): bool { return false; }
    public function player(array $performer, array $options = []): ?ProviderPlayer { return null; }
    public function resolvePlayer(ProviderPlayer $player): ?ProviderPlayer { return null; }
    public function isRoomUrlAllowed(string $url): bool { return false; }

    public function isMediaUrlAllowed(string $url): bool
    {
        if ($this->providerName === 'demo') {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

            return $scheme === 'https'
                && $host === 'placehold.co'
                && filter_var($url, FILTER_VALIDATE_URL) !== false;
        }

        return str_starts_with($url, 'demo://' . $this->providerName . '/');
    }
}
