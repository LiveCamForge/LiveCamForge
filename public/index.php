<?php

declare(strict_types=1);

use LiveCamForge\Core\SecurityHeaders;
use LiveCamForge\Core\CatalogReturn;
use LiveCamForge\Core\AppearanceSettings;
use LiveCamForge\Core\CatalogSettings;
use LiveCamForge\Core\OperationalSettings;
use LiveCamForge\Core\Translator;
use LiveCamForge\Core\SiteUrl;
use LiveCamForge\Core\SafeMarkdown;
use LiveCamForge\Core\VisitorGeo;
use LiveCamForge\Database\Connection;
use LiveCamForge\Database\Migrator;
use LiveCamForge\Providers\ProviderFactory;
use LiveCamForge\Providers\ProviderPlayer;
use LiveCamForge\Repositories\ClickRepository;
use LiveCamForge\Providers\AffiliateTrackingProviderInterface;
use LiveCamForge\Repositories\PerformerRepository;
use LiveCamForge\Repositories\SettingsRepository;
use LiveCamForge\Repositories\TrafficLandingRepository;
use LiveCamForge\Services\BrandAssetStorage;
use LiveCamForge\Services\MediaProxy;
use LiveCamForge\Services\CatalogCountCache;
use LiveCamForge\Core\ProviderPolicy;
use LiveCamForge\Core\PerformerTypes;
use LiveCamForge\Core\PerformerCountry;
use LiveCamForge\Core\PerformanceProfiler;
use LiveCamForge\Core\TagCursor;
use LiveCamForge\Core\DemoMode;

$root = dirname(__DIR__);
if (!is_file($root . '/config/local.php')) {
    header('Location: ../install/');
    exit;
}

$baseConfig = require $root . '/app/bootstrap.php';
SecurityHeaders::sendBase();
$pdo = Connection::make($baseConfig);
Migrator::run($pdo, $root . '/database/migrations');
$settingsRepository = new SettingsRepository($pdo);
$languageDiscovery = new Translator($root . '/languages', 'en', 'en');
$operationalSettings = new OperationalSettings(
    $settingsRepository,
    $baseConfig,
    ProviderFactory::availableNames(),
    array_keys($languageDiscovery->available())
);
$config = $operationalSettings->effectiveConfig();
$demoMode = DemoMode::enabled($config);
if ($demoMode) {
    header('X-Robots-Tag: noindex, nofollow');
}
$performanceRequested = (string) ($_GET['perf'] ?? '') === '1';
$remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
$performanceAllowed = (bool) $config->get('debug', false)
    || in_array($remoteAddress, ['127.0.0.1', '::1'], true);
PerformanceProfiler::enable($performanceRequested && $performanceAllowed);
if (PerformanceProfiler::enabled()) {
    $requestStartedAt = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    PerformanceProfiler::add('bootstrap.setup', microtime(true) - $requestStartedAt);
    PerformanceProfiler::meta('cache_revision', is_file($root . '/storage/cache/catalog-counts/revision') ? 'present' : 'initial');
    PerformanceProfiler::meta('request_uri', substr((string) ($_SERVER['REQUEST_URI'] ?? ''), 0, 500));
    PerformanceProfiler::meta('remote', $remoteAddress === '' ? 'unknown' : $remoteAddress);
}
$translator = new Translator(
    $root . '/languages',
    (string) $config->get('locale', 'en'),
    (string) $config->get('fallback_locale', 'en')
);
$performerTypes = PerformerTypes::fromConfig($config);
$repository = new PerformerRepository($pdo, $performerTypes);
$landingRepository = new TrafficLandingRepository($pdo, $config, array_keys($translator->available()));
$clickRepository = new ClickRepository($pdo);
$appearanceSettings = new AppearanceSettings($settingsRepository, $config, $translator);
$siteAppearance = $appearanceSettings->values();
$siteUrl = new SiteUrl($config, $_SERVER);
$assetUrl = static fn (string $path): string => $siteUrl->asset($path);
$brandingStorage = new BrandAssetStorage($root . '/storage/branding');
$brandingAssetBase = $siteUrl->path('public/?branding=');
$enabledProviders = ProviderFactory::enabledNames($config);
$providerCapabilitiesByName = [];
$providerLabels = [];
foreach ($enabledProviders as $enabledProvider) {
    $enabledAdapter = ProviderFactory::make($enabledProvider, $config, $root);
    $providerCapabilitiesByName[$enabledProvider] = $enabledAdapter->capabilities();
    $providerLabels[$enabledProvider] = ProviderFactory::publicDisplayName($enabledProvider, $config, $root);
}
$catalogSettings = new CatalogSettings($settingsRepository, $config, $enabledProviders);
$catalog = $catalogSettings->values();
$primaryProvider = $catalog['primary_provider'];
$showProviderBadges = $catalog['mode'] === 'combined' && $catalog['show_provider_badges'];
$requestedRouteProvider = strtolower(trim((string) ($_GET['provider'] ?? '')));
$routeProvider = $requestedRouteProvider === ''
    ? $primaryProvider
    : (in_array($requestedRouteProvider, $enabledProviders, true) ? $requestedRouteProvider : null);
