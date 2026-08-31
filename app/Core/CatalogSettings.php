<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

use LiveCamForge\Repositories\SettingsRepository;
use InvalidArgumentException;

final class CatalogSettings
{
    public const OFFLINE_FALLBACKS = ['profile', 'homepage'];

    public function __construct(
        private SettingsRepository $settings,
        private Config $config,
        private array $enabledProviders
    ) {
        $this->enabledProviders = array_values(array_unique(array_filter(array_map(
            static fn (mixed $provider): string => strtolower(trim((string) $provider)),
            $this->enabledProviders
        ))));
    }

    public function values(): array
    {
        $fallback = strtolower(trim((string) $this->config->get('provider', 'demo')));
        $primary = strtolower(trim((string) ($this->settings->get('catalog.primary_provider') ?? $fallback)));
        if (!in_array($primary, $this->enabledProviders, true)) {
            $primary = $this->enabledProviders[0] ?? 'demo';
        }

        $storedMode = $this->settings->get('catalog.mode') ?? 'single';
        $mode = $storedMode === 'combined' && count($this->enabledProviders) > 1 ? 'combined' : 'single';

        $offlineFallbacks = [];
        foreach ($this->enabledProviders as $provider) {
            $storedFallback = strtolower(trim((string) ($this->settings->get(
                'catalog.offline_fallback.' . $provider
            ) ?? 'profile')));
            // Older builds exposed a misleading "random" option. Preserve those
            // installations safely by treating the stored value as homepage.
            if ($storedFallback === 'random') {
                $storedFallback = 'homepage';
            }
            $offlineFallbacks[$provider] = in_array($storedFallback, self::OFFLINE_FALLBACKS, true)
                ? $storedFallback
                : 'profile';
        }

        return [
            'mode' => $mode,
            'primary_provider' => $primary,
            'show_provider_filter' => ($this->settings->get('catalog.show_provider_filter') ?? '1') !== '0',
            'show_provider_badges' => ($this->settings->get('catalog.show_provider_badges') ?? '1') !== '0',
            'enabled_providers' => $this->enabledProviders,
            'offline_fallbacks' => $offlineFallbacks,
        ];
    }

    public function save(array $input): void
    {
        $mode = (string) ($input['catalog_mode'] ?? 'single');
        $primary = strtolower(trim((string) ($input['primary_provider'] ?? '')));
        if (!in_array($mode, ['single', 'combined'], true)
            || !in_array($primary, $this->enabledProviders, true)
        ) {
            throw new InvalidArgumentException('catalog');
        }
        if ($mode === 'combined' && count($this->enabledProviders) < 2) {
            $mode = 'single';
        }

        $values = [
            'catalog.mode' => $mode,
            'catalog.primary_provider' => $primary,
            'catalog.show_provider_filter' => isset($input['show_provider_filter']) ? '1' : '0',
            'catalog.show_provider_badges' => isset($input['show_provider_badges']) ? '1' : '0',
        ];
        foreach ($this->enabledProviders as $provider) {
            $inputKey = 'offline_fallback_' . $provider;
            if (!array_key_exists($inputKey, $input)) {
                continue;
            }
            $fallback = strtolower(trim((string) $input[$inputKey]));
            if (!in_array($fallback, self::OFFLINE_FALLBACKS, true)) {
                throw new InvalidArgumentException('offline_fallback');
            }
            $values['catalog.offline_fallback.' . $provider] = $fallback;
        }

        $this->settings->setMany($values);
    }
}
