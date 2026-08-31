<?php

declare(strict_types=1);

use LiveCamForge\Core\OperationalSettings;
use LiveCamForge\Core\DemoMode;
use LiveCamForge\Core\SecurityHeaders;
use LiveCamForge\Database\Connection;
use LiveCamForge\Database\Migrator;
use LiveCamForge\Providers\ProviderFactory;
use LiveCamForge\Repositories\ClickRepository;
use LiveCamForge\Repositories\SettingsRepository;

$root = __DIR__;
if (!is_file($root . '/config/local.php')) {
    header('Location: install/');
    exit;
}

$baseConfig = require $root . '/app/bootstrap.php';
SecurityHeaders::sendBase();
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$pdo = Connection::make($baseConfig);
Migrator::run($pdo, $root . '/database/migrations');
$settingsRepository = new SettingsRepository($pdo);
$languageDiscovery = new \LiveCamForge\Core\Translator($root . '/languages', 'en', 'en');
$operationalSettings = new OperationalSettings(
    $settingsRepository,
    $baseConfig,
    ProviderFactory::availableNames(),
    array_keys($languageDiscovery->available())
);
$config = $operationalSettings->effectiveConfig();
$demoMode = DemoMode::enabled($config);
$recruitment = $config->get('recruitment.models', []);
$recruitment = is_array($recruitment) ? $recruitment : [];

$provider = strtolower(trim((string) ($_GET['recruit_provider'] ?? '')));
if ($provider === '' || !preg_match('/^[a-z0-9_-]{1,64}$/', $provider)) {
    http_response_code(404);
    exit('Recruitment destination not found.');
}

$providers = is_array($recruitment['providers'] ?? null) ? $recruitment['providers'] : [];
$entry = is_array($providers[$provider] ?? null) ? $providers[$provider] : null;
$destination = trim((string) ($entry['url'] ?? ''));
if ($demoMode) {
    if (!DemoMode::isDemoProvider($provider)) {
        http_response_code(404);
        exit('Recruitment destination not found.');
    }
    $destination = DemoMode::modelRecruitmentUrl();
}

if (!is_array($entry)
    || !($entry['enabled'] ?? false)
    || !filter_var($destination, FILTER_VALIDATE_URL)
    || !str_starts_with(strtolower($destination), 'https://')) {
    http_response_code(404);
    exit('Recruitment destination not found.');
}

$track = (string) $config->get($provider . '.postback.track', 'livecamforge');
$clickRepository = new ClickRepository($pdo);
$clickRepository->record([
    'provider' => $provider,
    'provider_id' => 'model-recruitment',
    'username' => 'model-recruitment',
], $track, 'click', 'model-recruitment');

header('Location: ' . $destination, true, 302);
exit;
