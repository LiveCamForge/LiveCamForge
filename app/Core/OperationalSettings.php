<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

use LiveCamForge\Repositories\SettingsRepository;
use LiveCamForge\Providers\CrakRevenue\CrakRevenueAdapter;
use LiveCamForge\Services\CrakRevenueAuthorization;
use RuntimeException;

final class OperationalSettings
{
    private const SETTING_KEY = 'runtime.configuration';
    private const LIVEJASMIN_CATEGORIES = ['girl', 'gay', 'transgender', 'lesbian', 'couple'];
    private const EMBED_PLAYER_MODES = ['stream_only', 'full_embed'];

    private array $providers;
    private array $languages;
    private ?CrakRevenueAuthorization $crakRevenueAuthorization = null;

    public function __construct(
        private SettingsRepository $settings,
        private Config $baseConfig,
        array $providers,
        array $languages,
    ) {
        $this->providers = array_values(array_unique(array_filter(array_map(
            static fn (mixed $provider): string => strtolower(trim((string) $provider)),
            $providers
        ), static fn (string $provider): bool => preg_match('/^[a-z0-9_-]{1,50}$/', $provider) === 1)));
        $this->languages = array_values(array_unique(array_filter(array_map(
            static fn (mixed $locale): string => (string) $locale,
            $languages
        ), static fn (string $locale): bool => preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) === 1)));
    }

    public function effectiveConfig(): Config
    {
        return $this->baseConfig->withOverrides($this->storedOverrides());
    }

    public function values(): array
    {
        $config = $this->effectiveConfig();
        $enabled = $this->enabledProviders($config);
        $policies = [];
        $newStrategies = [];
        foreach ($this->providers as $provider) {
            $policy = ProviderPolicy::for($config, $provider);
            $policies[$provider] = [
                'offline_retention' => $policy->offlineRetention,
                'offline_retention_days' => $policy->offlineRetentionDays,
                'index_performer_pages' => $policy->indexPerformerPages,
                'include_performers_in_sitemap' => $policy->includePerformersInSitemap,
                'cache_images' => $policy->cacheImages,
            ];
            $newStrategies[$provider] = NewnessStrategy::for($config, $provider);
        }

        $recruitment = $config->get('recruitment.models', []);
        $recruitment = is_array($recruitment) ? $recruitment : [];
        $webmasterRecruitment = $config->get('recruitment.webmasters', []);
        $webmasterRecruitment = is_array($webmasterRecruitment) ? $webmasterRecruitment : [];

        return [
            'locale' => (string) $config->get('locale', 'en'),
            'fallback_locale' => (string) $config->get('fallback_locale', 'en'),
            'enabled_providers' => $enabled,
            'sync_allow_empty' => (bool) $config->get('sync.allow_empty', false),
            'sync_history_days' => max(1, min(365, (int) $config->get('sync.history_days', 7))),
            'catalog_new_days' => max(1, min(90, (int) $config->get('catalog.new_days', 7))),
            'catalog_performer_types' => PerformerTypes::fromConfig($config),
            'catalog_new_strategies' => $newStrategies,
            'seo_adult_rating' => (bool) $config->get('seo.adult_rating', true),
            'seo_sitemap_max_models' => max(0, min(50000, (int) $config->get('seo.sitemap_max_models', 10000))),
            'player_enabled' => (bool) $config->get('player.enabled', true),
            'player_load_timeout_ms' => max(2000, min(60000, (int) $config->get('player.load_timeout_ms', 8000))),
            'player_aspect_ratio_width' => max(1, min(100, (int) $config->get('player.aspect_ratio_width', 16))),
            'player_aspect_ratio_height' => max(1, min(100, (int) $config->get('player.aspect_ratio_height', 9))),
            'rooms_block_non_public' => (bool) $config->get('rooms.block_non_public', true),
            'media_proxy_enabled' => (bool) $config->get('media_proxy.enabled', true),
            'media_proxy_ttl_seconds' => max(0, min(86400, (int) $config->get('media_proxy.ttl_seconds', 120))),
            'media_proxy_timeout_seconds' => max(2, min(30, (int) $config->get('media_proxy.timeout_seconds', 8))),
            'bongacams_detect_public_ip' => (bool) $config->get('bongacams.detect_public_ip', true),
            'provider_player_modes' => [
                'chaturbate' => $this->playerMode($config->get('chaturbate.player_mode', 'stream_only')),
                'bongacams' => $this->playerMode($config->get('bongacams.player_mode', 'stream_only')),
                'livejasmin' => $this->playerMode($config->get('livejasmin.player_mode', 'stream_only')),
            ],
            'livejasmin_categories' => $this->liveJasminCategoryList(
                $config->get('livejasmin.categories', self::LIVEJASMIN_CATEGORIES)
            ),
            'stripchat_autoplay' => $this->stripchatAutoplay($config->get('stripchat.player.autoplay', 'all')),
            'chaturbate_postback_enabled' => (bool) $config->get('chaturbate.postback.enabled', false),
            'chaturbate_postback_track' => (string) $config->get('chaturbate.postback.track', 'livecamforge'),
            'livejasmin_postback_enabled' => (bool) $config->get('livejasmin.postback.enabled', false),
            'livejasmin_postback_track' => (string) $config->get('livejasmin.postback.track', 'livecamforge'),
            'livejasmin_postback_currency' => (string) $config->get('livejasmin.postback.currency', 'USD'),
            'livejasmin_postback_accept_signups' => (bool) $config->get('livejasmin.postback.accept_signups', false),
            'stripchat_postback_enabled' => (bool) $config->get('stripchat.postback.enabled', false),
            'stripchat_postback_currency' => (string) $config->get('stripchat.postback.currency', 'USD'),
            'crakrevenue_postback_enabled' => (bool) $config->get('crakrevenue.postback.enabled', false),
            'provider_policies' => $policies,
            'recruitment' => $recruitment,
            'webmaster_recruitment' => $webmasterRecruitment,
        ];
    }

    public function save(array $input): void
    {
        $currentConfig = $this->effectiveConfig();
        $locale = (string) ($input['locale'] ?? '');
        $fallbackLocale = (string) ($input['fallback_locale'] ?? '');
        if (!in_array($locale, $this->languages, true) || !in_array($fallbackLocale, $this->languages, true)) {
            throw new RuntimeException('Invalid language configuration.');
        }

        if (array_key_exists('enabled_providers', $input)) {
            $enabled = [];
            foreach (is_array($input['enabled_providers']) ? $input['enabled_providers'] : [] as $provider) {
                $provider = strtolower(trim((string) $provider));
                if (in_array($provider, $this->providers, true)) {
                    $enabled[$provider] = true;
                }
            }
            $enabled = array_keys($enabled);
        } else {
            $enabled = $this->enabledProviders($currentConfig);
        }
        if ($enabled === []) {
            throw new RuntimeException('Enable at least one provider.');
        }
        $this->assertAffiliateRoutesAreExclusive($enabled);
        foreach ($enabled as $provider) {
            $this->assertProviderConfigured($provider);
        }

        $categories = array_key_exists('livejasmin_categories', $input)
            ? $this->liveJasminCategoryList($input['livejasmin_categories'])
            : $this->liveJasminCategoryList($currentConfig->get('livejasmin.categories', self::LIVEJASMIN_CATEGORIES));
        $playerModes = [];
        foreach (['chaturbate', 'bongacams', 'livejasmin'] as $provider) {
            $submitted = is_array($input['provider_player_mode'] ?? null)
                && array_key_exists($provider, $input['provider_player_mode']);
            $playerModes[$provider] = $this->playerMode(
                $submitted ? $input['provider_player_mode'][$provider] : $currentConfig->get($provider . '.player_mode', 'stream_only')
            );
        }
        $performerTypes = array_key_exists('catalog_performer_types', $input)
            ? $this->performerTypeList($input['catalog_performer_types'])
            : PerformerTypes::fromConfig($currentConfig);
        if ($performerTypes === []) {
            throw new RuntimeException('Enable at least one performer type.');
        }
        if (in_array('livejasmin', $enabled, true) && $categories === []) {
            throw new RuntimeException('Configure at least one LiveJasmin category.');
        }
        if (in_array('livejasmin', $enabled, true)
            && $this->liveJasminCategoriesForTypes($categories, $performerTypes) === []
        ) {
            throw new RuntimeException('The selected LiveJasmin categories do not match the enabled performer types.');
        }
        if ($categories === []) {
            $categories = ['girl'];
        }

        $chaturbatePostbackEnabled = isset($input['chaturbate_postback_enabled']);
        $chaturbateSecretAvailable = !isset($input['clear_chaturbate_postback_validation_salt'])
            && (trim((string) ($input['chaturbate_postback_validation_salt'] ?? '')) !== ''
                || trim((string) $this->baseConfig->get('chaturbate.postback.validation_salt', '')) !== '');
        if ($chaturbatePostbackEnabled
            && (bool) $this->baseConfig->get('chaturbate.postback.require_checksum', true)
            && !$chaturbateSecretAvailable
        ) {
            throw new RuntimeException('Configure the Chaturbate postback validation salt before enabling postback tracking.');
        }
        $liveJasminPostbackEnabled = isset($input['livejasmin_postback_enabled']);
        $liveJasminSecretAvailable = !isset($input['clear_livejasmin_postback_secret'])
            && (trim((string) ($input['livejasmin_postback_secret'] ?? '')) !== ''
                || trim((string) $this->baseConfig->get('livejasmin.postback.secret', '')) !== '');
        if ($liveJasminPostbackEnabled
            && (bool) $this->baseConfig->get('livejasmin.postback.require_secret', true)
            && !$liveJasminSecretAvailable
        ) {
            throw new RuntimeException('Configure the LiveJasmin postback secret before enabling postback tracking.');
        }
        $stripchatPostbackEnabled = isset($input['stripchat_postback_enabled']);
        $stripchatSecretAvailable = !isset($input['clear_stripchat_postback_secret'])
            && (trim((string) ($input['stripchat_postback_secret'] ?? '')) !== ''
                || trim((string) $this->baseConfig->get('stripchat.postback.secret', '')) !== '');
        if ($stripchatPostbackEnabled
            && (bool) $this->baseConfig->get('stripchat.postback.require_secret', true)
            && !$stripchatSecretAvailable
        ) {
            throw new RuntimeException('Configure the Stripchat postback secret before enabling postback tracking.');
        }
        $crakRevenuePostbackEnabled = isset($input['crakrevenue_postback_enabled']);
        $crakRevenueSecretAvailable = !isset($input['clear_crakrevenue_postback_secret'])
            && (trim((string) ($input['crakrevenue_postback_secret'] ?? '')) !== ''
                || trim((string) $this->baseConfig->get('crakrevenue.postback.secret', '')) !== '');
        if ($crakRevenuePostbackEnabled
            && (bool) $this->baseConfig->get('crakrevenue.postback.require_secret', true)
            && !$crakRevenueSecretAvailable
        ) {
            throw new RuntimeException('Configure the CrakRevenue postback secret before enabling postback tracking.');
        }

        $policies = [];
        foreach ($this->providers as $provider) {
            $policies[$provider] = [
                'offline_retention' => isset($input['policy_offline_retention'][$provider]),
                'offline_retention_days' => max(0, min(3650, (int) ($input['policy_offline_retention_days'][$provider] ?? 0))),
                'index_performer_pages' => isset($input['policy_index_performer_pages'][$provider]),
                'include_performers_in_sitemap' => isset($input['policy_include_performers_in_sitemap'][$provider]),
                'cache_images' => isset($input['policy_cache_images'][$provider]),
            ];
            if ($provider === 'stripchat') {
                $policies[$provider]['offline_retention'] = true;
                $policies[$provider]['offline_retention_days'] = max(
                    1,
                    min(30, $policies[$provider]['offline_retention_days'] ?: 30)
                );
                $policies[$provider]['cache_images'] = false;
            } elseif (str_starts_with($provider, 'crakrevenue_')) {
                $policies[$provider]['cache_images'] = false;
            }
        }

        $newStrategies = [];
        foreach ($this->providers as $provider) {
            $newStrategies[$provider] = NewnessStrategy::normalize(
                $input['catalog_new_strategy'][$provider] ?? NewnessStrategy::AUTOMATIC
            );
        }

        $recruitmentProviders = [];
        foreach ($this->providers as $provider) {
            $url = trim((string) ($input['recruitment_url'][$provider] ?? ''));
            if ($url !== '' && (!$this->isHttpsUrl($url) || strlen($url) > 2000)) {
                throw new RuntimeException('Recruitment URLs must be valid HTTPS addresses.');
            }
            $recruitmentProviders[$provider] = [
                'enabled' => isset($input['recruitment_provider_enabled'][$provider]) && $url !== '',
                'url' => $url,
                'title' => $this->text($input['recruitment_title'][$provider] ?? '', 100),
                'description' => $this->text($input['recruitment_description'][$provider] ?? '', 500),
            ];
        }

        $trackPattern = '/^[A-Za-z0-9_-]{1,80}$/';
        $chaturbateTrack = trim((string) ($input['chaturbate_postback_track'] ?? 'livecamforge'));
        $liveJasminTrack = trim((string) ($input['livejasmin_postback_track'] ?? 'livecamforge'));
        if (preg_match($trackPattern, $chaturbateTrack) !== 1 || preg_match($trackPattern, $liveJasminTrack) !== 1) {
            throw new RuntimeException('Invalid postback track value.');
        }
        $currency = strtoupper(trim((string) ($input['livejasmin_postback_currency'] ?? 'USD')));
        $stripchatCurrency = strtoupper(trim((string) ($input['stripchat_postback_currency'] ?? 'USD')));
        if (preg_match('/^[A-Z]{3}$/', $currency) !== 1 || preg_match('/^[A-Z]{3}$/', $stripchatCurrency) !== 1) {
            throw new RuntimeException('Invalid postback currency.');
        }

        $overrides = [
            'locale' => $locale,
            'fallback_locale' => $fallbackLocale,
            'provider' => $enabled[0],
            'providers' => ['enabled' => $enabled],
            'sync' => [
                'allow_empty' => isset($input['sync_allow_empty']),
                'history_days' => max(1, min(365, (int) ($input['sync_history_days'] ?? 7))),
            ],
            'catalog' => [
                'performer_types' => $performerTypes,
                'new_days' => max(1, min(90, (int) ($input['catalog_new_days'] ?? 7))),
                'new_strategies' => $newStrategies,
            ],
            'seo' => [
                'adult_rating' => isset($input['seo_adult_rating']),
                'sitemap_max_models' => max(0, min(50000, (int) ($input['seo_sitemap_max_models'] ?? 10000))),
            ],
            'player' => [
                'enabled' => isset($input['player_enabled']),
                'load_timeout_ms' => max(2000, min(60000, (int) ($input['player_load_timeout_ms'] ?? 8000))),
                'aspect_ratio_width' => max(1, min(100, (int) ($input['player_aspect_ratio_width'] ?? 16))),
                'aspect_ratio_height' => max(1, min(100, (int) ($input['player_aspect_ratio_height'] ?? 9))),
            ],
            'rooms' => ['block_non_public' => isset($input['rooms_block_non_public'])],
            'media_proxy' => [
                'enabled' => isset($input['media_proxy_enabled']),
                'ttl_seconds' => max(0, min(86400, (int) ($input['media_proxy_ttl_seconds'] ?? 120))),
                'timeout_seconds' => max(2, min(30, (int) ($input['media_proxy_timeout_seconds'] ?? 8))),
            ],
            'provider_policies' => $policies,
            'bongacams' => [
                'detect_public_ip' => array_key_exists('bongacams_detect_public_ip', $input)
                    ? isset($input['bongacams_detect_public_ip'])
                    : (bool) $currentConfig->get('bongacams.detect_public_ip', true),
                'player_mode' => $playerModes['bongacams'],
            ],
            'livejasmin' => [
                'categories' => $categories,
                'player_mode' => $playerModes['livejasmin'],
                'postback' => [
                    'enabled' => $liveJasminPostbackEnabled,
                    'track' => $liveJasminTrack,
                    'currency' => $currency,
                    'accept_signups' => isset($input['livejasmin_postback_accept_signups']),
                ],
            ],
            'chaturbate' => [
                'player_mode' => $playerModes['chaturbate'],
                'postback' => [
                    'enabled' => $chaturbatePostbackEnabled,
                    'track' => $chaturbateTrack,
                ],
            ],
            'stripchat' => [
                'player' => [
                    'autoplay' => $this->stripchatAutoplay($input['stripchat_autoplay'] ?? $currentConfig->get('stripchat.player.autoplay', 'all')),
                ],
                'postback' => [
                    'enabled' => $stripchatPostbackEnabled,
                    'currency' => $stripchatCurrency,
                ],
            ],
            'crakrevenue' => ['postback' => [
                'enabled' => $crakRevenuePostbackEnabled,
            ]],
            'recruitment' => [
                'models' => is_array($currentConfig->get('recruitment.models', []))
                    ? $currentConfig->get('recruitment.models', [])
                    : [],
                'webmasters' => is_array($currentConfig->get('recruitment.webmasters', []))
                    ? $currentConfig->get('recruitment.webmasters', [])
                    : [],
            ],
        ];

        $this->settings->set(self::SETTING_KEY, json_encode(
            $overrides,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    public function saveProviderIntegrations(array $input): void
    {
        $overrides = $this->storedOverrides();
        $currentConfig = $this->effectiveConfig();

        $categories = $this->liveJasminCategoryList($input['livejasmin_categories'] ?? []);
        if ($categories === []) {
            throw new RuntimeException('Configure at least one LiveJasmin category.');
        }

        $overrides['chaturbate']['player_mode'] = $this->playerMode($input['provider_player_mode']['chaturbate'] ?? 'stream_only');
        $overrides['bongacams']['player_mode'] = $this->playerMode($input['provider_player_mode']['bongacams'] ?? 'stream_only');
        $overrides['bongacams']['detect_public_ip'] = isset($input['bongacams_detect_public_ip']);
        $overrides['livejasmin']['player_mode'] = $this->playerMode($input['provider_player_mode']['livejasmin'] ?? 'stream_only');
        $overrides['livejasmin']['categories'] = $categories;
        $overrides['stripchat']['player']['autoplay'] = $this->stripchatAutoplay($input['stripchat_autoplay'] ?? 'all');

        $trackPattern = '/^[A-Za-z0-9_-]{1,80}$/';
        $chaturbateTrack = trim((string) ($input['chaturbate_postback_track'] ?? 'livecamforge'));
        $liveJasminTrack = trim((string) ($input['livejasmin_postback_track'] ?? 'livecamforge'));
        if (preg_match($trackPattern, $chaturbateTrack) !== 1 || preg_match($trackPattern, $liveJasminTrack) !== 1) {
            throw new RuntimeException('Invalid postback track value.');
        }
        $liveJasminCurrency = strtoupper(trim((string) ($input['livejasmin_postback_currency'] ?? 'USD')));
        $stripchatCurrency = strtoupper(trim((string) ($input['stripchat_postback_currency'] ?? 'USD')));
        if (preg_match('/^[A-Z]{3}$/', $liveJasminCurrency) !== 1 || preg_match('/^[A-Z]{3}$/', $stripchatCurrency) !== 1) {
            throw new RuntimeException('Invalid postback currency.');
        }

        $chaturbatePostbackEnabled = isset($input['chaturbate_postback_enabled']);
        $chaturbateSecretAvailable = !isset($input['clear_chaturbate_postback_validation_salt'])
            && (trim((string) ($input['chaturbate_postback_validation_salt'] ?? '')) !== ''
                || trim((string) $this->baseConfig->get('chaturbate.postback.validation_salt', '')) !== '');
        if ($chaturbatePostbackEnabled
            && (bool) $this->baseConfig->get('chaturbate.postback.require_checksum', true)
            && !$chaturbateSecretAvailable
        ) {
            throw new RuntimeException('Configure the Chaturbate postback validation salt before enabling postback tracking.');
        }
        $liveJasminPostbackEnabled = isset($input['livejasmin_postback_enabled']);
        $liveJasminSecretAvailable = !isset($input['clear_livejasmin_postback_secret'])
            && (trim((string) ($input['livejasmin_postback_secret'] ?? '')) !== ''
                || trim((string) $this->baseConfig->get('livejasmin.postback.secret', '')) !== '');
        if ($liveJasminPostbackEnabled
            && (bool) $this->baseConfig->get('livejasmin.postback.require_secret', true)
            && !$liveJasminSecretAvailable
        ) {
            throw new RuntimeException('Configure the LiveJasmin postback secret before enabling postback tracking.');
        }
        $stripchatPostbackEnabled = isset($input['stripchat_postback_enabled']);
        $stripchatSecretAvailable = !isset($input['clear_stripchat_postback_secret'])
            && (trim((string) ($input['stripchat_postback_secret'] ?? '')) !== ''
                || trim((string) $this->baseConfig->get('stripchat.postback.secret', '')) !== '');
        if ($stripchatPostbackEnabled
            && (bool) $this->baseConfig->get('stripchat.postback.require_secret', true)
            && !$stripchatSecretAvailable
        ) {
            throw new RuntimeException('Configure the Stripchat postback secret before enabling postback tracking.');
        }
        $crakRevenuePostbackEnabled = isset($input['crakrevenue_postback_enabled']);
        $crakRevenueSecretAvailable = !isset($input['clear_crakrevenue_postback_secret'])
            && (trim((string) ($input['crakrevenue_postback_secret'] ?? '')) !== ''
                || trim((string) $this->baseConfig->get('crakrevenue.postback.secret', '')) !== '');
        if ($crakRevenuePostbackEnabled
            && (bool) $this->baseConfig->get('crakrevenue.postback.require_secret', true)
            && !$crakRevenueSecretAvailable
        ) {
            throw new RuntimeException('Configure the CrakRevenue postback secret before enabling postback tracking.');
        }

        $overrides['chaturbate']['postback'] = [
            'enabled' => $chaturbatePostbackEnabled,
            'track' => $chaturbateTrack,
        ];
        $overrides['livejasmin']['postback'] = [
            'enabled' => $liveJasminPostbackEnabled,
            'track' => $liveJasminTrack,
            'currency' => $liveJasminCurrency,
            'accept_signups' => isset($input['livejasmin_postback_accept_signups']),
        ];
        $overrides['stripchat']['postback'] = [
            'enabled' => $stripchatPostbackEnabled,
            'currency' => $stripchatCurrency,
        ];
        $overrides['crakrevenue']['postback'] = [
            'enabled' => $crakRevenuePostbackEnabled,
        ];

        $this->settings->set(self::SETTING_KEY, json_encode(
            $overrides,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    public function saveLanguages(array $input): void
    {
        $locale = (string) ($input['locale'] ?? '');
        $fallbackLocale = (string) ($input['fallback_locale'] ?? '');
        if (!in_array($locale, $this->languages, true) || !in_array($fallbackLocale, $this->languages, true)) {
            throw new RuntimeException('Invalid language configuration.');
        }
        $overrides = $this->storedOverrides();
        $overrides['locale'] = $locale;
        $overrides['fallback_locale'] = $fallbackLocale;
        $this->settings->set(self::SETTING_KEY, json_encode($overrides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function saveTechnicalSettings(array $input): void
    {
        $overrides = $this->storedOverrides();
        $currentConfig = $this->effectiveConfig();

        $newStrategies = [];
        foreach ($this->providers as $provider) {
            $newStrategies[$provider] = NewnessStrategy::normalize(
                $input['catalog_new_strategy'][$provider] ?? $currentConfig->get('catalog.new_strategies.' . $provider, NewnessStrategy::AUTOMATIC)
            );
        }

        $policies = [];
        foreach ($this->providers as $provider) {
            $existingPolicy = ProviderPolicy::for($currentConfig, $provider);
            $days = max(0, min(3650, (int) ($input['policy_offline_retention_days'][$provider] ?? $existingPolicy->offlineRetentionDays)));
            $policies[$provider] = [
                'offline_retention' => isset($input['policy_offline_retention'][$provider]),
                'offline_retention_days' => $days,
                'index_performer_pages' => isset($input['policy_index_performer_pages'][$provider]),
                'include_performers_in_sitemap' => isset($input['policy_include_performers_in_sitemap'][$provider]),
                'cache_images' => isset($input['policy_cache_images'][$provider]),
            ];
            if ($provider === 'stripchat') {
                $policies[$provider]['offline_retention'] = true;
                $policies[$provider]['offline_retention_days'] = max(1, min(30, $days ?: 30));
                $policies[$provider]['cache_images'] = false;
            } elseif (str_starts_with($provider, 'crakrevenue_')) {
                $policies[$provider]['cache_images'] = false;
            }
        }

        $overrides['sync'] = [
            'allow_empty' => isset($input['sync_allow_empty']),
            'history_days' => max(1, min(365, (int) ($input['sync_history_days'] ?? 7))),
        ];
        $overrides['catalog'] = array_replace(is_array($overrides['catalog'] ?? null) ? $overrides['catalog'] : [], [
            'new_days' => max(1, min(90, (int) ($input['catalog_new_days'] ?? 7))),
            'new_strategies' => $newStrategies,
        ]);
        $overrides['seo'] = [
            'adult_rating' => isset($input['seo_adult_rating']),
            'sitemap_max_models' => max(0, min(50000, (int) ($input['seo_sitemap_max_models'] ?? 10000))),
        ];
        $overrides['player'] = [
            'enabled' => isset($input['player_enabled']),
            'load_timeout_ms' => max(2000, min(60000, (int) ($input['player_load_timeout_ms'] ?? 8000))),
            'aspect_ratio_width' => max(1, min(100, (int) ($input['player_aspect_ratio_width'] ?? 16))),
            'aspect_ratio_height' => max(1, min(100, (int) ($input['player_aspect_ratio_height'] ?? 9))),
        ];
        $overrides['rooms'] = ['block_non_public' => isset($input['rooms_block_non_public'])];
        $overrides['media_proxy'] = [
            'enabled' => isset($input['media_proxy_enabled']),
            'ttl_seconds' => max(0, min(86400, (int) ($input['media_proxy_ttl_seconds'] ?? 120))),
            'timeout_seconds' => max(2, min(30, (int) ($input['media_proxy_timeout_seconds'] ?? 8))),
        ];
        $overrides['provider_policies'] = $policies;

        $this->settings->set(self::SETTING_KEY, json_encode($overrides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function saveRecruitment(array $input): void
    {
        $current = $this->effectiveConfig()->get('recruitment.models', []);
        $current = is_array($current) ? $current : [];

        // Backward compatibility: older releases used "title" as both SEO title and H1.
        $legacyTitle = $current['title'] ?? [];
        $eyebrow = $this->localizedInput(
            $input['recruitment_eyebrow'] ?? [],
            $current['eyebrow'] ?? [],
            100
        );
        $seoTitle = $this->localizedInput(
            $input['recruitment_seo_title'] ?? [],
            $current['seo_title'] ?? $legacyTitle,
            160
        );
        $heading = $this->localizedInput(
            $input['recruitment_heading'] ?? [],
            $current['heading'] ?? $legacyTitle,
            160
        );
        $description = $this->localizedInput(
            $input['recruitment_meta_description'] ?? [],
            $current['description'] ?? $current['intro'] ?? [],
            320
        );
        $pageIntro = $this->localizedInput(
            $input['recruitment_page_intro'] ?? [],
            $current['intro'] ?? [],
            1500
        );
        $body = $this->localizedInput(
            $input['recruitment_body'] ?? [],
            $current['body'] ?? [],
            30000
        );
        $faq = $this->localizedFaqInput(
            $input['recruitment_faq_question'] ?? [],
            $input['recruitment_faq_answer'] ?? [],
            $current['faq'] ?? []
        );

        $recruitmentProviders = [];
        $currentProviders = is_array($current['providers'] ?? null) ? $current['providers'] : [];
        foreach ($this->providers as $provider) {
            $url = trim((string) ($input['recruitment_url'][$provider] ?? ''));
            if ($url !== '' && (!$this->isHttpsUrl($url) || strlen($url) > 2000)) {
                throw new RuntimeException('Recruitment URLs must be valid HTTPS addresses.');
            }
            $existing = is_array($currentProviders[$provider] ?? null) ? $currentProviders[$provider] : [];
            $recruitmentProviders[$provider] = [
                'enabled' => isset($input['recruitment_provider_enabled'][$provider]) && $url !== '',
                'url' => $url,
                'title' => $this->localizedInput(
                    is_array($input['recruitment_title'][$provider] ?? null) ? $input['recruitment_title'][$provider] : [],
                    $existing['title'] ?? [],
                    100
                ),
                'description' => $this->localizedInput(
                    is_array($input['recruitment_description'][$provider] ?? null) ? $input['recruitment_description'][$provider] : [],
                    $existing['description'] ?? [],
                    500
                ),
            ];
        }

        $overrides = $this->storedOverrides();
        $overrides['recruitment']['models'] = [
            'enabled' => isset($input['recruitment_enabled']),
            'index' => isset($input['recruitment_index']),
            'eyebrow' => $eyebrow,
            'seo_title' => $seoTitle,
            'heading' => $heading,
            'description' => $description,
            'intro' => $pageIntro,
            'body' => $body,
            'faq' => $faq,
            'providers' => $recruitmentProviders,
        ];
        $this->settings->set(self::SETTING_KEY, json_encode($overrides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function saveWebmasterRecruitment(array $input): void
    {
        $current = $this->effectiveConfig()->get('recruitment.webmasters', []);
        $current = is_array($current) ? $current : [];
        $ctaUrl = trim((string) ($input['webmaster_recruitment_cta_url'] ?? ''));
        if ($ctaUrl !== '' && (!$this->isHttpsUrl($ctaUrl) || strlen($ctaUrl) > 2000)) {
            throw new RuntimeException('Webmaster recruitment CTA URL must be a valid HTTPS address.');
        }

        $overrides = $this->storedOverrides();
        $overrides['recruitment']['webmasters'] = [
            'enabled' => isset($input['webmaster_recruitment_enabled']),
            'index' => isset($input['webmaster_recruitment_index']),
            'eyebrow' => $this->localizedInput(
                $input['webmaster_recruitment_eyebrow'] ?? [],
                $current['eyebrow'] ?? [],
                100
            ),
            'seo_title' => $this->localizedInput(
                $input['webmaster_recruitment_seo_title'] ?? [],
                $current['seo_title'] ?? [],
                160
            ),
            'heading' => $this->localizedInput(
                $input['webmaster_recruitment_heading'] ?? [],
                $current['heading'] ?? [],
                160
            ),
            'description' => $this->localizedInput(
                $input['webmaster_recruitment_meta_description'] ?? [],
                $current['description'] ?? [],
                320
            ),
            'intro' => $this->localizedInput(
                $input['webmaster_recruitment_intro'] ?? [],
                $current['intro'] ?? [],
                1500
            ),
            'body' => $this->localizedInput(
                $input['webmaster_recruitment_body'] ?? [],
                $current['body'] ?? [],
                30000
            ),
            'cta_label' => $this->localizedInput(
                $input['webmaster_recruitment_cta_label'] ?? [],
                $current['cta_label'] ?? [],
                160
            ),
            'cta_url' => $ctaUrl,
            'faq' => $this->localizedFaqInput(
                $input['webmaster_recruitment_faq_question'] ?? [],
                $input['webmaster_recruitment_faq_answer'] ?? [],
                $current['faq'] ?? []
            ),
        ];
        $this->settings->set(self::SETTING_KEY, json_encode($overrides, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @return array<string, list<array{question:string,answer:string}>> */
    private function localizedFaqInput(mixed $questions, mixed $answers, mixed $existing): array
    {
        $questionMap = is_array($questions) ? $questions : [];
        $answerMap = is_array($answers) ? $answers : [];
        $existing = is_array($existing) ? $existing : [];
        $result = [];

        foreach ($this->languages as $locale) {
            $localeQuestions = is_array($questionMap[$locale] ?? null) ? $questionMap[$locale] : [];
            $localeAnswers = is_array($answerMap[$locale] ?? null) ? $answerMap[$locale] : [];
            $existingItems = [];
            foreach ($existing as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $question = $item['question'] ?? '';
                $answer = $item['answer'] ?? '';
                $existingItems[] = [
                    'question' => is_array($question) ? ($question[$locale] ?? '') : '',
                    'answer' => is_array($answer) ? ($answer[$locale] ?? '') : '',
                ];
            }

            $items = [];
            for ($index = 0; $index < 5; $index++) {
                $question = $this->text(
                    array_key_exists($index, $localeQuestions)
                        ? $localeQuestions[$index]
                        : ($existingItems[$index]['question'] ?? ''),
                    300
                );
                $answer = $this->text(
                    array_key_exists($index, $localeAnswers)
                        ? $localeAnswers[$index]
                        : ($existingItems[$index]['answer'] ?? ''),
                    2000
                );
                if ($question !== '' && $answer !== '') {
                    $items[] = ['question' => $question, 'answer' => $answer];
                }
            }
            $result[$locale] = $items;
        }

        return $result;
    }

    /** @return array<string, string> */
    private function localizedInput(mixed $submitted, mixed $existing, int $maximum): array
    {
        $submitted = is_array($submitted) ? $submitted : [];
        $existingMap = is_array($existing) ? $existing : [];
        if (!is_array($existing) && is_scalar($existing) && trim((string) $existing) !== '') {
            $existingMap[(string) $this->effectiveConfig()->get('locale', $this->languages[0] ?? 'en')] = trim((string) $existing);
        }
        $values = [];
        foreach ($this->languages as $locale) {
            $raw = array_key_exists($locale, $submitted) ? $submitted[$locale] : ($existingMap[$locale] ?? '');
            $values[$locale] = $this->text($raw, $maximum);
        }
        return $values;
    }

    public function saveCatalogSources(array $input): void
    {
        $enabled = [];
        foreach (is_array($input['enabled_providers'] ?? null) ? $input['enabled_providers'] : [] as $provider) {
            $provider = strtolower(trim((string) $provider));
            if (in_array($provider, $this->providers, true)) {
                $enabled[$provider] = true;
            }
        }
        $enabled = array_keys($enabled);
        if ($enabled === []) {
            throw new RuntimeException('Enable at least one provider.');
        }
        $this->assertAffiliateRoutesAreExclusive($enabled);
        foreach ($enabled as $provider) {
            $this->assertProviderConfigured($provider);
        }

        $performerTypes = $this->performerTypeList($input['catalog_performer_types'] ?? []);
        if ($performerTypes === []) {
            throw new RuntimeException('Enable at least one performer type.');
        }

        $config = $this->effectiveConfig();
        $categories = $this->liveJasminCategoryList(
            $config->get('livejasmin.categories', self::LIVEJASMIN_CATEGORIES)
        );
        if (in_array('livejasmin', $enabled, true) && $categories === []) {
            throw new RuntimeException('Configure at least one LiveJasmin category in Integrations.');
        }
        if (in_array('livejasmin', $enabled, true)
            && $this->liveJasminCategoriesForTypes($categories, $performerTypes) === []
        ) {
            throw new RuntimeException('The selected LiveJasmin categories do not match the enabled performer types.');
        }

        $overrides = $this->storedOverrides();
        $overrides['provider'] = $enabled[0];
        $overrides['providers']['enabled'] = $enabled;
        $overrides['catalog']['performer_types'] = $performerTypes;

        $this->settings->set(self::SETTING_KEY, json_encode(
            $overrides,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        ));
    }

    public function seed(string $locale, string $provider): void
    {
        if ($this->settings->get(self::SETTING_KEY) !== null) {
            return;
        }
        if (!in_array($locale, $this->languages, true)) {
            $locale = $this->languages[0] ?? 'en';
        }
        if (!in_array($provider, $this->providers, true)) {
            $provider = 'demo';
        }
        $this->settings->set(self::SETTING_KEY, json_encode([
            'locale' => $locale,
            'fallback_locale' => 'en',
            'provider' => $provider,
            'providers' => ['enabled' => [$provider]],
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function reset(): void
    {
        $this->settings->set(self::SETTING_KEY, null);
    }

    public function resetIntegrations(): void
    {
        $config = $this->effectiveConfig();
        $enabled = $this->enabledProviders($config);
        $performerTypes = PerformerTypes::fromConfig($config);
        $recruitment = $config->get('recruitment.models', []);
        $webmasterRecruitment = $config->get('recruitment.webmasters', []);
        $this->settings->set(self::SETTING_KEY, json_encode([
            'locale' => (string) $config->get('locale', 'en'),
            'fallback_locale' => (string) $config->get('fallback_locale', 'en'),
            'provider' => $enabled[0] ?? 'demo',
            'providers' => ['enabled' => $enabled ?: ['demo']],
            'catalog' => ['performer_types' => $performerTypes ?: PerformerTypes::VALUES],
            'recruitment' => [
                'models' => is_array($recruitment) ? $recruitment : [],
                'webmasters' => is_array($webmasterRecruitment) ? $webmasterRecruitment : [],
            ],
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /** @return list<string> */
    public static function liveJasminCategories(): array
    {
        return self::LIVEJASMIN_CATEGORIES;
    }

    private function storedOverrides(): array
    {
        $stored = $this->settings->get(self::SETTING_KEY);
        if ($stored === null || trim($stored) === '') {
            return [];
        }
        try {
            $decoded = json_decode($stored, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return [];
        }
        if (!is_array($decoded)) {
            return [];
        }

        // Only operational keys may come from the database. Credentials, database,
        // Admin/session, GeoIP trust and public base URL always remain file-owned.
        $allowed = [];
        foreach (['locale', 'fallback_locale', 'provider'] as $key) {
            if (isset($decoded[$key]) && is_string($decoded[$key])) {
                $allowed[$key] = $decoded[$key];
            }
        }
        $paths = [
            'providers' => ['enabled'],
            'sync' => ['allow_empty', 'history_days'],
            'catalog' => ['performer_types', 'new_days', 'new_strategies'],
            'seo' => ['adult_rating', 'sitemap_max_models'],
            'player' => ['enabled', 'load_timeout_ms', 'aspect_ratio_width', 'aspect_ratio_height'],
            'rooms' => ['block_non_public'],
            'media_proxy' => ['enabled', 'ttl_seconds', 'timeout_seconds'],
            'bongacams' => ['detect_public_ip', 'player_mode'],
        ];
        foreach ($paths as $section => $keys) {
            if (!isset($decoded[$section]) || !is_array($decoded[$section])) {
                continue;
            }
            foreach ($keys as $key) {
                if (array_key_exists($key, $decoded[$section])) {
                    $allowed[$section][$key] = $decoded[$section][$key];
                }
            }
        }
        if (isset($decoded['provider_policies']) && is_array($decoded['provider_policies'])) {
            $allowed['provider_policies'] = $decoded['provider_policies'];
        }
        if (isset($decoded['livejasmin']['categories']) && is_array($decoded['livejasmin']['categories'])) {
            $allowed['livejasmin']['categories'] = $decoded['livejasmin']['categories'];
        }
        foreach (['chaturbate', 'livejasmin'] as $provider) {
            if (isset($decoded[$provider]['player_mode']) && is_string($decoded[$provider]['player_mode'])) {
                $allowed[$provider]['player_mode'] = $this->playerMode($decoded[$provider]['player_mode']);
            }
        }
        if (isset($decoded['stripchat']['player']['autoplay']) && is_string($decoded['stripchat']['player']['autoplay'])) {
            $allowed['stripchat']['player']['autoplay'] = $this->stripchatAutoplay($decoded['stripchat']['player']['autoplay']);
        }
        foreach (['chaturbate', 'livejasmin', 'stripchat', 'crakrevenue'] as $provider) {
            if (isset($decoded[$provider]['postback']) && is_array($decoded[$provider]['postback'])) {
                $postbackKeys = match ($provider) {
                    'chaturbate' => ['enabled', 'track'],
                    'stripchat' => ['enabled', 'currency'],
                    'crakrevenue' => ['enabled'],
                    default => ['enabled', 'track', 'currency', 'accept_signups'],
                };
                foreach ($postbackKeys as $key) {
                    if (array_key_exists($key, $decoded[$provider]['postback'])) {
                        $allowed[$provider]['postback'][$key] = $decoded[$provider]['postback'][$key];
                    }
                }
            }
        }
        if (isset($decoded['recruitment']['models']) && is_array($decoded['recruitment']['models'])) {
            $modelRecruitment = $decoded['recruitment']['models'];
            foreach (['enabled', 'index', 'seo_title', 'heading', 'description', 'intro', 'body', 'faq', 'providers'] as $key) {
                if (array_key_exists($key, $modelRecruitment)) {
                    $allowed['recruitment']['models'][$key] = $modelRecruitment[$key];
                }
            }
            // 0.24.x–0.27.8 stored one localized "title" for both the document title and H1.
            if (array_key_exists('title', $modelRecruitment)) {
                if (!array_key_exists('seo_title', $modelRecruitment)) {
                    $allowed['recruitment']['models']['seo_title'] = $modelRecruitment['title'];
                }
                if (!array_key_exists('heading', $modelRecruitment)) {
                    $allowed['recruitment']['models']['heading'] = $modelRecruitment['title'];
                }
            }
        }
        if (isset($decoded['recruitment']['webmasters']) && is_array($decoded['recruitment']['webmasters'])) {
            foreach (['enabled', 'index', 'seo_title', 'heading', 'description', 'intro', 'body', 'cta_label', 'cta_url', 'faq'] as $key) {
                if (array_key_exists($key, $decoded['recruitment']['webmasters'])) {
                    $allowed['recruitment']['webmasters'][$key] = $decoded['recruitment']['webmasters'][$key];
                }
            }
        }
        return $allowed;
    }

    private function playerMode(mixed $value): string
    {
        $mode = strtolower(trim((string) $value));

        return in_array($mode, self::EMBED_PLAYER_MODES, true) ? $mode : 'stream_only';
    }

    private function stripchatAutoplay(mixed $value): string
    {
        $value = trim((string) $value);

        return in_array($value, ['all', 'notAtAll', 'playButton'], true) ? $value : 'all';
    }

    private function enabledProviders(Config $config): array
    {
        $providers = $config->get('providers.enabled', []);
        $providers = is_array($providers) ? $providers : [];
        array_unshift($providers, (string) $config->get('provider', 'demo'));
        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $provider): string => strtolower(trim((string) $provider)),
            $providers
        ), fn (string $provider): bool => in_array($provider, $this->providers, true))));
    }

    private function assertProviderConfigured(string $provider): void
    {
        $configured = match ($provider) {
            'demo', 'demo_alpha', 'demo_beta' => true,
            'chaturbate' => trim((string) $this->baseConfig->get('chaturbate.wm', '')) !== '',
            'bongacams' => (int) $this->baseConfig->get('bongacams.campaign_id', 0) > 0,
            'cam4' => (int) $this->baseConfig->get('cam4.affiliate_id', 0) > 0,
            'livejasmin' => trim((string) $this->baseConfig->get('livejasmin.ps_id', '')) !== ''
                && trim((string) $this->baseConfig->get('livejasmin.access_key', '')) !== '',
            'stripchat' => trim((string) $this->baseConfig->get('stripchat.api_key', '')) !== ''
                && trim((string) $this->baseConfig->get('stripchat.user_id', '')) !== '',
            'crakrevenue_mfc',
            'crakrevenue_streamate',
            'crakrevenue_chaturbate',
            'crakrevenue_awempire',
            'crakrevenue_stripchat',
            'crakrevenue_imlive',
            'crakrevenue_bongacash' => trim((string) $this->baseConfig->get('crakrevenue.api_key', '')) !== ''
                && trim((string) $this->baseConfig->get('crakrevenue.token', '')) !== '',
            default => false,
        };
        $crakRevenueBrand = CrakRevenueAdapter::brandForProvider($provider);
        if ($configured && $crakRevenueBrand !== null) {
            $this->crakRevenueAuthorization ??= new CrakRevenueAuthorization($this->settings, $this->baseConfig);
            if ($this->crakRevenueAuthorization->statusForBrand($crakRevenueBrand)
                === CrakRevenueAuthorization::NOT_AUTHORIZED
            ) {
                throw new RuntimeException("The CrakRevenue token is not authorized for {$crakRevenueBrand}.");
            }
        }
        if (!$configured) {
            throw new RuntimeException("Configure the {$provider} credentials in Integrations first.");
        }
    }

    /** @param list<string> $enabled */
    private function assertAffiliateRoutesAreExclusive(array $enabled): void
    {
        foreach ([
            ['chaturbate', 'crakrevenue_chaturbate'],
            ['livejasmin', 'crakrevenue_awempire'],
            ['stripchat', 'crakrevenue_stripchat'],
            ['bongacams', 'crakrevenue_bongacash'],
        ] as $conflict) {
            if (count(array_intersect($conflict, $enabled)) > 1) {
                throw new RuntimeException('Choose only one affiliate source for each network.');
            }
        }
    }

    private function liveJasminCategoryList(mixed $value): array
    {
        $items = is_array($value)
            ? $value
            : (preg_split('/[\s,]+/', strtolower(trim((string) $value)), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $aliases = [
            'boy' => 'gay',
            'guy' => 'gay',
            'trans' => 'transgender',
            'transgendered' => 'transgender',
        ];
        $normalized = [];
        foreach ($items as $item) {
            $category = strtolower(trim((string) $item));
            $category = $aliases[$category] ?? $category;
            if (in_array($category, self::LIVEJASMIN_CATEGORIES, true)) {
                $normalized[$category] = true;
            }
        }
        return array_values(array_filter(
            self::LIVEJASMIN_CATEGORIES,
            static fn (string $category): bool => isset($normalized[$category])
        ));
    }

    private function performerTypeList(mixed $value): array
    {
        $items = is_array($value) ? $value : [];
        $selected = [];
        foreach ($items as $item) {
            $type = strtolower(trim((string) $item));
            if (in_array($type, PerformerTypes::VALUES, true)) {
                $selected[$type] = true;
            }
        }

        return array_values(array_filter(
            PerformerTypes::VALUES,
            static fn (string $type): bool => isset($selected[$type])
        ));
    }

    private function liveJasminCategoriesForTypes(array $categories, array $types): array
    {
        $mapping = [
            'girl' => ['f'],
            'gay' => ['m'],
            'transgender' => ['t'],
            'lesbian' => ['f', 'c'],
            'couple' => ['c'],
        ];

        return array_values(array_filter($categories, static function (string $category) use ($types, $mapping): bool {
            return array_intersect($mapping[$category] ?? [], $types) !== [];
        }));
    }

    private function isHttpsUrl(string $url): bool
    {
        $parts = parse_url($url);
        return is_array($parts)
            && strtolower((string) ($parts['scheme'] ?? '')) === 'https'
            && trim((string) ($parts['host'] ?? '')) !== ''
            && !isset($parts['user'], $parts['pass']);
    }

    private function text(mixed $value, int $maximum): string
    {
        $value = trim((string) $value);
        return function_exists('mb_substr')
            ? trim(mb_substr($value, 0, $maximum, 'UTF-8'))
            : trim(substr($value, 0, $maximum));
    }
}
