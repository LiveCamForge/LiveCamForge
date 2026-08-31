<?php

declare(strict_types=1);

use LiveCamForge\Core\AdminAuth;
use LiveCamForge\Core\AdminPasswordPolicy;
use LiveCamForge\Core\AdminLoginThrottle;
use LiveCamForge\Core\SecurityHeaders;
use LiveCamForge\Core\SiteUrl;
use LiveCamForge\Core\DemoMode;
use LiveCamForge\Core\AppearanceSettings;
use LiveCamForge\Core\CatalogSettings;
use LiveCamForge\Core\PerformerTypes;
use LiveCamForge\Core\PerformerCountry;
use LiveCamForge\Core\OperationalSettings;
use LiveCamForge\Core\LocalConfigManager;
use LiveCamForge\Core\Translator;
use LiveCamForge\Core\VisitorGeo;
use LiveCamForge\Database\Connection;
use LiveCamForge\Database\Migrator;
use LiveCamForge\Providers\ProviderFactory;
use LiveCamForge\Providers\CrakRevenue\CrakRevenueAdapter;
use LiveCamForge\Providers\CrakRevenue\CrakRevenueClient;
use LiveCamForge\Repositories\PerformerRepository;
use LiveCamForge\Repositories\ClickRepository;
use LiveCamForge\Repositories\ConversionRepository;
use LiveCamForge\Repositories\ConversionSyncRunRepository;
use LiveCamForge\Repositories\SettingsRepository;
use LiveCamForge\Repositories\SyncRunRepository;
use LiveCamForge\Repositories\TrafficLandingRepository;
use LiveCamForge\Services\SyncProviders;
use LiveCamForge\Services\BrandAssetStorage;
use LiveCamForge\Services\CatalogCountCache;
use LiveCamForge\Services\CrakRevenueAuthorization;
use LiveCamForge\Postbacks\LiveJasminPostbackHandler;
use LiveCamForge\Postbacks\StripchatPostbackHandler;
use LiveCamForge\Postbacks\PostbackHandlerFactory;

$root = dirname(__DIR__);
if (!is_file($root . '/config/local.php')) {
    header('Location: ../install/');
    exit;
}

$baseConfig = require $root . '/app/bootstrap.php';
$localConfigManager = new LocalConfigManager($root);
if (!(bool) $baseConfig->get('admin.enabled', true)) {
    http_response_code(404);
    exit;
}

SecurityHeaders::sendPrivatePage();
header('Cache-Control: private, no-store');

