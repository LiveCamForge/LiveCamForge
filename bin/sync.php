<?php

declare(strict_types=1);

use LiveCamForge\Database\Connection;
use LiveCamForge\Database\Migrator;
use LiveCamForge\Core\OperationalSettings;
use LiveCamForge\Core\Translator;
use LiveCamForge\Core\PerformerTypes;
use LiveCamForge\Providers\ProviderFactory;
use LiveCamForge\Repositories\PerformerRepository;
use LiveCamForge\Repositories\SyncRunRepository;
use LiveCamForge\Repositories\SettingsRepository;
use LiveCamForge\Services\SyncProviders;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
if (!is_file($root . '/config/local.php')) {
    fwrite(STDERR, "LiveCamForge is not installed.\n");
    exit(2);
}

try {
    $baseConfig = require $root . '/app/bootstrap.php';
    $pdo = Connection::make($baseConfig);
    Migrator::run($pdo, $root . '/database/migrations');
    $settings = new SettingsRepository($pdo);
    $languages = (new Translator($root . '/languages', 'en', 'en'))->available();
    $config = (new OperationalSettings(
        $settings,
        $baseConfig,
        ProviderFactory::availableNames(),
        array_keys($languages)
    ))->effectiveConfig();
    $enabledProviders = ProviderFactory::enabledNames($config);
    $arguments = array_values(array_slice($argv, 1));
    $profile = in_array('--profile', $arguments, true);
    $dbBatchSize = 200;
    foreach ($arguments as $arg) {
        if (str_starts_with($arg, '--db-batch=')) {
            $requestedBatchSize = (int) substr($arg, strlen('--db-batch='));
            $dbBatchSize = max(25, min(250, $requestedBatchSize));
            if ($requestedBatchSize !== $dbBatchSize) {
                fwrite(STDERR, sprintf(
                    "Requested --db-batch=%d adjusted to %d (supported diagnostic range: 25..250; default: 200).\n",
                    $requestedBatchSize,
                    $dbBatchSize
                ));
            }
        }
    }
    $arguments = array_values(array_filter($arguments, static fn (string $arg): bool => $arg !== '--profile' && !str_starts_with($arg, '--db-batch=')));
    $requested = strtolower(trim((string) ($arguments[0] ?? '--all')));
    $targets = in_array($requested, ['', '--all', 'all'], true) ? $enabledProviders : [$requested];
    if (array_diff($targets, $enabledProviders) !== []) {
        fwrite(STDERR, 'Provider not enabled. Available providers: ' . implode(', ', $enabledProviders) . "\n");
        exit(2);
    }

    $runs = new SyncRunRepository($pdo);
    $runs->interruptStaleRunning(30);
    $runs->prune((int) $config->get('sync.history_days', 7));
    $results = (new SyncProviders(
        $config,
        $root,
        new PerformerRepository($pdo, PerformerTypes::fromConfig($config)),
        $runs,
        $pdo
    ))->run($targets, 'cron', $profile, $dbBatchSize);
    $failed = false;
    foreach ($results as $name => $result) {
        if ($result['success']) {
            fwrite(STDOUT, sprintf("[%s] %s: %d performers imported.\n", date('c'), $name, $result['count']));
            if ($profile && isset($result['profile']) && is_array($result['profile'])) {
                $timings = is_array($result['profile']['timings'] ?? null) ? $result['profile']['timings'] : [];
                $meta = is_array($result['profile']['meta'] ?? null) ? $result['profile']['meta'] : [];
                fwrite(STDOUT, "  Sync profile:\n");
                foreach ($timings as $metric => $seconds) {
                    fwrite(STDOUT, sprintf("    %-28s %8.3f s\n", $metric, (float) $seconds));
                }
                if ($meta !== []) {
                    fwrite(STDOUT, "  Counters:\n");
                    foreach ($meta as $metric => $value) {
                        $display = $metric === 'remote_bytes' ? number_format(((int) $value) / 1048576, 2, '.', '') . ' MiB' : (string) $value;
                        fwrite(STDOUT, sprintf("    %-28s %s\n", $metric, $display));
                    }
                }
            }
        } else {
            $failed = true;
            fwrite(STDERR, sprintf("[%s] %s failed: %s\n", date('c'), $name, $result['error']));
        }
    }
    $runs->prune((int) $config->get('sync.history_days', 7));
    exit($failed ? 1 : 0);
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("[%s] Synchronization failed: %s\n", date('c'), $exception->getMessage()));
    exit(1);
}