$blockNonPublicRooms = (bool) $config->get('rooms.block_non_public', true);
$visitorGeo = VisitorGeo::detect($config, $_SERVER);
$visitorGeoCodes = $visitorGeo->complete() ? $visitorGeo->restrictionCodes() : [];
$hideRestrictedWhenGeoUnknown = !$visitorGeo->complete();

header('Cache-Control: private, no-store');
header('Vary: CF-IPCountry, CF-Region-Code, Accept-Language', false);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

$landings = array_filter(
    $landingRepository->enabled($translator),
    static function (array $landing) use ($performerTypes): bool {
        $landingGender = trim((string) ($landing['filters']['gender'] ?? ''));
        return $landingGender === '' || in_array($landingGender, $performerTypes, true);
    }
);
$navigationLandings = array_filter(
    $landings,
    static fn (array $landing): bool => (bool) ($landing['show_in_navigation'] ?? true)
);
$recruitmentConfig = $config->get('recruitment.models', []);
$recruitmentEnabled = false;
if (is_array($recruitmentConfig) && ($recruitmentConfig['enabled'] ?? false)) {
    foreach (is_array($recruitmentConfig['providers'] ?? null) ? $recruitmentConfig['providers'] : [] as $recruitmentEntry) {
        $recruitmentUrl = is_array($recruitmentEntry) ? trim((string) ($recruitmentEntry['url'] ?? '')) : '';
        if (is_array($recruitmentEntry) && ($recruitmentEntry['enabled'] ?? false)
            && filter_var($recruitmentUrl, FILTER_VALIDATE_URL) && str_starts_with(strtolower($recruitmentUrl), 'https://')) {
            $recruitmentEnabled = true;
            break;
        }
    }
}
$webmasterRecruitmentConfig = $config->get('recruitment.webmasters', []);
$webmasterRecruitmentEnabled = is_array($webmasterRecruitmentConfig)
    && (bool) ($webmasterRecruitmentConfig['enabled'] ?? false);
$landingSlug = strtolower(trim((string) ($_GET['landing'] ?? '')));
$activeLanding = $landingSlug !== '' ? ($landings[$landingSlug] ?? null) : null;
if ($landingSlug !== '' && $activeLanding === null) {
    http_response_code(404);
    $catalogBackUrl = $siteUrl->path();
    require $root . '/templates/model-not-found.php';
    exit;
}
$sourcePage = $activeLanding['slug']
    ?? (substr(preg_replace('/[^a-z0-9_.-]/i', '', (string) ($_GET['source'] ?? 'catalog')) ?: 'catalog', 0, 80));
$catalogReturnQuery = CatalogReturn::query($_GET);
$catalogBackBaseUrl = isset($landings[$sourcePage])
    ? $siteUrl->landing($sourcePage)
    : $siteUrl->path();
$catalogBackUrl = CatalogReturn::url($catalogBackBaseUrl, $catalogReturnQuery);
$profileUrl = static function (string $provider, string $username) use ($catalogReturnQuery, $sourcePage, $siteUrl): string {
    $query = array_filter(['return' => $catalogReturnQuery, 'source' => $sourcePage !== 'catalog' ? $sourcePage : '']);
    return $siteUrl->model($provider, $username) . ($query ? '?' . http_build_query($query) : '');
};
$goUrl = static function (string $provider, string $username) use ($catalogReturnQuery, $sourcePage, $siteUrl): string {
    $query = ['provider' => $provider, 'go' => $username, 'return' => $catalogReturnQuery, 'source' => $sourcePage];
    return $siteUrl->path('public/') . '?' . http_build_query($query);
};
$mediaUrl = static fn (string $provider, string $username): string => $siteUrl->path('public/') . '?' . http_build_query([
    'provider' => $provider,
    'media' => 'preview',
    'model' => $username,
]);
$tagUrl = static function (string $tag) use ($activeLanding, $siteUrl): string {
    $safeTag = strtolower(ltrim(trim($tag), '#'));
    parse_str(CatalogReturn::query($_GET), $query);
    if (!is_array($query)) {
        $query = [];
    }
    unset($query['page'], $query['tag_cursor'], $query['tag_dir']);
    $query['tag'] = $safeTag;
    $baseUrl = $activeLanding !== null
        ? $siteUrl->landing((string) $activeLanding['slug'])
        : $siteUrl->path();

    return $baseUrl . '?' . http_build_query($query);
};