$secureCookieOverride = $baseConfig->get('admin.secure_cookies', null);
$secureCookies = is_bool($secureCookieOverride)
    ? $secureCookieOverride
    : (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
@ini_set('session.use_strict_mode', '1');
@ini_set('session.use_only_cookies', '1');
$configuredSessionName = (string) $baseConfig->get('admin.session_name', 'livecamforge_admin');

// Scope the Admin session to the physical installation path derived from the
// current request, not from seo.base_url. SEO configuration is editable and
// must never determine authentication-cookie isolation.
$adminScriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php'));
$adminDirectory = rtrim(str_replace('\\', '/', dirname($adminScriptName)), '/');
$adminBasePath = rtrim(str_replace('\\', '/', dirname($adminDirectory)), '/');
$adminCookiePath = $adminBasePath === '' || $adminBasePath === '.' ? '/' : $adminBasePath . '/';

// Keep the historical session name at the domain root, while giving every
// subdirectory installation a deterministic, path-specific session name.
$adminSessionName = $configuredSessionName;
if ($adminCookiePath !== '/') {
    $adminSessionName .= '_' . substr(hash('sha256', $adminCookiePath), 0, 12);
}
session_name($adminSessionName);

session_set_cookie_params([
    'lifetime' => 0,
    'path' => $adminCookiePath,
    'secure' => $secureCookies,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

$pdo = Connection::make($baseConfig);
Migrator::run($pdo, $root . '/database/migrations');
$dbPerformanceEnvironment = [
    'innodb_buffer_pool_size' => null,
    'max_allowed_packet' => null,
];
try {
    $dbVariables = $pdo->query(
        "SHOW VARIABLES WHERE Variable_name IN ('innodb_buffer_pool_size', 'max_allowed_packet')"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
    $dbPerformanceEnvironment['innodb_buffer_pool_size'] = isset($dbVariables['innodb_buffer_pool_size'])
        ? (int) $dbVariables['innodb_buffer_pool_size']
        : null;
    $dbPerformanceEnvironment['max_allowed_packet'] = isset($dbVariables['max_allowed_packet'])
        ? (int) $dbVariables['max_allowed_packet']
        : null;
} catch (Throwable) {
    // Performance recommendations are informational and must never block Admin.
}
$dbPerformanceRecommendations = [
    'buffer_pool' => [
        'ok' => $dbPerformanceEnvironment['innodb_buffer_pool_size'] === null
            || $dbPerformanceEnvironment['innodb_buffer_pool_size'] >= 64 * 1024 * 1024,
        'bytes' => $dbPerformanceEnvironment['innodb_buffer_pool_size'],
        'recommended_bytes' => 64 * 1024 * 1024,
    ],
    'packet' => [
        'ok' => $dbPerformanceEnvironment['max_allowed_packet'] === null
            || $dbPerformanceEnvironment['max_allowed_packet'] >= 8 * 1024 * 1024,
        'bytes' => $dbPerformanceEnvironment['max_allowed_packet'],
        'recommended_bytes' => 8 * 1024 * 1024,
    ],
];
$settings = new SettingsRepository($pdo);
$languageDiscovery = new Translator($root . '/languages', 'en', 'en');
$landingLanguages = $languageDiscovery->available();
$operationalSettings = new OperationalSettings(
    $settings,
    $baseConfig,
    ProviderFactory::availableNames(),
    array_keys($landingLanguages)
);
$config = $operationalSettings->effectiveConfig();
$demoMode = DemoMode::enabled($config);
$translator = new Translator(
    $root . '/languages',
    (string) $config->get('locale', 'en'),
    (string) $config->get('fallback_locale', 'en')
);
$auth = new AdminAuth(
    $settings,
    (int) $baseConfig->get('admin.session_idle_timeout_seconds', 3600)
);
$loginThrottle = new AdminLoginThrottle(
    $root . '/storage/cache/admin-login',
    (int) $baseConfig->get('admin.login_max_attempts', 5),
    (int) $baseConfig->get('admin.login_window_seconds', 300),
    (int) $baseConfig->get('admin.login_lockout_seconds', 600),
);
$loginClientIdentifier = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$appearanceSettings = new AppearanceSettings($settings, $config, $translator);
$brandingStorage = new BrandAssetStorage($root . '/storage/branding');
$performers = new PerformerRepository($pdo, PerformerTypes::fromConfig($config));
$clicks = new ClickRepository($pdo);
$conversions = new ConversionRepository($pdo);
$conversionRuns = new ConversionSyncRunRepository($pdo);
$runs = new SyncRunRepository($pdo);
$landingLanguages = $translator->available();
$landingRepository = new TrafficLandingRepository($pdo, $config, array_keys($landingLanguages));
$providerName = (string) $config->get('provider', 'demo');
$providerNames = ProviderFactory::enabledNames($config);
$availableProviderNames = array_values(array_filter(
    ProviderFactory::availableNames(),
    static fn (string $name): bool => $name !== 'demo'
        && ($demoMode || !DemoMode::isDemoProvider($name))
));
$affiliateRouteGroups = ProviderFactory::affiliateRouteGroups();
$routedProviderNames = ProviderFactory::routedProviderNames();
$crakRevenueAuthorization = new CrakRevenueAuthorization($settings, $baseConfig);
$crakRevenueBrandLabels = [
    'mfc' => 'MyFreeCams',
    'streamate' => 'Jerkmate',
    'chaturbate' => 'Chaturbate',
    'bongacash' => 'BongaCams',
    'awempire' => 'LiveJasmin',
    'stripchat' => 'Stripchat',
    'imlive' => 'ImLive',
];
$crakRevenueBrandStatuses = [];
foreach ($crakRevenueBrandLabels as $brand => $_label) {
    $crakRevenueBrandStatuses[$brand] = $crakRevenueAuthorization->statusForBrand($brand);
}
$crakRevenueCheckedAt = $crakRevenueAuthorization->checkedAt();
$availableProviderLabels = [];
$availableProviderPublicLabels = [];
$providerCredentialStatus = [];
$providerConnectionStatus = [];
$providerCapabilities = [];
foreach ($availableProviderNames as $name) {
    $availableAdapter = ProviderFactory::make($name, $config, $root);
    $availableProviderLabels[$name] = $availableAdapter->displayName();
    $availableProviderPublicLabels[$name] = ProviderFactory::publicDisplayName($name, $config, $root);
    $providerCapabilities[$name] = $availableAdapter->capabilities();
    $providerCredentialStatus[$name] = match ($name) {
        'demo', 'demo_alpha', 'demo_beta' => true,
        'chaturbate' => trim((string) $baseConfig->get('chaturbate.wm', '')) !== '',
        'bongacams' => (int) $baseConfig->get('bongacams.campaign_id', 0) > 0,
        'cam4' => (int) $baseConfig->get('cam4.affiliate_id', 0) > 0,
        'livejasmin' => trim((string) $baseConfig->get('livejasmin.ps_id', '')) !== ''
            && trim((string) $baseConfig->get('livejasmin.access_key', '')) !== '',
        'stripchat' => trim((string) $baseConfig->get('stripchat.api_key', '')) !== ''
            && trim((string) $baseConfig->get('stripchat.user_id', '')) !== '',
        'crakrevenue_mfc',
        'crakrevenue_streamate',
        'crakrevenue_chaturbate',
        'crakrevenue_awempire',
        'crakrevenue_stripchat',
        'crakrevenue_imlive',
        'crakrevenue_bongacash' => trim((string) $baseConfig->get('crakrevenue.api_key', '')) !== ''
            && trim((string) $baseConfig->get('crakrevenue.token', '')) !== '',
        default => false,
    };
    $crakRevenueBrand = CrakRevenueAdapter::brandForProvider($name);
    $providerConnectionStatus[$name] = $crakRevenueBrand !== null
        ? $crakRevenueAuthorization->statusForBrand($crakRevenueBrand)
        : ($providerCredentialStatus[$name]
            ? CrakRevenueAuthorization::CONFIGURED
            : CrakRevenueAuthorization::NOT_CONFIGURED);
}
$postbackSecretStatus = [
    'chaturbate' => !(bool) $baseConfig->get('chaturbate.postback.require_checksum', true)
        || trim((string) $baseConfig->get('chaturbate.postback.validation_salt', '')) !== '',
    'livejasmin' => !(bool) $baseConfig->get('livejasmin.postback.require_secret', true)
        || trim((string) $baseConfig->get('livejasmin.postback.secret', '')) !== '',
    'stripchat' => !(bool) $baseConfig->get('stripchat.postback.require_secret', true)
        || trim((string) $baseConfig->get('stripchat.postback.secret', '')) !== '',
    'crakrevenue' => !(bool) $baseConfig->get('crakrevenue.postback.require_secret', true)
        || trim((string) $baseConfig->get('crakrevenue.postback.secret', '')) !== '',
];
$providerLabels = [];
foreach ($providerNames as $name) {
    $providerLabels[$name] = ProviderFactory::make($name, $config, $root)->displayName();
}
$catalogSettings = new CatalogSettings($settings, $config, $providerNames);
$adminSection = in_array($_GET['section'] ?? '', ['operations', 'configuration', 'catalog', 'landings', 'conversions', 'appearance'], true)
    ? (string) $_GET['section']
    : 'operations';
$notice = isset($_SESSION['admin_notice']) && is_array($_SESSION['admin_notice']) ? $_SESSION['admin_notice'] : null;
unset($_SESSION['admin_notice']);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirectToAdmin(string $section = 'operations', string $edit = ''): never
{
    if (!in_array($section, ['configuration', 'catalog', 'landings', 'conversions', 'appearance'], true)) {
        header('Location: ./');
        exit;
    }
    $target = '?section=' . rawurlencode($section);
    if ($section === 'landings' && $edit !== '') {
        $target .= '&edit=' . rawurlencode($edit);
    }
    header('Location: ' . $target);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $returnSection = in_array($_POST['return_section'] ?? '', ['configuration', 'catalog', 'landings', 'conversions', 'appearance'], true)
        ? (string) $_POST['return_section']
        : 'operations';
    if (!$auth->verifyCsrf($_POST['csrf_token'] ?? null)) {
        $_SESSION['admin_notice'] = ['type' => 'error', 'message' => $translator->get('admin.error.csrf')];
        redirectToAdmin($returnSection);
    }

    if ($demoMode && $auth->check() && in_array($action, DemoMode::blockedAdminActions(), true)) {
        $_SESSION['admin_notice'] = ['type' => 'error', 'message' => $translator->get('admin.demo.blocked')];
        redirectToAdmin($returnSection);
    }
    if ($demoMode && in_array($action, ['save_catalog_all', 'save_catalog_sources'], true)) {
        $submittedDemoSources = array_values(array_unique(array_filter(array_map(
            static fn (mixed $provider): string => strtolower(trim((string) $provider)),
            is_array($_POST['enabled_providers'] ?? null) ? $_POST['enabled_providers'] : []
        ))));
        $unexpectedDemoSources = array_values(array_diff($submittedDemoSources, DemoMode::providerNames()));
        if ($unexpectedDemoSources !== []) {
            $_SESSION['admin_notice'] = ['type' => 'error', 'message' => $translator->get('admin.demo.catalog_sources_locked')];
            redirectToAdmin('catalog');
        }
        $_POST['enabled_providers'] = DemoMode::providerNames();
        $_POST['primary_provider'] = in_array((string) ($_POST['primary_provider'] ?? ''), DemoMode::providerNames(), true)
            ? $_POST['primary_provider']
            : 'demo_alpha';
        $_POST['catalog_mode'] = 'combined';
    }

    if ($action === 'setup' && !$auth->isConfigured()) {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $confirmation = (string) ($_POST['password_confirmation'] ?? '');
        if (strlen($username) < 3
            || !hash_equals($password, $confirmation)
            || AdminPasswordPolicy::isWeak($password, $username, (string) $config->get('name', 'LiveCamForge'))
        ) {
            $_SESSION['admin_notice'] = ['type' => 'error', 'message' => $translator->get('admin.error.setup')];
        } else {
            $auth->setup($username, $password);
            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => $translator->get('admin.setup_completed')];
        }
        redirectToAdmin();
    }

    if ($action === 'login' && $auth->isConfigured()) {
        if ($loginThrottle->isBlocked($loginClientIdentifier)) {
            $_SESSION['admin_notice'] = ['type' => 'error', 'message' => $translator->get('admin.error.login')];
            redirectToAdmin();
        }
        if (!$auth->attempt((string) ($_POST['username'] ?? ''), (string) ($_POST['password'] ?? ''))) {
            $loginThrottle->registerFailure($loginClientIdentifier);
            $_SESSION['admin_notice'] = ['type' => 'error', 'message' => $translator->get('admin.error.login')];
        } else {
            $loginThrottle->clear($loginClientIdentifier);
        }
        redirectToAdmin();
    }

    if ($action === 'logout' && $auth->check()) {
        $auth->logout();
        header('Location: ./');
        exit;
    }

    if ($action === 'sync' && $auth->check()) {
        try {
            $requestedProvider = strtolower(trim((string) ($_POST['provider'] ?? '')));
            $targets = $requestedProvider === '__all__' ? $providerNames : [$requestedProvider];
            if ($demoMode && array_diff($targets, DemoMode::providerNames()) !== []) {
                throw new RuntimeException($translator->get('admin.demo.blocked'));
            }
            if ($targets === [] || array_diff($targets, $providerNames) !== []) {
                throw new RuntimeException($translator->get('admin.error.provider'));
            }
            $results = (new SyncProviders(
                $config,
                $root,
                $performers,
                $runs,
                $pdo
            ))->run($targets, 'admin');
            $successful = array_filter($results, static fn (array $result): bool => $result['success']);
            $failed = array_filter($results, static fn (array $result): bool => !$result['success']);
            $totalImported = array_sum(array_column($successful, 'count'));

            if (count($targets) === 1) {
                $result = reset($results);
                $message = $result['success']
                    ? $translator->get('admin.sync_completed', ['count' => $result['count']])
                    : $translator->get('admin.sync_failed', ['message' => $result['error']]);
            } else {
                $message = $translator->get('admin.sync_batch_completed', [
                    'success' => count($successful),
                    'failed' => count($failed),
                    'count' => $totalImported,
                ]);
            }
            $_SESSION['admin_notice'] = [
                'type' => $failed === [] ? 'success' : 'error',
                'message' => $message,
            ];
        } catch (Throwable $exception) {
            $_SESSION['admin_notice'] = [
                'type' => 'error',
                'message' => $translator->get('admin.sync_failed', ['message' => $exception->getMessage()]),
            ];
        }
        redirectToAdmin();
    }

    if ($action === 'reset_demo' && $auth->check() && $demoMode) {
        try {
            $settings->set('runtime.configuration', json_encode(array_merge(
                ['locale' => (string) $config->get('locale', 'en'), 'fallback_locale' => 'en'],
                DemoMode::runtimeConfiguration()
            ), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $settings->setMany([
                'catalog.mode' => 'combined',
                'catalog.primary_provider' => 'demo_alpha',
                'catalog.show_provider_filter' => '1',
                'catalog.show_provider_badges' => '1',
            ]);
            $appearanceSettings->reset();
            foreach (array_keys((array) $config->get('traffic.landings', [])) as $slug) {
                try { $landingRepository->reset((string) $slug); } catch (Throwable) {}
            }
            (new SyncProviders($config, $root, $performers, $runs, $pdo))->run(DemoMode::providerNames(), 'demo-reset');
            CatalogCountCache::invalidate($root);
            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => $translator->get('admin.demo.reset_done')];
        } catch (Throwable $exception) {
            $_SESSION['admin_notice'] = ['type' => 'error', 'message' => $translator->get('admin.demo.reset_failed', ['message' => $exception->getMessage()])];
        }
        redirectToAdmin();
    }

    if ($action === 'confirm_cron_setup' && $auth->check()) {
        if (($_POST['cron_setup_confirmed'] ?? '') !== '1') {
            $_SESSION['admin_notice'] = ['type' => 'error', 'message' => $translator->get('admin.cron.confirm_required')];
        } else {
            $settings->set('deployment.cron_confirmed_at', date(DATE_ATOM));
            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => $translator->get('admin.cron.confirmed_notice')];
        }
        redirectToAdmin();
    }

    if (($action === 'save_configuration_all' || $action === 'save_integrations' || $action === 'save_integrations_all') && $auth->check()) {
        $localConfigPath = $root . '/config/local.php';
        $localConfigBackup = is_file($localConfigPath) ? file_get_contents($localConfigPath) : false;
        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
            }
            $localConfigManager->saveProviderConfiguration($_POST);
            $operationalSettings->saveProviderIntegrations($_POST);
            if ($action === 'save_integrations_all' || $action === 'save_configuration_all') {
                $operationalSettings->saveTechnicalSettings($_POST);
            }
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            $_SESSION['admin_notice'] = [
                'type' => 'success',
                'message' => $translator->get('admin.configuration.saved'),
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if (is_string($localConfigBackup)) {
                @file_put_contents($localConfigPath, $localConfigBackup, LOCK_EX);
                @chmod($localConfigPath, 0600);
            }
            $_SESSION['admin_notice'] = [
                'type' => 'error',
                'message' => $translator->get('admin.configuration.error', ['message' => $exception->getMessage()]),
            ];
        }
        redirectToAdmin('configuration');
    }

    if ($action === 'save_technical_settings' && $auth->check()) {
        try {
            $operationalSettings->saveTechnicalSettings($_POST);
            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => $translator->get('admin.configuration.saved')];
        } catch (Throwable $exception) {
            $_SESSION['admin_notice'] = ['type' => 'error', 'message' => $translator->get('admin.configuration.error', ['message' => $exception->getMessage()])];
        }
        redirectToAdmin('configuration');
    }

    if ($action === 'save_provider_configuration' && $auth->check()) {
        try {
            $localConfigManager->saveProviderConfiguration($_POST);
            $operationalSettings->saveProviderIntegrations($_POST);
            $_SESSION['admin_notice'] = [
                'type' => 'success',
                'message' => $translator->get('admin.configuration.provider_credentials_saved'),
            ];
        } catch (Throwable $exception) {
            $_SESSION['admin_notice'] = [
                'type' => 'error',
                'message' => $translator->get('admin.configuration.provider_credentials_error', [
                    'message' => $exception->getMessage(),
                ]),
            ];
        }
        redirectToAdmin('configuration');
    }

    if ($action === 'save_operational_settings' && $auth->check()) {
        try {
            $operationalSettings->save($_POST);
            $_SESSION['admin_notice'] = [
                'type' => 'success',
                'message' => $translator->get('admin.configuration.saved'),
            ];
        } catch (Throwable $exception) {
            $_SESSION['admin_notice'] = [
                'type' => 'error',
                'message' => $translator->get('admin.configuration.error', ['message' => $exception->getMessage()]),
            ];
        }
        redirectToAdmin('configuration');
    }

    if ($action === 'test_crakrevenue_access' && $auth->check()) {
        try {
            $client = new CrakRevenueClient($config);
            if (!$client->configured()) {
                throw new RuntimeException($translator->get('admin.configuration.crakrevenue_not_configured'));
            }
            $results = $client->testBrandsDetailed();
            $crakRevenueAuthorization->save($results);
            $parts = [];
            foreach ($results as $brand => $status) {
                $parts[] = ($crakRevenueBrandLabels[$brand] ?? $brand) . ': '
                    . $translator->get('admin.configuration.connection_status.' . $status);
            }
            $_SESSION['admin_notice'] = [
                'type' => in_array(CrakRevenueAuthorization::ERROR, $results, true) ? 'error' : 'success',
                'message' => $translator->get('admin.configuration.crakrevenue_test_result', [
                    'result' => implode(' · ', $parts),
                ]),
            ];
        } catch (Throwable $exception) {
            $_SESSION['admin_notice'] = [
                'type' => 'error',
                'message' => $translator->get('admin.configuration.crakrevenue_test_error', [
                    'message' => $exception->getMessage(),
                ]),
            ];
        }
        redirectToAdmin('configuration');
    }

    if ($action === 'reset_operational_settings' && $auth->check()) {
        $operationalSettings->resetIntegrations();
        $_SESSION['admin_notice'] = [
            'type' => 'success',
            'message' => $translator->get('admin.configuration.reset_done'),
        ];
        redirectToAdmin('configuration');
    }

    if ($action === 'save_appearance' && $auth->check()) {
        try {
            $appearanceSettings->save($_POST);

            foreach (['logo', 'favicon'] as $kind) {
                $key = 'branding.' . $kind . '_file';
                $oldFilename = $settings->get($key);
                if (isset($_POST['remove_' . $kind])) {
                    $brandingStorage->remove($oldFilename);
                    $settings->set($key, null);
                    continue;
                }
                if (isset($_FILES[$kind]) && ($_FILES[$kind]['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $newFilename = $brandingStorage->save($_FILES[$kind], $kind);
                    $settings->set($key, $newFilename);
                    $brandingStorage->remove($oldFilename);
                }
            }

            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => $translator->get('admin.appearance.saved')];
        } catch (Throwable) {
            $_SESSION['admin_notice'] = ['type' => 'error', 'message' => $translator->get('admin.appearance.error')];
        }
        redirectToAdmin('appearance');
    }

    if ($action === 'save_catalog_all' && $auth->check()) {
        try {
            if (!$pdo->inTransaction()) {
                $pdo->beginTransaction();
            }
            $operationalSettings->saveCatalogSources($_POST);
            $operationalSettings->saveLanguages($_POST);
            $submittedCatalogProviders = array_values(array_unique(array_filter(array_map(
                static fn (mixed $provider): string => strtolower(trim((string) $provider)),
                is_array($_POST['enabled_providers'] ?? null) ? $_POST['enabled_providers'] : []
            ), static fn (string $provider): bool => in_array($provider, $availableProviderNames, true))));
            (new CatalogSettings($settings, $config, $submittedCatalogProviders))->save($_POST);
            if ($pdo->inTransaction()) {
                $pdo->commit();
            }
            CatalogCountCache::invalidate($root);
            $_SESSION['admin_notice'] = [
                'type' => 'success',
                'message' => $translator->get('admin.catalog_settings.saved'),
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['admin_notice'] = [
                'type' => 'error',
                'message' => $translator->get('admin.catalog_settings.sources_error', [
                    'message' => $exception->getMessage(),
                ]),
            ];
        }
        redirectToAdmin('catalog');
    }

    if ($action === 'save_catalog' && $auth->check()) {
        try {
            $catalogSettings->save($_POST);
            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => $translator->get('admin.catalog_settings.saved')];
        } catch (Throwable) {
            $_SESSION['admin_notice'] = ['type' => 'error', 'message' => $translator->get('admin.catalog_settings.error')];
        }
        redirectToAdmin('catalog');
    }

    if ($action === 'save_catalog_sources' && $auth->check()) {
        try {
            $operationalSettings->saveCatalogSources($_POST);
            CatalogCountCache::invalidate($root);
            $_SESSION['admin_notice'] = [
                'type' => 'success',
                'message' => $translator->get('admin.catalog_settings.sources_saved'),
            ];
        } catch (Throwable $exception) {
            $_SESSION['admin_notice'] = [
                'type' => 'error',
                'message' => $translator->get('admin.catalog_settings.sources_error', [
                    'message' => $exception->getMessage(),
                ]),
            ];
        }
        redirectToAdmin('catalog');
    }

    if ($action === 'save_recruitment' && $auth->check()) {
        try {
            if ($demoMode) {
                foreach ($availableProviderNames as $demoRecruitmentProvider) {
                    if (DemoMode::isDemoProvider($demoRecruitmentProvider)) {
                        $_POST['recruitment_provider_enabled'][$demoRecruitmentProvider] = '1';
                        $_POST['recruitment_url'][$demoRecruitmentProvider] = DemoMode::modelRecruitmentUrl();
                    } else {
                        unset($_POST['recruitment_provider_enabled'][$demoRecruitmentProvider]);
                        $_POST['recruitment_url'][$demoRecruitmentProvider] = '';
                    }
                }
            }
            $operationalSettings->saveRecruitment($_POST);
            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => $translator->get('admin.configuration.saved')];
        } catch (Throwable $exception) {
            $_SESSION['admin_notice'] = ['type' => 'error', 'message' => $translator->get('admin.configuration.error', ['message' => $exception->getMessage()])];
        }
        redirectToAdmin('landings', 'recruitment');
    }

    if ($action === 'save_webmaster_recruitment' && $auth->check()) {
        try {
            if ($demoMode) {
                $_POST['webmaster_recruitment_cta_url'] = DemoMode::webmasterRecruitmentUrl();
            }
            $operationalSettings->saveWebmasterRecruitment($_POST);
            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => $translator->get('admin.configuration.saved')];
        } catch (Throwable $exception) {
            $_SESSION['admin_notice'] = ['type' => 'error', 'message' => $translator->get('admin.configuration.error', ['message' => $exception->getMessage()])];
        }
        redirectToAdmin('landings', 'webmaster-recruitment');
    }

    if ($action === 'save_landing' && $auth->check()) {
        try {
            $savedSlug = $landingRepository->save($_POST, $providerNames);
            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => $translator->get('admin.landings.saved')];
            redirectToAdmin('landings', $savedSlug);
        } catch (Throwable $exception) {
            $_SESSION['admin_notice'] = [
                'type' => 'error',
                'message' => $translator->get('admin.landings.error', ['message' => $exception->getMessage()]),
            ];
            redirectToAdmin('landings', trim((string) ($_POST['original_slug'] ?? 'new')) ?: 'new');
        }
    }

    if ($action === 'delete_landing' && $auth->check()) {
        try {
            $landingRepository->delete(strtolower(trim((string) ($_POST['slug'] ?? ''))));
            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => $translator->get('admin.landings.deleted')];
        } catch (Throwable $exception) {
            $_SESSION['admin_notice'] = [
                'type' => 'error',
                'message' => $translator->get('admin.landings.error', ['message' => $exception->getMessage()]),
            ];
        }
        redirectToAdmin('landings');
    }

    if ($action === 'reset_landing' && $auth->check()) {
        try {
            $resetSlug = strtolower(trim((string) ($_POST['slug'] ?? '')));
            $landingRepository->reset($resetSlug);
            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => $translator->get('admin.landings.reset_done')];
            redirectToAdmin('landings', $resetSlug);
        } catch (Throwable $exception) {
            $_SESSION['admin_notice'] = [
                'type' => 'error',
                'message' => $translator->get('admin.landings.error', ['message' => $exception->getMessage()]),
            ];
            redirectToAdmin('landings');
        }
    }

    if ($action === 'test_postback' && $auth->check()) {
        try {
            $testProvider = strtolower(trim((string) ($_POST['provider'] ?? '')));
            $testMode = strtolower(trim((string) ($_POST['test_mode'] ?? 'conversion')));
            if (!in_array($testProvider, $providerNames, true) || !PostbackHandlerFactory::supports($testProvider)) {
                throw new RuntimeException($translator->get('admin.error.provider'));
            }
            $sid = preg_replace('/[^a-z0-9_.-]/i', '', (string) ($_POST['sid'] ?? '')) ?? '';
            if ($testProvider === 'chaturbate') {
                $salt = (string) $config->get('chaturbate.postback.validation_salt', '');
                $requireChecksum = (bool) $config->get('chaturbate.postback.require_checksum', true);
                if (!(bool) $config->get('chaturbate.postback.enabled', false) || ($requireChecksum && $salt === '')) {
                    throw new RuntimeException($translator->get('admin.conversions.test_not_configured'));
                }
                $logId = 'test_' . bin2hex(random_bytes(6));
                $attempt = '1';
                $payload = [
                    'log_id' => $logId,
                    'attempt' => $attempt,
                    'checksum' => md5($salt . $logId . $attempt),
                    'transaction_id' => 'tx_' . $logId,
                    'click_id' => 'click_' . $logId,
                    'type' => 'test_conversion',
                    'payout' => 4.20,
                    'amount' => 21.00,
                    'currency' => 'USD',
                    'token' => 0,
                    'sid' => $sid,
                    'track' => (string) $config->get('chaturbate.postback.track', 'livecamforge'),
                    'timestamp' => date(DATE_ATOM),
                ];
            } elseif ($testProvider === 'livejasmin') {
                $requireSecret = (bool) $config->get('livejasmin.postback.require_secret', true);
                if (!(bool) $config->get('livejasmin.postback.enabled', false)
                    || ($requireSecret && trim((string) $config->get('livejasmin.postback.secret', '')) === '')
                ) {
                    throw new RuntimeException($translator->get('admin.conversions.livejasmin_test_not_configured'));
                }
                $payload = (new LiveJasminPostbackHandler($config, $clicks, $conversions))->testPayload($sid);
            } elseif ($testProvider === 'stripchat') {
                $requireSecret = (bool) $config->get('stripchat.postback.require_secret', true);
                if (!(bool) $config->get('stripchat.postback.enabled', false)
                    || ($requireSecret && trim((string) $config->get('stripchat.postback.secret', '')) === '')
                ) {
                    throw new RuntimeException($translator->get('admin.conversions.test_not_configured'));
                }
                $payload = (new StripchatPostbackHandler($config, $clicks, $conversions))->testPayload($sid);
            } elseif (str_starts_with($testProvider, 'crakrevenue_')) {
                $requireSecret = (bool) $config->get('crakrevenue.postback.require_secret', true);
                if (!(bool) $config->get('crakrevenue.postback.enabled', false)
                    || ($requireSecret && trim((string) $config->get('crakrevenue.postback.secret', '')) === '')
                ) {
                    throw new RuntimeException($translator->get('admin.conversions.test_not_configured'));
                }
                $payload = (new LiveCamForge\Postbacks\CrakRevenuePostbackHandler($config, $clicks, $conversions))
                    ->testPayload($testProvider, $sid);
            } else {
                throw new RuntimeException($translator->get('admin.error.provider'));
            }
            $handler = PostbackHandlerFactory::make($testProvider, $config, $clicks, $conversions);
            $result = $handler->handle($payload);
            if ($result['status'] !== 200 || !($result['body']['ok'] ?? false)) {
                throw new RuntimeException((string) ($result['body']['message'] ?? 'Postback test failed'));
            }
            if ($testMode === 'duplicate' && str_starts_with($testProvider, 'crakrevenue_')) {
                if (($result['body']['duplicate'] ?? false) === true) {
                    throw new RuntimeException($translator->get('admin.conversions.duplicate_test_first_duplicate'));
                }
                $duplicateResult = $handler->handle($payload);
                if ($duplicateResult['status'] !== 200
                    || !($duplicateResult['body']['ok'] ?? false)
                    || !($duplicateResult['body']['duplicate'] ?? false)
                ) {
                    throw new RuntimeException($translator->get('admin.conversions.duplicate_test_failed'));
                }
                $_SESSION['admin_notice'] = [
                    'type' => 'success',
                    'message' => $translator->get('admin.conversions.duplicate_test_completed'),
                ];
            } else {
                $_SESSION['admin_notice'] = [
                    'type' => 'success',
                    'message' => $translator->get('admin.conversions.test_completed', [
                        'attributed' => ($result['body']['attributed'] ?? false)
                            ? $translator->get('admin.conversions.yes')
                            : $translator->get('admin.conversions.no'),
                    ]),
                ];
            }
        } catch (Throwable $exception) {
            $_SESSION['admin_notice'] = ['type' => 'error', 'message' => $exception->getMessage()];
        }
        redirectToAdmin('conversions');
    }

    if ($action === 'reset_appearance' && $auth->check()) {
        try {
            $brandingStorage->remove($settings->get('branding.logo_file'));
            $brandingStorage->remove($settings->get('branding.favicon_file'));
            $settings->setMany(['branding.logo_file' => null, 'branding.favicon_file' => null]);
            $appearanceSettings->reset();
            $_SESSION['admin_notice'] = ['type' => 'success', 'message' => $translator->get('admin.appearance.reset_done')];
        } catch (Throwable) {
            $_SESSION['admin_notice'] = ['type' => 'error', 'message' => $translator->get('admin.appearance.error')];
        }
        redirectToAdmin('appearance');
    }
}

