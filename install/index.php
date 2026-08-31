<?php

declare(strict_types=1);

use LiveCamForge\Core\Translator;
use LiveCamForge\Core\InstallerPreflight;
use LiveCamForge\Core\AdminPasswordPolicy;
use LiveCamForge\Database\Migrator;

$root = dirname(__DIR__);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
header('X-Frame-Options: DENY');
header("Content-Security-Policy: frame-ancestors 'none'");
header('Cache-Control: no-store');
if (is_file($root . '/config/local.php')) {
    header('Location: ../');
    exit;
}

require $root . '/app/Core/Translator.php';
require $root . '/app/Core/InstallerPreflight.php';
require $root . '/app/Core/AdminPasswordPolicy.php';
require $root . '/app/Database/Migrator.php';

$secureRequest = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
@ini_set('session.use_strict_mode', '1');
@ini_set('session.use_only_cookies', '1');
session_name('livecamforge_install');
session_set_cookie_params([
    'httponly' => true,
    'secure' => $secureRequest,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
if (!isset($_SESSION['installer_csrf']) || !is_string($_SESSION['installer_csrf'])) {
    $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['installer_csrf'];
$preflightChecks = InstallerPreflight::checks($root);
$preflightReady = InstallerPreflight::ready($preflightChecks);
$requestedLocale = (string) ($_POST['locale'] ?? $_GET['locale'] ?? 'en');
$translator = new Translator($root . '/languages', $requestedLocale, 'en');
$languages = $translator->available();

$values = [
    'locale' => $translator->locale(),
    'host' => $_POST['host'] ?? 'localhost',
    'port' => $_POST['port'] ?? '3306',
    'database' => $_POST['database'] ?? 'livecamforge',
    'user' => $_POST['user'] ?? 'root',
    'site_name' => $_POST['site_name'] ?? 'LiveCamForge',
    'base_url' => $_POST['base_url'] ?? '',
    'timezone' => $_POST['timezone'] ?? 'Europe/Rome',
    'demo_mode' => isset($_POST['demo_mode']) && $_POST['demo_mode'] === '1',
    'providers' => isset($_POST['providers']) && is_array($_POST['providers']) ? $_POST['providers'] : ['demo'],
    'wm' => $_POST['wm'] ?? '',
    'bongacams_campaign_id' => $_POST['bongacams_campaign_id'] ?? '',
    'cam4_affiliate_id' => $_POST['cam4_affiliate_id'] ?? '',
    'cam4_tune_network_id' => $_POST['cam4_tune_network_id'] ?? 'cam4com',
    'cam4_tune_api_key' => '',
    'bongacams_client_ip' => $_POST['bongacams_client_ip'] ?? '',
    'livejasmin_ps_id' => $_POST['livejasmin_ps_id'] ?? '',
    'livejasmin_access_key' => '',
    'stripchat_api_key' => '',
    'stripchat_user_id' => $_POST['stripchat_user_id'] ?? '',
    'crakrevenue_api_key' => '',
    'crakrevenue_token' => '',
    'admin_username' => $_POST['admin_username'] ?? 'admin',
];
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $installationLock = null;
    try {
        if (!$preflightReady) {
            throw new RuntimeException($translator->get('installer.error.requirements'));
        }
        $postedCsrf = (string) ($_POST['csrf_token'] ?? '');
        if ($postedCsrf === '' || !hash_equals($csrfToken, $postedCsrf)) {
            throw new RuntimeException($translator->get('installer.error.csrf'));
        }
        $installationLock = @fopen($root . '/storage/locks/install.lock', 'c+');
        if (!is_resource($installationLock) || !flock($installationLock, LOCK_EX | LOCK_NB)) {
            throw new RuntimeException($translator->get('installer.error.locked'));
        }
        if (is_file($root . '/config/local.php')) {
            header('Location: ../');
            exit;
        }

        $cam4TuneApiKey = trim((string) ($_POST['cam4_tune_api_key'] ?? ''));
        $liveJasminAccessKey = trim((string) ($_POST['livejasmin_access_key'] ?? ''));
        $stripchatApiKey = trim((string) ($_POST['stripchat_api_key'] ?? ''));
        $crakRevenueApiKey = trim((string) ($_POST['crakrevenue_api_key'] ?? ''));
        $crakRevenueToken = trim((string) ($_POST['crakrevenue_token'] ?? ''));
        foreach (['host', 'port', 'database', 'user', 'site_name', 'timezone'] as $required) {
            if (trim((string) $values[$required]) === '') {
                throw new RuntimeException($translator->get('installer.error.required'));
            }
        }
        $allowedProviders = [
            'demo', 'demo_alpha', 'demo_beta', 'chaturbate', 'bongacams', 'cam4', 'livejasmin', 'stripchat',
            'crakrevenue_mfc', 'crakrevenue_streamate', 'crakrevenue_chaturbate',
            'crakrevenue_awempire', 'crakrevenue_stripchat', 'crakrevenue_imlive',
            'crakrevenue_bongacash',
        ];
        $selectedProviders = array_values(array_unique(array_filter(
            array_map(static fn ($provider): string => strtolower(trim((string) $provider)), $values['providers']),
            static fn (string $provider): bool => in_array($provider, $allowedProviders, true)
        )));
        if ($selectedProviders === []) {
            throw new RuntimeException($translator->get('installer.error.providers'));
        }
        if ($values['demo_mode']) {
            $selectedProviders = ['demo_alpha', 'demo_beta'];
        }
        if (count($selectedProviders) > 1 && in_array('demo', $selectedProviders, true)) {
            $selectedProviders = array_values(array_filter(
                $selectedProviders,
                static fn (string $provider): bool => $provider !== 'demo'
            ));
        }
        $siteName = trim((string) $values['site_name']);
        if (strlen($siteName) > 80) {
            throw new RuntimeException($translator->get('installer.error.site'));
        }
        $baseUrl = rtrim(trim((string) $values['base_url']), '/');
        if ($baseUrl !== '') {
            $parts = parse_url($baseUrl);
            $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
            if (!is_array($parts) || !in_array($scheme, ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
                throw new RuntimeException($translator->get('installer.error.site'));
            }
        }
        $timezone = trim((string) $values['timezone']);
        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            throw new RuntimeException($translator->get('installer.error.timezone'));
        }
        $adminUsername = trim((string) $values['admin_username']);
        $adminPassword = (string) ($_POST['admin_password'] ?? '');
        $adminPasswordConfirmation = (string) ($_POST['admin_password_confirmation'] ?? '');
        if (strlen($adminUsername) < 3
            || strlen($adminPassword) < 12
            || !hash_equals($adminPassword, $adminPasswordConfirmation)
            || AdminPasswordPolicy::isWeak($adminPassword, $adminUsername, $siteName)
        ) {
            throw new RuntimeException($translator->get('installer.error.admin'));
        }
        if (in_array('chaturbate', $selectedProviders, true) && trim((string) $values['wm']) === '') {
            throw new RuntimeException($translator->get('installer.error.chaturbate'));
        }
        $bongaIp = trim((string) $values['bongacams_client_ip']);
        $validBongaIp = $bongaIp === '' || filter_var(
            $bongaIp,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
        if (in_array('bongacams', $selectedProviders, true)
            && ((int) $values['bongacams_campaign_id'] <= 0 || !$validBongaIp)
        ) {
            throw new RuntimeException($translator->get('installer.error.bongacams'));
        }
        if (in_array('cam4', $selectedProviders, true) && (int) $values['cam4_affiliate_id'] <= 0) {
            throw new RuntimeException($translator->get('installer.error.cam4'));
        }
        if (in_array('livejasmin', $selectedProviders, true)
            && (preg_match('/^[A-Za-z0-9_-]{1,100}$/', trim((string) $values['livejasmin_ps_id'])) !== 1
                || preg_match('/^[A-Za-z0-9_-]{16,200}$/', $liveJasminAccessKey) !== 1)
        ) {
            throw new RuntimeException($translator->get('installer.error.livejasmin'));
        }
        if (in_array('stripchat', $selectedProviders, true)
            && ($stripchatApiKey === '' || trim((string) $values['stripchat_user_id']) === '')
        ) {
            throw new RuntimeException($translator->get('installer.error.stripchat'));
        }
        if (count(array_filter($selectedProviders, static fn (string $provider): bool => str_starts_with($provider, 'crakrevenue_'))) > 0
            && ($crakRevenueApiKey === '' || $crakRevenueToken === '')
        ) {
            throw new RuntimeException($translator->get('installer.error.crakrevenue'));
        }

        $dsnServer = sprintf('mysql:host=%s;port=%d;charset=utf8mb4', $values['host'], (int) $values['port']);
        $pdo = new PDO($dsnServer, $values['user'], $_POST['password'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $database = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $values['database']);
        if ($database === '') {
            throw new RuntimeException($translator->get('installer.error.database_name'));
        }
        try {
            $pdo->exec("USE `{$database}`");
        } catch (PDOException) {
            // Shared-hosting accounts often cannot CREATE DATABASE. Existing databases
            // work without that privilege; creation is attempted only when USE fails.
            $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$database}`");
        }
        $schema = file_get_contents($root . '/database/schema.sql');
        if ($schema === false) {
            throw new RuntimeException($translator->get('installer.error.schema'));
        }
        foreach (array_filter(array_map('trim', explode(';', $schema))) as $statement) {
            $pdo->exec($statement);
        }
        Migrator::run($pdo, $root . '/database/migrations');
        $saveSetting = $pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $saveSetting->execute(['setting_key' => 'admin.username', 'setting_value' => $adminUsername]);
        $saveSetting->execute(['setting_key' => 'admin.password_hash', 'setting_value' => password_hash($adminPassword, PASSWORD_DEFAULT)]);
        $saveSetting->execute(['setting_key' => 'site.name', 'setting_value' => $siteName]);

        $primaryProvider = $selectedProviders[0];
        $runtimeConfiguration = [
            'locale' => array_key_exists($values['locale'], $languages) ? $values['locale'] : 'en',
            'fallback_locale' => 'en',
            'provider' => $primaryProvider,
            'providers' => ['enabled' => $selectedProviders],
            'catalog' => ['mode' => count($selectedProviders) > 1 ? 'combined' : 'single'],
        ];
        $saveSetting->execute([
            'setting_key' => 'runtime.configuration',
            'setting_value' => json_encode($runtimeConfiguration, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
        ]);
        $initialCatalogMode = count($selectedProviders) > 1 ? 'combined' : 'single';
        foreach ([
            'catalog.mode' => $initialCatalogMode,
            'catalog.primary_provider' => $primaryProvider,
            'catalog.show_provider_filter' => '1',
            'catalog.show_provider_badges' => '1',
        ] as $settingKey => $settingValue) {
            $saveSetting->execute(['setting_key' => $settingKey, 'setting_value' => $settingValue]);
        }
        $config = [
            'debug' => false,
            'demo_mode' => ['enabled' => (bool) $values['demo_mode']],
            'timezone' => $timezone,
            'seo' => ['base_url' => $baseUrl],
            'geo' => [
                'source' => 'auto',
                'test_country' => '',
                'test_region' => '',
            ],
            'database' => [
                'host' => $values['host'],
                'port' => (int) $values['port'],
                'name' => $database,
                'user' => $values['user'],
                'password' => $_POST['password'] ?? '',
            ],
            'chaturbate' => ['wm' => trim((string) $values['wm'])],
            'bongacams' => [
                'campaign_id' => (int) $values['bongacams_campaign_id'],
                'client_ip' => trim((string) $values['bongacams_client_ip']),
            ],
            'cam4' => [
                'affiliate_id' => (int) $values['cam4_affiliate_id'],
                'tune' => [
                    'network_id' => trim((string) $values['cam4_tune_network_id']) !== '' ? trim((string) $values['cam4_tune_network_id']) : 'cam4com',
                    'api_key' => $cam4TuneApiKey,
                ],
            ],
            'livejasmin' => [
                'ps_id' => trim((string) $values['livejasmin_ps_id']),
                'access_key' => $liveJasminAccessKey,
            ],
            'stripchat' => [
                'api_key' => $stripchatApiKey,
                'user_id' => trim((string) $values['stripchat_user_id']),
            ],
            'crakrevenue' => [
                'api_key' => $crakRevenueApiKey,
                'token' => $crakRevenueToken,
            ],
        ];
        $contents = "<?php\n\ndeclare(strict_types=1);\n\nreturn " . var_export($config, true) . ";\n";
        $configPath = $root . '/config/local.php';
        $temporaryConfigPath = $root . '/config/.local.php.' . bin2hex(random_bytes(8)) . '.tmp';
        if (file_put_contents($temporaryConfigPath, $contents, LOCK_EX) === false) {
            throw new RuntimeException($translator->get('installer.error.config'));
        }
        @chmod($temporaryConfigPath, 0600);
        $loadedConfig = require $temporaryConfigPath;
        if (!is_array($loadedConfig) || !isset($loadedConfig['database']) || !is_array($loadedConfig['database'])) {
            @unlink($temporaryConfigPath);
            throw new RuntimeException($translator->get('installer.error.config'));
        }
        if (!@rename($temporaryConfigPath, $configPath)) {
            @unlink($temporaryConfigPath);
            throw new RuntimeException($translator->get('installer.error.config'));
        }
        @chmod($configPath, 0600);

        $_SESSION['installer_csrf'] = bin2hex(random_bytes(32));
        header('Location: ../?installed=1');
        exit;
    } catch (PDOException $exception) {
        error_log('[LiveCamForge installer] Database operation failed: ' . $exception->getCode());
        $message = $translator->get('installer.error.database');
    } catch (RuntimeException $exception) {
        $message = $exception->getMessage();
    } catch (Throwable $exception) {
        error_log('[LiveCamForge installer] Unexpected installation failure: ' . get_class($exception));
        $message = $translator->get('installer.error.unexpected');
    } finally {
        if (is_resource($installationLock)) {
            @flock($installationLock, LOCK_UN);
            @fclose($installationLock);
        }
    }
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
$timezoneGroups = [];
$timezoneIdentifiers = DateTimeZone::listIdentifiers();
if (!in_array('UTC', $timezoneIdentifiers, true)) {
    array_unshift($timezoneIdentifiers, 'UTC');
}
foreach ($timezoneIdentifiers as $timezoneIdentifier) {
    $separator = strpos($timezoneIdentifier, '/');
    $group = $separator === false ? $translator->get('installer.timezone_group.other') : substr($timezoneIdentifier, 0, $separator);
    $timezoneGroups[$group][] = $timezoneIdentifier;
}
ksort($timezoneGroups);

?>
<!doctype html>
<html lang="<?= e($translator->locale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($translator->get('installer.title')) ?> · LiveCamForge</title>
    <link rel="stylesheet" href="../public/assets/app.css">
</head>
<body>
<main class="installer">
    <p class="eyebrow">LiveCamForge 0.27.12</p>
    <h1><?= e($translator->get('installer.heading')) ?></h1>
    <p class="intro"><?= e($translator->get('installer.intro')) ?></p>
    <section class="panel">
        <h2><?= e($translator->get('installer.requirements_heading')) ?></h2>
        <ul>
            <?php foreach ($preflightChecks as $check): ?>
                <li><?= $check['ok'] ? '✓' : '✗' ?> <?= e($translator->get('installer.requirement.' . $check['key'])) ?> — <?= e($check['detail']) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php if (!$preflightReady): ?><div class="alert error"><?= e($translator->get('installer.requirements_blocked')) ?></div><?php endif; ?>
    </section>
    <?php if ($message): ?><div class="alert error"><?= e($message) ?></div><?php endif; ?>
    <form method="post" class="panel form-grid">
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
        <label class="wide"><?= e($translator->get('installer.language')) ?>
            <select name="locale">
                <?php foreach ($languages as $language): ?>
                    <option value="<?= e($language['code']) ?>" <?= $values['locale'] === $language['code'] ? 'selected' : '' ?>><?= e($language['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label><?= e($translator->get('installer.db_host')) ?><input name="host" value="<?= e($values['host']) ?>" required></label>
        <label><?= e($translator->get('installer.db_port')) ?><input name="port" type="number" value="<?= e($values['port']) ?>" required></label>
        <label><?= e($translator->get('installer.db_name')) ?><input name="database" value="<?= e($values['database']) ?>" pattern="[A-Za-z0-9_]+" required></label>
        <label><?= e($translator->get('installer.db_user')) ?><input name="user" value="<?= e($values['user']) ?>" required></label>
        <label><?= e($translator->get('installer.db_password')) ?><input name="password" type="password" autocomplete="new-password"><small><?= e($translator->get('installer.password_hint')) ?></small></label>
        <label><?= e($translator->get('installer.site_name')) ?><input name="site_name" value="<?= e($values['site_name']) ?>" maxlength="80" required></label>
        <label><?= e($translator->get('installer.timezone')) ?>
            <select name="timezone" required>
                <?php foreach ($timezoneGroups as $timezoneGroup => $timezoneOptions): ?>
                    <optgroup label="<?= e($timezoneGroup) ?>">
                        <?php foreach ($timezoneOptions as $timezoneOption): ?>
                            <option value="<?= e($timezoneOption) ?>" <?= $values['timezone'] === $timezoneOption ? 'selected' : '' ?>><?= e($timezoneOption) ?></option>
                        <?php endforeach; ?>
                    </optgroup>
                <?php endforeach; ?>
            </select>
            <small><?= e($translator->get('installer.timezone_hint')) ?></small>
        </label>
        <label class="wide"><?= e($translator->get('installer.base_url')) ?><input name="base_url" type="url" value="<?= e($values['base_url']) ?>" placeholder="https://example.com/livecamforge"><small><?= e($translator->get('installer.base_url_hint')) ?></small></label>
        <label class="wide installer-check demo-mode-choice"><input type="checkbox" name="demo_mode" value="1" data-demo-mode-choice <?= $values['demo_mode'] ? 'checked' : '' ?>> <span><?= e($translator->get('installer.demo_mode')) ?></span><small><?= e($translator->get('installer.demo_mode_hint')) ?></small></label>
        <fieldset class="wide installer-providers" data-provider-fieldset>
            <legend><?= e($translator->get('installer.providers')) ?></legend>
            <p class="field-hint"><?= e($translator->get('installer.providers_hint')) ?></p>
            <p class="field-hint demo-provider-note" data-demo-provider-note hidden><?= e($translator->get('installer.demo_mode_providers_hint')) ?></p>
            <?php $providerOptions = [
                'demo' => $translator->get('installer.demo'), 'chaturbate' => 'Chaturbate', 'bongacams' => 'BongaCams',
                'cam4' => 'CAM4', 'livejasmin' => 'LiveJasmin', 'stripchat' => 'Stripchat',
                'crakrevenue_mfc' => 'MyFreeCams via CrakRevenue', 'crakrevenue_streamate' => 'Jerkmate via CrakRevenue',
                'crakrevenue_chaturbate' => 'Chaturbate via CrakRevenue', 'crakrevenue_awempire' => 'LiveJasmin via CrakRevenue',
                'crakrevenue_stripchat' => 'Stripchat via CrakRevenue', 'crakrevenue_imlive' => 'ImLive via CrakRevenue',
                'crakrevenue_bongacash' => 'BongaCams via CrakRevenue',
            ]; ?>
            <div class="installer-provider-grid">
                <?php foreach ($providerOptions as $providerKey => $providerLabel): ?>
                    <label class="installer-check"><input type="checkbox" name="providers[]" data-provider-choice value="<?= e($providerKey) ?>" <?= in_array($providerKey, $values['providers'], true) ? 'checked' : '' ?>> <span><?= e($providerLabel) ?></span></label>
                <?php endforeach; ?>
            </div>
        </fieldset>
        <div class="wide installer-provider-configs">
            <h2><?= e($translator->get('installer.provider_configuration')) ?></h2>
            <p class="field-hint"><?= e($translator->get('installer.provider_configuration_hint')) ?></p>

            <fieldset class="installer-provider-config" data-provider-config="chaturbate">
                <legend>Chaturbate</legend>
                <label><?= e($translator->get('installer.affiliate_code')) ?><input name="wm" value="<?= e($values['wm']) ?>"><small><?= e($translator->get('installer.affiliate_hint')) ?></small></label>
            </fieldset>

            <fieldset class="installer-provider-config" data-provider-config="bongacams">
                <legend>BongaCams</legend>
                <div class="field-grid">
                    <label><?= e($translator->get('installer.bongacams_campaign_id')) ?><input name="bongacams_campaign_id" type="number" min="0" value="<?= e($values['bongacams_campaign_id']) ?>"></label>
                    <label><?= e($translator->get('installer.bongacams_client_ip')) ?><input name="bongacams_client_ip" inputmode="decimal" value="<?= e($values['bongacams_client_ip']) ?>"><small><?= e($translator->get('installer.bongacams_hint')) ?></small></label>
                </div>
            </fieldset>

            <fieldset class="installer-provider-config" data-provider-config="cam4">
                <legend>CAM4</legend>
                <div class="field-grid">
                    <label><?= e($translator->get('installer.cam4_affiliate_id')) ?><input name="cam4_affiliate_id" type="number" min="0" value="<?= e($values['cam4_affiliate_id']) ?>"></label>
                    <label><?= e($translator->get('installer.cam4_tune_network_id')) ?><input name="cam4_tune_network_id" value="<?= e($values['cam4_tune_network_id']) ?>"><small><?= e($translator->get('installer.cam4_hint')) ?></small></label>
                    <label><?= e($translator->get('installer.cam4_tune_api_key')) ?><input name="cam4_tune_api_key" type="password" value="<?= e($values['cam4_tune_api_key']) ?>" autocomplete="new-password"></label>
                </div>
            </fieldset>

            <fieldset class="installer-provider-config" data-provider-config="livejasmin">
                <legend>LiveJasmin</legend>
                <div class="field-grid">
                    <label><?= e($translator->get('installer.livejasmin_ps_id')) ?><input name="livejasmin_ps_id" value="<?= e($values['livejasmin_ps_id']) ?>"></label>
                    <label><?= e($translator->get('installer.livejasmin_access_key')) ?><input name="livejasmin_access_key" type="password" value="<?= e($values['livejasmin_access_key']) ?>" autocomplete="new-password"><small><?= e($translator->get('installer.livejasmin_hint')) ?></small></label>
                </div>
            </fieldset>

            <fieldset class="installer-provider-config" data-provider-config="stripchat">
                <legend>Stripchat</legend>
                <div class="field-grid">
                    <label><?= e($translator->get('installer.stripchat_user_id')) ?><input name="stripchat_user_id" value="<?= e($values['stripchat_user_id']) ?>"></label>
                    <label><?= e($translator->get('installer.stripchat_api_key')) ?><input name="stripchat_api_key" type="password" value="<?= e($values['stripchat_api_key']) ?>" autocomplete="new-password"></label>
                </div>
            </fieldset>

            <fieldset class="installer-provider-config" data-provider-config="crakrevenue">
                <legend>CrakRevenue</legend>
                <div class="field-grid">
                    <label><?= e($translator->get('installer.crakrevenue_api_key')) ?><input name="crakrevenue_api_key" type="password" value="<?= e($values['crakrevenue_api_key']) ?>" autocomplete="new-password"></label>
                    <label><?= e($translator->get('installer.crakrevenue_token')) ?><input name="crakrevenue_token" type="password" value="<?= e($values['crakrevenue_token']) ?>" autocomplete="new-password"><small><?= e($translator->get('installer.crakrevenue_hint')) ?></small></label>
                </div>
            </fieldset>
        </div>
        <label class="wide"><?= e($translator->get('installer.admin_username')) ?><input name="admin_username" value="<?= e($values['admin_username']) ?>" minlength="3" autocomplete="username" required></label>
        <label><?= e($translator->get('installer.admin_password')) ?>
            <input id="installer-admin-password" name="admin_password" type="password" minlength="12" autocomplete="new-password" required>
            <small><?= e($translator->get('installer.admin_password_hint')) ?></small>
            <span class="password-strength" aria-live="polite">
                <span class="password-strength-bar"><span id="password-strength-fill"></span></span>
                <span id="password-strength-label"><?= e($translator->get('installer.password_strength.empty')) ?></span>
            </span>
        </label>
        <label><?= e($translator->get('installer.admin_password_confirmation')) ?><input name="admin_password_confirmation" type="password" minlength="12" autocomplete="new-password" required></label>
        <button class="button wide" type="submit" <?= !$preflightReady ? 'disabled aria-disabled="true"' : '' ?>><?= e($translator->get('installer.submit')) ?></button>
    </form>
</main>
<script>
(() => {
    const choices = [...document.querySelectorAll('[data-provider-choice]')];
    const configSections = [...document.querySelectorAll('[data-provider-config]')];
    const demoModeChoice = document.querySelector('[data-demo-mode-choice]');
    const providerFieldset = document.querySelector('[data-provider-fieldset]');
    const demoProviderNote = document.querySelector('[data-demo-provider-note]');

    const refreshProviderConfigs = () => {
        const demoMode = demoModeChoice?.checked === true;

        choices.forEach((choice) => {
            if (demoMode) {
                choice.checked = false;
            }
            choice.disabled = demoMode;
        });
        if (providerFieldset) {
            providerFieldset.classList.toggle('demo-mode-locked', demoMode);
        }
        if (demoProviderNote) {
            demoProviderNote.hidden = !demoMode;
        }

        const selected = demoMode
            ? []
            : choices.filter((choice) => choice.checked).map((choice) => choice.value);

        configSections.forEach((section) => {
            const key = section.dataset.providerConfig;
            const visible = !demoMode && (key === 'crakrevenue'
                ? selected.some((provider) => provider.startsWith('crakrevenue_'))
                : selected.includes(key));
            section.hidden = !visible;
        });
    };

    choices.forEach((choice) => choice.addEventListener('change', refreshProviderConfigs));
    demoModeChoice?.addEventListener('change', refreshProviderConfigs);
    refreshProviderConfigs();

    const password = document.getElementById('installer-admin-password');
    const fill = document.getElementById('password-strength-fill');
    const label = document.getElementById('password-strength-label');
    const labels = <?= json_encode([
        $translator->get('installer.password_strength.empty'),
        $translator->get('installer.password_strength.weak'),
        $translator->get('installer.password_strength.fair'),
        $translator->get('installer.password_strength.good'),
        $translator->get('installer.password_strength.strong'),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const updateStrength = () => {
        const value = password.value;
        if (!value) { fill.style.width = '0%'; label.textContent = labels[0]; return; }
        let score = 0;
        if (value.length >= 12) score++;
        if (value.length >= 16) score++;
        const varieties = [/[a-z]/, /[A-Z]/, /\d/, /[^A-Za-z0-9]/].filter((pattern) => pattern.test(value)).length;
        if (varieties >= 2) score++;
        if (varieties >= 3) score++;
        score = Math.max(1, Math.min(4, score));
        fill.style.width = `${score * 25}%`;
        fill.dataset.score = String(score);
        label.textContent = labels[score];
    };
    password.addEventListener('input', updateStrength);
    updateStrength();
})();
</script>
</body>
</html>
