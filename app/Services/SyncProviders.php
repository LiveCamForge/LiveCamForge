<?php

declare(strict_types=1);

namespace LiveCamForge\Services;

use LiveCamForge\Core\Config;
use LiveCamForge\Core\ProviderPolicy;
use LiveCamForge\Core\PerformerTypes;
use LiveCamForge\Core\SyncPerformanceProfiler;
use LiveCamForge\Providers\ProviderFactory;
use LiveCamForge\Repositories\PerformerRepository;
use LiveCamForge\Repositories\SyncRunRepository;
use PDO;

final class SyncProviders
{
    public function __construct(
        private Config $config,
        private string $root,
        private PerformerRepository $performers,
        private SyncRunRepository $runs,
        private PDO $pdo,
    ) {
    }

    /** @return array<string, array{success: bool, count: int, error: string, profile?: array}> */
    public function run(array $providerNames, string $trigger, bool $profile = false, int $dbBatchSize = 200): array
    {
        $results = [];
        foreach (array_values(array_unique($providerNames)) as $providerName) {
            SyncPerformanceProfiler::enable($profile);
            $providerName = strtolower(trim((string) $providerName));
            if ($providerName === '' || !ProviderFactory::isEnabled($providerName, $this->config)) {
                $results[$providerName] = ['success' => false, 'count' => 0, 'error' => 'Provider is not enabled.'];
                continue;
            }

            try {
                $provider = ProviderFactory::make($providerName, $this->config, $this->root);
                $count = (new SyncPerformers(
                    $provider,
                    $this->performers,
                    $this->pdo,
                    $this->runs,
                    $this->root . '/storage/locks/sync-' . preg_replace('/[^a-z0-9_-]/i', '', $providerName) . '.lock',
                    (bool) $this->config->get('sync.allow_empty', false),
                    ProviderPolicy::for($this->config, $providerName),
                    $this->root . '/storage/cache/media',
                    PerformerTypes::fromConfig($this->config),
                    $dbBatchSize
                ))->run($trigger);
                SyncPerformanceProfiler::start('cache.invalidate');
                CatalogCountCache::invalidate($this->root);
                SyncPerformanceProfiler::stop('cache.invalidate');
                $results[$providerName] = ['success' => true, 'count' => $count, 'error' => ''];
                if ($profile) {
                    $results[$providerName]['profile'] = SyncPerformanceProfiler::report();
                }
            } catch (\Throwable $exception) {
                $results[$providerName] = ['success' => false, 'count' => 0, 'error' => $exception->getMessage()];
                if ($profile) {
                    $results[$providerName]['profile'] = SyncPerformanceProfiler::report();
                }
            }
        }

        return $results;
    }
}