$configured = $auth->isConfigured();
$authenticated = $auth->check();
$csrfToken = $auth->csrfToken();
$siteAppearance = $appearanceSettings->values();

// Admin-only previews let special recruitment pages be reviewed before publication.
$specialPreview = strtolower(trim((string) ($_GET['preview'] ?? '')));
if ($authenticated && in_array($specialPreview, ['recruitment', 'webmaster-recruitment'], true)) {
    $siteUrl = new SiteUrl($config, $_SERVER);
    $assetUrl = static fn (string $path): string => $siteUrl->asset($path);
    $adminPreview = true;
    if ($specialPreview === 'recruitment') {
        $recruitment = $config->get('recruitment.models', []);
        $recruitment = is_array($recruitment) ? $recruitment : [];
        $recruitment['index'] = false;
        require $root . '/templates/recruitment.php';
        exit;
    }
    $webmasterRecruitment = $config->get('recruitment.webmasters', []);
    $webmasterRecruitment = is_array($webmasterRecruitment) ? $webmasterRecruitment : [];
    $webmasterRecruitment['index'] = false;
    require $root . '/templates/webmaster-recruitment.php';
    exit;
}

$catalogPreferences = $catalogSettings->values();
$operationalValues = $operationalSettings->values();
$providerConfigurationValues = $localConfigManager->values($baseConfig);
$localConfigurationWritable = $localConfigManager->writable();
$cronSetupConfirmedAt = trim((string) ($settings->get('deployment.cron_confirmed_at') ?? ''));
$adminVisitorGeo = VisitorGeo::detect($config, $_SERVER);
$brandingAssetBase = '../public/?branding=';
$recentRuns = [];
$providerStats = [];
$conversionPeriod = in_array($_GET['period'] ?? '', ['today', '7d', '30d', 'all'], true)
    ? (string) $_GET['period']
    : '7d';
