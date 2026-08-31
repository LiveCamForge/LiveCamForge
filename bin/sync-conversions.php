<?php

declare(strict_types=1);

use LiveCamForge\Database\Connection;
use LiveCamForge\Database\Migrator;
use LiveCamForge\Repositories\ClickRepository;
use LiveCamForge\Repositories\ConversionRepository;
use LiveCamForge\Repositories\ConversionSyncRunRepository;
use LiveCamForge\Services\Cam4ConversionSync;

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
    $config = require $root . '/app/bootstrap.php';
    $pdo = Connection::make($config);
    Migrator::run($pdo, $root . '/database/migrations');
    $provider = strtolower(trim((string) ($argv[1] ?? 'cam4')));
    if ($provider !== 'cam4') {
        throw new RuntimeException('Only CAM4 conversion polling is supported by this command.');
    }
    $start = isset($argv[2]) && trim((string) $argv[2]) !== '' ? trim((string) $argv[2]) : null;
    $end = isset($argv[3]) && trim((string) $argv[3]) !== '' ? trim((string) $argv[3]) : null;
    $conversionRuns = new ConversionSyncRunRepository($pdo);
    $conversionRuns->interruptStaleRunning(60);
    $conversionRuns->prune(30);
    $runId = $conversionRuns->start($provider, 'cron');
    try {
        $result = (new Cam4ConversionSync(
        $config,
        new ClickRepository($pdo),
        new ConversionRepository($pdo),
        ))->run($start, $end);
        $conversionRuns->succeed($runId, $result);
        $conversionRuns->prune(30);
    } catch (Throwable $syncException) {
        $conversionRuns->fail($runId, $syncException->getMessage());
        throw $syncException;
    }
    fwrite(STDOUT, sprintf(
        "[%s] CAM4 conversions: %d received, %d inserted, %d duplicates, %d attributed.\n",
        date('c'), $result['received'], $result['inserted'], $result['duplicates'], $result['attributed']
    ));
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, sprintf("[%s] Conversion sync failed: %s\n", date('c'), $exception->getMessage()));
    exit(1);
}
