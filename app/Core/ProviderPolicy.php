<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final class ProviderPolicy
{
    public function __construct(
        public readonly bool $offlineRetention,
        public readonly int $offlineRetentionDays,
        public readonly bool $indexPerformerPages,
        public readonly bool $includePerformersInSitemap,
        public readonly bool $cacheImages,
    ) {
    }

    public static function for(Config $config, string $provider): self
    {
        $provider = strtolower(trim($provider));
        $defaults = self::arrayValue($config->get('provider_policies.default', []));
        $overrides = self::arrayValue($config->get('provider_policies.' . $provider, []));
        $values = array_replace($defaults, $overrides);

        if ($provider === 'stripchat') {
            $values['offline_retention'] = true;
            $values['offline_retention_days'] = max(1, min(30, (int) ($values['offline_retention_days'] ?? 30)));
            $values['cache_images'] = false;
        } elseif (str_starts_with($provider, 'crakrevenue_')) {
            $values['cache_images'] = false;
        }

        return new self(
            (bool) ($values['offline_retention'] ?? false),
            max(0, min(3650, (int) ($values['offline_retention_days'] ?? 0))),
            (bool) ($values['index_performer_pages'] ?? false),
            (bool) ($values['include_performers_in_sitemap'] ?? false),
            (bool) ($values['cache_images'] ?? false),
        );
    }

    private static function arrayValue(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }
}