$conversionSince = match ($conversionPeriod) {
    'today' => date('Y-m-d 00:00:00'),
    '7d' => date('Y-m-d H:i:s', time() - 7 * 86400),
    '30d' => date('Y-m-d H:i:s', time() - 30 * 86400),
    default => null,
};
$conversionSummary = ['clicks' => 0, 'conversions' => 0, 'attributed' => 0, 'payout' => 0.0, 'amount' => 0.0, 'conversion_rate' => 0.0, 'epc' => 0.0];
$recentConversions = [];
$conversionEventTypes = [];
$conversionCurrencyTotals = [];
$clickPerformance = [];
$sourcePerformance = [];
$latestPostbackClicks = [];
$recentConversionSyncRuns = [];
$postbackProviders = [];
$postbackEndpoint = '';
$landingRecords = [];
$landingCounts = [];
$landingEdit = null;
$recruitmentEdit = false;
$webmasterRecruitmentEdit = false;
$landingCountryOptions = [];
$landingEditSlug = strtolower(trim((string) ($_GET['edit'] ?? '')));
if ($authenticated) {
    if (in_array($adminSection, ['configuration', 'conversions'], true)) {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = preg_match('/^[A-Za-z0-9.:-]+$/', (string) ($_SERVER['HTTP_HOST'] ?? '')) === 1
            ? (string) $_SERVER['HTTP_HOST']
            : 'localhost';
        $scriptDirectory = str_replace('\\', '/', dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php')));
        $basePath = rtrim(str_replace('\\', '/', dirname($scriptDirectory)), '/');
        $postbackEndpoint = $scheme . '://' . $host . ($basePath !== '' ? $basePath : '') . '/postback.php';
    }
    // Load only the data needed by the selected tab. Previously every admin
    // request also ran conversion and landing reports, making the operations
    // page increasingly slow as providers and performers were added.
    if (in_array($adminSection, ['operations', 'catalog'], true)) {
        if ($adminSection === 'operations') {
            $runs->interruptStaleRunning(30);
            $runs->prune((int) $config->get('sync.history_days', 7));
            $recentRuns = $runs->recent(20);
        }
        $onlineCounts = $performers->countOnlineByProviders($providerNames);
        $latestRuns = $runs->latestSuccessfulByProviders($providerNames);
        foreach ($providerNames as $name) {
            $providerAdapter = ProviderFactory::make($name, $config, $root);
            $providerStats[$name] = [
                'online' => $onlineCounts[$name] ?? 0,
                'last_success' => $latestRuns[$name] ?? null,
                'active' => $name === $catalogPreferences['primary_provider'],
                'capabilities' => $providerAdapter->capabilities()->enabled(),
            ];
        }
    }
    if ($adminSection === 'conversions') {
        $conversionSummary = array_merge($conversionSummary, $conversions->summary($conversionSince));
        $conversionSummary['clicks'] = $clicks->countSince($conversionSince);
        $clickPerformance = $conversions->clickPerformance($conversionSince);
        $sourcePerformance = $clicks->sourcePerformance($conversionSince);
        $clickConversions = array_sum(array_map(static fn (array $row): int => (int) $row['conversions'], $clickPerformance));
        $conversionSummary['conversion_rate'] = $conversionSummary['clicks'] > 0
            ? 100 * $clickConversions / $conversionSummary['clicks']
            : 0.0;
        $recentConversions = $conversions->recent(20);
        $conversionEventTypes = $conversions->eventTypes($conversionSince);
        $conversionCurrencyTotals = $conversions->currencyTotals($conversionSince);
        $conversionRuns->interruptStaleRunning(60);
        $conversionRuns->prune(30);
        $recentConversionSyncRuns = $conversionRuns->recent(20);
        foreach ($providerNames as $name) {
            $adapter = ProviderFactory::make($name, $config, $root);
            if (!$adapter->capabilities()->postbackTracking) {
                continue;
            }
            $isCrakRevenue = str_starts_with($name, 'crakrevenue_');
            $enabled = $isCrakRevenue
                ? (bool) $config->get('crakrevenue.postback.enabled', false)
                : (bool) $config->get($name . '.postback.enabled', false);
            $ready = match (true) {
                $name === 'chaturbate' => $enabled && (!(bool) $config->get('chaturbate.postback.require_checksum', true)
                    || trim((string) $config->get('chaturbate.postback.validation_salt', '')) !== ''),
                $name === 'livejasmin' => $enabled && (!(bool) $config->get('livejasmin.postback.require_secret', true)
                    || trim((string) $config->get('livejasmin.postback.secret', '')) !== ''),
                $name === 'stripchat' => $enabled && (!(bool) $config->get('stripchat.postback.require_secret', true)
                    || trim((string) $config->get('stripchat.postback.secret', '')) !== ''),
                $isCrakRevenue => $enabled && (!(bool) $config->get('crakrevenue.postback.require_secret', true)
                    || trim((string) $config->get('crakrevenue.postback.secret', '')) !== ''),
                default => $enabled,
            };
            $postbackProviders[$name] = [
                'label' => $adapter->displayName(),
                'enabled' => $enabled,
                'ready' => $ready,
                'endpoint' => $postbackEndpoint . '?provider=' . rawurlencode($name),
            ];
            $latestPostbackClicks[$name] = $clicks->latestByProvider($name);
        }
    }
    if ($adminSection === 'landings') {
        foreach (array_keys($performers->availableCountries(['providers' => $providerNames])) as $countryCode) {
            $landingCountryOptions[$countryCode] = PerformerCountry::label($countryCode, $translator->locale());
        }
        asort($landingCountryOptions, SORT_NATURAL | SORT_FLAG_CASE);
        $landingRecords = $landingRepository->records();
        $landingFilterSets = [];
        foreach ($landingRecords as $slug => $landingRecord) {
            $landingFilterSets[$slug] = array_replace([
                'provider' => $catalogPreferences['mode'] === 'single' ? $catalogPreferences['primary_provider'] : '',
                'providers' => $catalogPreferences['mode'] === 'combined'
                    ? $providerNames : [$catalogPreferences['primary_provider']],
                'hide_restricted_when_geo_unknown' => true,
            ], is_array($landingRecord['filters'] ?? null) ? $landingRecord['filters'] : []);
        }
        $landingCacheContext = [
            'filter_sets' => $landingFilterSets,
            'provider_names' => $providerNames,
            'performer_types' => PerformerTypes::fromConfig($config),
        ];
        $cachedLandingCounts = CatalogCountCache::get($root, 'landings', $landingCacheContext);
        if (is_array($cachedLandingCounts)) {
            $landingCounts = array_intersect_key($cachedLandingCounts, $landingFilterSets);
        } else {
            $landingCounts = $performers->countOnlineForFilterSets($landingFilterSets, $providerNames);
            CatalogCountCache::put($root, 'landings', $landingCacheContext, $landingCounts);
        }
    }
    if ($landingEditSlug === 'recruitment') {
        $recruitmentEdit = true;
    } elseif ($landingEditSlug === 'webmaster-recruitment') {
        $webmasterRecruitmentEdit = true;
    } elseif ($landingEditSlug === 'new') {
        $emptyLandingContent = [];
        foreach (array_keys($landingLanguages) as $landingLocale) {
            $emptyLandingContent[$landingLocale] = [
                'title' => '', 'heading' => '', 'description' => '', 'eyebrow' => '',
                'intro' => '', 'body' => '', 'faq' => [],
            ];
        }
        $landingEdit = [
            'slug' => '', 'is_standard' => false, 'overridden' => false,
            'enabled' => true, 'index' => false, 'show_in_navigation' => false,
            'minimum_results' => 8, 'sort_order' => 100, 'filters' => ['sort' => 'popular'],
            'content' => $emptyLandingContent,
        ];
    } elseif ($landingEditSlug !== '') {
        $landingEdit = $landingRecords[$landingEditSlug] ?? null;
    } elseif ($adminSection === 'landings' && $landingRecords !== []) {
        $landingEdit = reset($landingRecords) ?: null;
    }
}

require $root . '/templates/admin.php';