if (($_GET['route'] ?? '') === 'robots') {
    if ($demoMode) {
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: public, max-age=300');
        echo "User-agent: *\nDisallow: /\n";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo "User-agent: *\nAllow: " . $siteUrl->path() . "\nDisallow: " . $siteUrl->path('admin/')
        . "\nDisallow: " . $siteUrl->path('install/') . "\nDisallow: " . $siteUrl->path('postback.php')
        . "\nDisallow: " . $siteUrl->path('recruitment-go.php')
        . "\nDisallow: " . $siteUrl->path('public/') . "?widget=\nSitemap: "
        . $siteUrl->absolute('sitemap.xml') . "\n";
    exit;
}

if (($_GET['route'] ?? '') === 'sitemap') {
    if ($demoMode) {
        http_response_code(404);
        header('X-Robots-Tag: noindex, nofollow');
        exit;
    }
    header('Content-Type: application/xml; charset=utf-8');
    header('Cache-Control: public, max-age=900');
    $sitemapLandings = [];
    foreach ($landings as $candidateLanding) {
        if (!$candidateLanding['index']) {
            continue;
        }
        $landingFilters = array_replace([
            'provider' => $catalog['mode'] === 'single' ? $primaryProvider : '',
            'providers' => $catalog['mode'] === 'combined' ? $enabledProviders : [$primaryProvider],
            'hide_restricted_when_geo_unknown' => true,
        ], $candidateLanding['filters']);
        if ($repository->countOnline($landingFilters) >= $candidateLanding['minimum_results']) {
            $sitemapLandings[] = $candidateLanding;
        }
    }
    $sitemapModelProviders = array_values(array_filter(
        $enabledProviders,
        static function (string $providerName) use ($config): bool {
            $policy = ProviderPolicy::for($config, $providerName);
            return $policy->indexPerformerPages && $policy->includePerformersInSitemap;
        }
    ));
    $sitemapModels = $repository->sitemap(
        $sitemapModelProviders,
        (int) $config->get('seo.sitemap_max_models', 10000)
    );
    require $root . '/templates/sitemap.php';
    exit;
}

if (($_GET['route'] ?? '') === 'recruitment') {
    $recruitment = $recruitmentConfig;
    if (!$recruitmentEnabled) {
        http_response_code(404);
        require $root . '/templates/model-not-found.php';
        exit;
    }
    require $root . '/templates/recruitment.php';
    exit;
}

if (($_GET['route'] ?? '') === 'webmaster-recruitment') {
    $webmasterRecruitment = $webmasterRecruitmentConfig;
    if (!$webmasterRecruitmentEnabled) {
        http_response_code(404);
        require $root . '/templates/model-not-found.php';
        exit;
    }
    require $root . '/templates/webmaster-recruitment.php';
    exit;
}

if (($_GET['route'] ?? '') === 'recruitment-go') {
    $recruitProvider = strtolower(trim((string) ($_GET['recruit_provider'] ?? '')));
    header('Location: ' . $siteUrl->path('recruitment-go.php') . '?' . http_build_query([
        'recruit_provider' => $recruitProvider,
    ]), true, 302);
    exit;
}

if (isset($_GET['widget'])) {
    $username = trim((string) ($_GET['model'] ?? ''));
    $performer = $routeProvider !== null && $username !== ''
        ? $repository->findByUsername(
            $routeProvider,
            $username,
            $visitorGeoCodes,
            $hideRestrictedWhenGeoUnknown
        )
        : null;
    $provider = $performer ? ProviderFactory::make((string) $performer['provider'], $config, $root) : null;
    $widgetCapabilities = $provider?->capabilities();
    $playerOptions = [
        'offline_fallback' => $catalog['offline_fallbacks'][$routeProvider] ?? 'profile',
        'click_through_url' => $siteUrl->absolute('public/?' . http_build_query([
            'go' => $performer['username'] ?? '',
            'provider' => $performer['provider'] ?? '',
            'source' => $sourcePage,
        ])),
    ];
    $widgetEligible = $performer
        && (int) $performer['is_online'] === 1
        && (bool) $config->get('player.enabled', true)
        && (!$widgetCapabilities?->roomStatus
            || !$blockNonPublicRooms
            || ($performer['room_status'] ?? 'unknown') === 'public');
    if ($widgetEligible
        && $provider instanceof AffiliateTrackingProviderInterface
        && (bool) $config->get($performer['provider'] . '.postback.enabled', false)
    ) {
        $track = (string) $config->get($performer['provider'] . '.postback.track', 'livecamforge');
        $touchpoint = $clickRepository->record($performer, $track, 'widget', $sourcePage);
        $playerOptions['sub_aff_id'] = $touchpoint['sid'];
    }
    $player = $provider?->player($performer, $playerOptions);
    $resolvedPlayer = $player ? $provider?->resolvePlayer($player) : null;

    if (!$performer
        || (int) $performer['is_online'] !== 1
        || !(bool) $config->get('player.enabled', true)
        || ($widgetCapabilities?->roomStatus
            && $blockNonPublicRooms
            && ($performer['room_status'] ?? 'unknown') !== 'public')
        || !$player
        || $player->mode !== ProviderPlayer::MODE_SCRIPT
        || !$provider?->isEmbedUrlAllowed($player->url)
        || !$resolvedPlayer
        || !in_array($resolvedPlayer->mode, [ProviderPlayer::MODE_WRAPPED_IFRAME, ProviderPlayer::MODE_SCRIPT], true)
        || !$provider?->isEmbedUrlAllowed($resolvedPlayer->url)
    ) {
        http_response_code(404);
        header('Cache-Control: no-store');
        exit;
    }

    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');
    header("Content-Security-Policy: frame-ancestors 'self'");
    require $root . '/templates/provider-widget.php';
    exit;
}

if (isset($_GET['branding'])) {
    $kind = (string) $_GET['branding'];
    $filename = $kind === 'logo'
        ? $siteAppearance['logo_file']
        : ($kind === 'favicon' ? $siteAppearance['favicon_file'] : null);
    if ($brandingStorage->serve($filename)) {
        exit;
    }
    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

if (isset($_GET['media'])) {
    $provider = $routeProvider !== null ? ProviderFactory::make($routeProvider, $config, $root) : null;
    if ($routeProvider !== null && DemoMode::isDemoProvider($routeProvider)) {
        $username = trim((string) ($_GET['model'] ?? ''));
        $performer = $username !== '' ? $repository->findByUsername(
            $routeProvider,
            $username,
            $visitorGeoCodes,
            $hideRestrictedWhenGeoUnknown
        ) : null;
        $source = is_array($performer) ? (string) ($performer['preview_url'] ?: $performer['image_url']) : '';
        $prefix = 'demo://' . $routeProvider . '/';
        if ($source !== '' && str_starts_with($source, $prefix)) {
            $asset = basename(substr($source, strlen($prefix)));
            $assetPath = $root . '/public/assets/demo/' . $routeProvider . '/' . $asset;
            if (preg_match('/^[0-9]{2}\.svg$/', $asset) === 1 && is_file($assetPath)) {
                header('Content-Type: image/svg+xml; charset=UTF-8');
                header('Cache-Control: public, max-age=86400');
                readfile($assetPath);
                exit;
            }
        }
    }
    $providerCapabilities = $provider?->capabilities();
    $username = trim((string) ($_GET['model'] ?? ''));
    $performer = $routeProvider !== null && $_GET['media'] === 'preview' && $username !== ''
        ? $repository->findByUsername(
            $routeProvider,
            $username,
            $visitorGeoCodes,
            $hideRestrictedWhenGeoUnknown
        )
        : null;
    $sourceUrls = is_array($performer)
        ? array_values(array_unique(array_filter([
            trim((string) $performer['preview_url']),
            trim((string) $performer['image_url']),
        ])))
        : [];

    if ($providerCapabilities?->mediaProxy && (bool) $config->get('media_proxy.enabled', true)) {
        foreach ($sourceUrls as $sourceUrl) {
            if ($provider?->isMediaUrlAllowed($sourceUrl)
                && MediaProxy::serve(
                    $sourceUrl,
                    $root . '/storage/cache/media',
                    (int) $config->get('media_proxy.ttl_seconds', 120),
                    (int) $config->get('media_proxy.timeout_seconds', 8),
                    ProviderPolicy::for($config, $routeProvider)->cacheImages
                )
            ) {
                exit;
            }
        }
    }

    http_response_code(404);
    header('Cache-Control: no-store');
    exit;
}

if (isset($_GET['go'])) {
    $username = trim((string) $_GET['go']);
    $performer = $routeProvider !== null
        ? $repository->findByUsername(
            $routeProvider,
            $username,
            $visitorGeoCodes,
            $hideRestrictedWhenGeoUnknown
        )
        : null;
    $roomUrl = is_array($performer) ? (string) $performer['room_url'] : '';
    $provider = $performer ? ProviderFactory::make((string) $performer['provider'], $config, $root) : null;
    $providerCapabilities = $provider?->capabilities();

    if ($performer
        && (int) $performer['is_online'] === 1
        && (!$providerCapabilities?->roomStatus
            || !$blockNonPublicRooms
            || ($performer['room_status'] ?? 'unknown') === 'public')
        && $provider?->isRoomUrlAllowed($roomUrl)
    ) {
        $trackingProvider = (string) $performer['provider'];
        $track = (string) $config->get($trackingProvider . '.postback.track', 'livecamforge');
        $click = $clickRepository->record($performer, $track, 'click', $sourcePage);
        if ($provider instanceof AffiliateTrackingProviderInterface) {
            $trackedRoomUrl = $provider->trackedRoomUrl($roomUrl, $click['sid'], $click['track']);
            if ($provider->isRoomUrlAllowed($trackedRoomUrl)) {
                $roomUrl = $trackedRoomUrl;
            }
        }
        header('Location: ' . $roomUrl, true, 302);
        exit;
    }

    header('Location: ' . ($performer ? $profileUrl($performer['provider'], $performer['username']) : $catalogBackUrl));
    exit;
}

if (isset($_GET['model'])) {
    $username = trim((string) $_GET['model']);
    $performer = $routeProvider !== null
        ? $repository->findByUsername(
            $routeProvider,
            $username,
            $visitorGeoCodes,
            $hideRestrictedWhenGeoUnknown
        )
        : null;
    if (!$performer) {
        http_response_code(404);
        require $root . '/templates/model-not-found.php';
        exit;
    }

    $provider = ProviderFactory::make((string) $performer['provider'], $config, $root);
    $publicProviderName = $providerLabels[(string) $performer['provider']]
        ?? ProviderFactory::publicDisplayName((string) $performer['provider'], $config, $root);
    $performerPolicy = ProviderPolicy::for($config, (string) $performer['provider']);
    $providerCapabilities = $provider->capabilities();
    $player = $provider->player($performer, [
        'offline_fallback' => $catalog['offline_fallbacks'][$performer['provider']] ?? 'profile',
    ]);
    $playerFrameUrl = null;
    $playerFallbackUrl = null;
    if ($player?->mode === ProviderPlayer::MODE_SCRIPT) {
        $playerFrameUrl = '?' . http_build_query([
            'widget' => '1',
            'provider' => $performer['provider'],
            'model' => $performer['username'],
            'source' => $sourcePage,
        ]);
    } elseif ($player?->mode === ProviderPlayer::MODE_IFRAME) {
        $playerFrameUrl = $player->url;
    } elseif ($player?->mode === ProviderPlayer::MODE_HLS && $provider->isEmbedUrlAllowed($player->url)) {
        $playerFrameUrl = $player->url;
    }
    if ($player?->fallbackMode === ProviderPlayer::MODE_HLS
        && is_string($player->fallbackUrl)
        && $provider->isEmbedUrlAllowed($player->fallbackUrl)
    ) {
        $playerFallbackUrl = $player->fallbackUrl;
    }
    $similarProviders = $catalog['mode'] === 'combined' ? $enabledProviders : [$primaryProvider];
    $providersWithoutRoomStatus = array_keys(array_filter(
        $providerCapabilitiesByName,
        static fn ($capabilities): bool => !$capabilities->roomStatus
    ));
    $similarPerformers = $repository->similar(
        $performer,
        8,
        !$blockNonPublicRooms,
        $similarProviders,
        $providersWithoutRoomStatus,
        $visitorGeoCodes,
        $hideRestrictedWhenGeoUnknown
    );
    require $root . '/templates/model.php';
    exit;
}

$notice = isset($_GET['installed']) ? $translator->get('home.installed') : null;

$requestedTag = strtolower(ltrim(trim((string) ($_GET['tag'] ?? '')), '#'));
if (preg_match('~^[a-z0-9_/-]{1,80}$~', $requestedTag) !== 1) {
    $requestedTag = '';
}
$ageOptions = ['18-20', '21-25', '26-30', '31-35', '36-40', '41-plus'];
$roomStatusOptions = ['public', 'private', 'group', 'away', 'unknown'];
$sortOptions = ['popular', 'provider_popular', 'newest', 'youngest', 'oldest', 'name'];
$performerTypeTranslationKeys = ['f' => 'women', 'm' => 'men', 't' => 'trans', 'c' => 'couples'];
$showGenderFilter = count($performerTypes) > 1;
$showProviderFilter = $catalog['mode'] === 'combined' && $catalog['show_provider_filter'];
$newStrategies = [];
foreach ($enabledProviders as $enabledProvider) {
    $newStrategies[$enabledProvider] = LiveCamForge\Core\NewnessStrategy::for($config, $enabledProvider);
}
$requestedCatalogProvider = strtolower(trim((string) ($_GET['provider'] ?? '')));
$catalogFilterProvider = $showProviderFilter && in_array($requestedCatalogProvider, $enabledProviders, true)
    ? $requestedCatalogProvider
    : '';
$requestedGender = strtolower(trim((string) ($_GET['gender'] ?? '')));
$requestedCountry = PerformerCountry::normalize($_GET['country'] ?? null);
$filters = [
    'provider' => $catalog['mode'] === 'single' ? $primaryProvider : $catalogFilterProvider,
    'providers' => $catalog['mode'] === 'combined' ? $enabledProviders : [$primaryProvider],
    // Keep a recognized but disabled type in the query: the repository's
    // global scope then returns zero results instead of silently serving a
    // different catalog under the excluded URL. Such filtered URLs are noindex.
    'gender' => in_array($requestedGender, PerformerTypes::VALUES, true) ? $requestedGender : '',
    'country' => $requestedCountry ?? '',
    'q' => trim((string) ($_GET['q'] ?? '')),
    'tag' => $requestedTag,
    'age' => in_array($_GET['age'] ?? '', $ageOptions, true) ? $_GET['age'] : '',
    'room_status' => in_array($_GET['room_status'] ?? '', $roomStatusOptions, true) ? $_GET['room_status'] : '',
    'providers_without_room_status' => array_keys(array_filter(
        $providerCapabilitiesByName,
        static fn ($capabilities): bool => !$capabilities->roomStatus
    )),
    'new_only' => ($_GET['new'] ?? '') === '1',
    'new_days' => max(1, min(90, (int) $config->get('catalog.new_days', 7))),
    'new_strategies' => $newStrategies,
    'sort' => in_array($_GET['sort'] ?? '', $sortOptions, true) ? $_GET['sort'] : 'popular',
    'geo_codes' => $visitorGeoCodes,
    'hide_restricted_when_geo_unknown' => $hideRestrictedWhenGeoUnknown,
];
if ($activeLanding !== null) {
    $landingQueryKeys = [
        'provider' => 'provider', 'gender' => 'gender', 'country' => 'country', 'tag' => 'tag', 'age' => 'age',
        'room_status' => 'room_status', 'new_only' => 'new', 'sort' => 'sort',
    ];
    foreach ($activeLanding['filters'] as $key => $value) {
        $queryKey = $landingQueryKeys[$key] ?? null;
        if ($queryKey !== null && array_key_exists($queryKey, $_GET) && trim((string) $_GET[$queryKey]) !== '') {
            continue;
        }
        $filters[$key] = $value;
    }
}
$countryScopeFilters = [
    'provider' => $catalog['mode'] === 'single' ? $primaryProvider : '',
    'providers' => $catalog['mode'] === 'combined' ? $enabledProviders : [$primaryProvider],
    'geo_codes' => $visitorGeoCodes,
    'hide_restricted_when_geo_unknown' => $hideRestrictedWhenGeoUnknown,
];
$countryCacheContext = ['filters' => $countryScopeFilters, 'performer_types' => $performerTypes];
PerformanceProfiler::start('countries.cache_get');
$cachedCountryCounts = CatalogCountCache::get($root, 'countries', $countryCacheContext);
PerformanceProfiler::stop('countries.cache_get');
$countryCacheHit = is_array($cachedCountryCounts);
PerformanceProfiler::meta('countries_cache', $countryCacheHit ? 'HIT' : 'MISS');
$availableCountryCounts = $countryCacheHit
    ? $cachedCountryCounts
    : $repository->availableCountries($countryScopeFilters);
if (!$countryCacheHit) {
    PerformanceProfiler::start('countries.cache_put');
    CatalogCountCache::put($root, 'countries', $countryCacheContext, $availableCountryCounts);
    PerformanceProfiler::stop('countries.cache_put');
}
$countryOptions = [];
foreach (array_keys($availableCountryCounts) as $countryCode) {
    $countryOptions[$countryCode] = PerformerCountry::label($countryCode, $translator->locale());
}
if ($filters['country'] !== '' && !isset($countryOptions[$filters['country']])) {
    $countryOptions[$filters['country']] = PerformerCountry::label($filters['country'], $translator->locale());
}
asort($countryOptions, SORT_NATURAL | SORT_FLAG_CASE);
$showCountryFilter = count($countryOptions) >= 2 || $filters['country'] !== '';
$perPageOptions = [24, 48, 96];
$requestedPerPage = (int) ($_GET['per_page'] ?? 24);
$perPage = in_array($requestedPerPage, $perPageOptions, true) ? $requestedPerPage : 24;
$deferredTagPagination = $activeLanding === null
    && $filters['tag'] !== ''
    && $filters['q'] === '';
$tagCursorPagination = $deferredTagPagination
    && in_array((string) ($filters['sort'] ?? 'popular'), ['popular', 'provider_popular'], true);
$currentPage = max(1, (int) ($_GET['page'] ?? 1));
$offset = ($currentPage - 1) * $perPage;
$hasNextPage = false;
$tagCursorValue = $tagCursorPagination ? trim((string) ($_GET['tag_cursor'] ?? '')) : '';
$tagCursor = $tagCursorValue !== '' ? TagCursor::decode($tagCursorValue) : null;
$tagDirection = $tagCursorPagination && (string) ($_GET['tag_dir'] ?? '') === 'prev' ? 'prev' : 'next';
if ($tagCursorPagination && $currentPage > 1 && $tagCursor === null) {
    // Cursor pages are sequential by design. A page number without its cursor cannot
    // safely reproduce the requested deep page, so fall back to the first page.
    $currentPage = 1;
    $offset = 0;
    $tagDirection = 'next';
}
$tagPreviousCursor = '';
$tagNextCursor = '';

if ($deferredTagPagination) {
    // Exact COUNT(*) on tags_json is the dominant cold-path cost on large catalogs.
    // Tag-filtered discovery pages only need to know whether another page exists, so
    // fetch one extra ID/row and use Previous/Next navigation instead of counting the
    // complete result set. Standard catalogs and managed landings keep exact counts.
    PerformanceProfiler::meta('count_cache', 'SKIP');
    PerformanceProfiler::meta('count_strategy', $tagCursorPagination ? 'tag_cursor_window' : 'deferred_tag_window');
    $catalogPageCacheContext = [
        'filters' => $filters,
        'performer_types' => $performerTypes,
        'visitor_geo' => $visitorGeoCodes,
        'hide_restricted' => $hideRestrictedWhenGeoUnknown,
        'per_page' => $perPage,
        'page' => $currentPage,
        'window' => true,
        'cursor' => $tagCursorPagination ? $tagCursorValue : '',
        'direction' => $tagCursorPagination ? $tagDirection : '',
    ];
    PerformanceProfiler::start('page.cache_get');
    $cachedCatalogWindow = CatalogCountCache::get($root, 'page_tag_window', $catalogPageCacheContext);
    PerformanceProfiler::stop('page.cache_get');
    $pageCacheHit = is_array($cachedCatalogWindow)
        && isset($cachedCatalogWindow['performers'], $cachedCatalogWindow['has_next'])
        && is_array($cachedCatalogWindow['performers']);
    PerformanceProfiler::meta('page_cache', $pageCacheHit ? 'HIT' : 'MISS');
    if ($pageCacheHit) {
        $performers = $cachedCatalogWindow['performers'];
        $hasNextPage = (bool) $cachedCatalogWindow['has_next'];
    } else {
        if ($tagCursorPagination) {
            if ($tagDirection === 'prev') {
                $window = $repository->onlineByPopularityCursor($filters, $perPage, $tagCursor, 'prev');
                $performers = $window;
                $hasNextPage = $currentPage > 1 || $performers !== [];
            } else {
                $window = $repository->onlineByPopularityCursor($filters, $perPage + 1, $tagCursor, 'next');
                $hasNextPage = count($window) > $perPage;
                $performers = array_slice($window, 0, $perPage);
            }
        } else {
            $window = $repository->online($filters, $perPage + 1, $offset);
            $hasNextPage = count($window) > $perPage;
            $performers = array_slice($window, 0, $perPage);
        }
        PerformanceProfiler::start('page.cache_put');
        CatalogCountCache::put($root, 'page_tag_window', $catalogPageCacheContext, [
            'performers' => $performers,
            'has_next' => $hasNextPage,
        ]);
        PerformanceProfiler::stop('page.cache_put');
    }
    $totalPerformers = null;
    $totalPages = null;
    if ($tagCursorPagination && $performers !== []) {
        $tagPreviousCursor = TagCursor::encode($performers[0], (string) $filters['sort']);
        $tagNextCursor = TagCursor::encode($performers[count($performers) - 1], (string) $filters['sort']);
    }
    $rangeFrom = $performers === [] ? 0 : $offset + 1;
    $rangeTo = $offset + count($performers);
    PerformanceProfiler::meta('result_count', 'deferred');
} else {
    $catalogCountCacheContext = [
        'filters' => $filters,
        'performer_types' => $performerTypes,
        'visitor_geo' => $visitorGeoCodes,
        'hide_restricted' => $hideRestrictedWhenGeoUnknown,
    ];
    PerformanceProfiler::start('count.cache_get');
    $catalogCountCache = CatalogCountCache::get($root, 'catalog', $catalogCountCacheContext);
    PerformanceProfiler::stop('count.cache_get');
    $cachedCatalogCount = is_int($catalogCountCache) ? $catalogCountCache : null;
    PerformanceProfiler::meta('count_cache', $cachedCatalogCount !== null ? 'HIT' : 'MISS');
    if ($cachedCatalogCount !== null) {
        $totalPerformers = $cachedCatalogCount;
    } else {
        $totalPerformers = $repository->countOnline($filters);
        PerformanceProfiler::start('count.cache_put');
        CatalogCountCache::put($root, 'catalog', $catalogCountCacheContext, $totalPerformers);
        PerformanceProfiler::stop('count.cache_put');
    }
    $totalPages = max(1, (int) ceil($totalPerformers / $perPage));
    $currentPage = max(1, min($totalPages, $currentPage));
    $offset = ($currentPage - 1) * $perPage;
    $catalogPageCacheContext = [
        'filters' => $filters,
        'performer_types' => $performerTypes,
        'visitor_geo' => $visitorGeoCodes,
        'hide_restricted' => $hideRestrictedWhenGeoUnknown,
        'per_page' => $perPage,
        'page' => $currentPage,
    ];
    PerformanceProfiler::start('page.cache_get');
    $cachedCatalogPage = CatalogCountCache::get($root, 'page', $catalogPageCacheContext);
    PerformanceProfiler::stop('page.cache_get');
    $pageCacheHit = is_array($cachedCatalogPage);
    PerformanceProfiler::meta('page_cache', $pageCacheHit ? 'HIT' : 'MISS');
    if ($pageCacheHit) {
        $performers = $cachedCatalogPage;
    } else {
        $performers = $repository->online($filters, $perPage, $offset);
        PerformanceProfiler::start('page.cache_put');
        CatalogCountCache::put($root, 'page', $catalogPageCacheContext, $performers);
        PerformanceProfiler::stop('page.cache_put');
    }
    PerformanceProfiler::meta('result_count', $totalPerformers);
    $rangeFrom = $totalPerformers === 0 ? 0 : $offset + 1;
    $rangeTo = min($offset + count($performers), $totalPerformers);
}
PerformanceProfiler::meta('page', $currentPage);
PerformanceProfiler::meta('per_page', $perPage);
$catalogLabel = $catalog['mode'] === 'combined'
    ? $translator->get('common.all_providers')
    : ($providerLabels[$primaryProvider] ?? ucfirst($primaryProvider));
$landingVariables = [
    'site_name' => $siteAppearance['site_name'],
    'result_count' => $totalPerformers,
    'provider_name' => $catalogLabel,
    'landing_title' => $activeLanding['heading'] ?? ($activeLanding['title'] ?? ''),
];
$landingIntro = $activeLanding !== null
    ? SafeMarkdown::interpolate((string) $activeLanding['intro'], $landingVariables)
    : '';
$landingBodyHtml = $activeLanding !== null
    ? SafeMarkdown::render((string) ($activeLanding['body'] ?? ''), $landingVariables)
    : '';
$landingFaq = [];
foreach ($activeLanding['faq'] ?? [] as $faqItem) {
    $landingFaq[] = [
        'question' => SafeMarkdown::interpolate((string) $faqItem['question'], $landingVariables),
        'answer' => SafeMarkdown::interpolate((string) $faqItem['answer'], $landingVariables),
    ];
}

$pageNumbers = [];
if (!$deferredTagPagination) {
    $pageNumbers = array_values(array_unique(array_filter([
        1, 2, $currentPage - 2, $currentPage - 1, $currentPage,
        $currentPage + 1, $currentPage + 2, $totalPages - 1, $totalPages,
    ], static fn (int $page): bool => $page >= 1 && $page <= $totalPages)));
    sort($pageNumbers);
}

$pageUrl = static function (int $page) use ($filters, $perPage, $showProviderFilter, $activeLanding, $siteUrl): string {
    if ($activeLanding !== null) {
        // Preserve only filters explicitly supplied by the visitor. Landing defaults
        // remain encoded by the landing itself and should not be duplicated in the URL.
        parse_str(CatalogReturn::query($_GET), $query);
        $query = is_array($query) ? $query : [];
        unset($query['page'], $query['tag_cursor'], $query['tag_dir']);
        if ($perPage !== 24) {
            $query['per_page'] = (string) $perPage;
        } else {
            unset($query['per_page']);
        }
        $query['page'] = (string) max(1, $page);
    } else {
        $query = array_filter([
            'provider' => $showProviderFilter ? $filters['provider'] : '',
            'q' => $filters['q'],
            'gender' => $filters['gender'],
            'country' => $filters['country'],
            'tag' => $filters['tag'],
            'age' => $filters['age'],
            'room_status' => $filters['room_status'],
            'new' => $filters['new_only'] ? '1' : '',
            'sort' => $filters['sort'] !== 'popular' ? $filters['sort'] : '',
            'per_page' => $perPage,
            'page' => max(1, $page),
        ], static fn (mixed $value): bool => $value !== '');
    }

    $base = $activeLanding !== null ? $siteUrl->landing((string) $activeLanding['slug']) : $siteUrl->path();
    return $base . ($query ? '?' . http_build_query($query) : '');
};
$tagPageUrl = static function (int $page, string $cursor, string $direction) use ($filters, $perPage, $showProviderFilter, $siteUrl): string {
    $query = array_filter([
        'provider' => $showProviderFilter ? $filters['provider'] : '',
        'q' => $filters['q'],
        'gender' => $filters['gender'],
        'country' => $filters['country'],
        'tag' => $filters['tag'],
        'age' => $filters['age'],
        'room_status' => $filters['room_status'],
        'new' => $filters['new_only'] ? '1' : '',
        'sort' => $filters['sort'] !== 'popular' ? $filters['sort'] : '',
        'per_page' => $perPage,
        'page' => max(1, $page),
        'tag_cursor' => $page > 1 ? $cursor : '',
        'tag_dir' => $page > 1 ? $direction : '',
    ], static fn (mixed $value): bool => $value !== '');

    return $siteUrl->path() . ($query ? '?' . http_build_query($query) : '');
};

$hasActiveFilters = $filters['q'] !== ''
    || ($catalog['mode'] === 'combined' && $filters['provider'] !== '')
    || $filters['gender'] !== ''
    || $filters['country'] !== ''
    || $filters['tag'] !== ''
    || $filters['age'] !== ''
    || $filters['room_status'] !== ''
    || $filters['new_only']
    || $filters['sort'] !== 'popular';

$pageTitle = $activeLanding !== null
    ? SafeMarkdown::interpolate((string) $activeLanding['title'], $landingVariables) . ' · ' . $siteAppearance['site_name']
    : $siteAppearance['site_name'] . ' · ' . $catalogLabel;
$pageDescription = ($activeLanding['description'] ?? '') !== ''
    ? SafeMarkdown::interpolate((string) $activeLanding['description'], $landingVariables)
    : $siteAppearance['hero_intro'];
$pageCanonical = $activeLanding !== null
    ? $siteUrl->absolute('cams/' . $activeLanding['slug'] . '/' . ($currentPage > 1 ? '?page=' . $currentPage : ''))
    : $siteUrl->absolute();
$landingHasOverrides = $activeLanding !== null && array_intersect(
    array_keys($_GET),
    ['provider', 'q', 'gender', 'country', 'tag', 'age', 'room_status', 'new', 'sort']
) !== [];
$pageRobots = $activeLanding !== null
    && $activeLanding['index']
    && $totalPerformers >= $activeLanding['minimum_results']
    && !$landingHasOverrides
    && $perPage === 24
    && $currentPage === 1
    ? 'index,follow'
    : ($activeLanding === null && !$hasActiveFilters && $currentPage === 1 ? 'index,follow' : 'noindex,follow');

if (PerformanceProfiler::enabled()) {
    PerformanceProfiler::start('render');
    ob_start();
    require $root . '/templates/home.php';
    $renderedPage = (string) ob_get_clean();
    PerformanceProfiler::stop('render');
    $requestStartedAt = (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
    PerformanceProfiler::add('request.total_before_output', microtime(true) - $requestStartedAt);
    $serverTiming = PerformanceProfiler::headerValue();
    if ($serverTiming !== '' && !headers_sent()) {
        header('Server-Timing: ' . $serverTiming);
        header('X-LiveCamForge-Performance: enabled');
        $performanceMeta = PerformanceProfiler::metaHeaderValue();
        if ($performanceMeta !== '') {
            header('X-LiveCamForge-Performance-Meta: ' . $performanceMeta);
        }
        header('Cache-Control: no-store');
    }
    echo $renderedPage, PerformanceProfiler::comment();
} else {
    require $root . '/templates/home.php';
}
