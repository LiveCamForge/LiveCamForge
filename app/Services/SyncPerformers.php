<?php

declare(strict_types=1);

namespace LiveCamForge\Services;

use LiveCamForge\Providers\ProviderInterface;
use LiveCamForge\Providers\DeletedPerformersProviderInterface;
use LiveCamForge\Core\ProviderPolicy;
use LiveCamForge\Core\PerformerTypes;
use LiveCamForge\Core\SyncPerformanceProfiler;
use LiveCamForge\Repositories\PerformerRepository;
use LiveCamForge\Repositories\SyncRunRepository;
use PDO;
use RuntimeException;

final class SyncPerformers
{
    public function __construct(
        private ProviderInterface $provider,
        private PerformerRepository $repository,
        private PDO $pdo,
        private ?SyncRunRepository $runs = null,
        private ?string $lockPath = null,
        private bool $allowEmpty = false,
        private ?ProviderPolicy $policy = null,
        private ?string $mediaCachePath = null,
        private array $allowedPerformerTypes = PerformerTypes::VALUES,
        private int $dbBatchSize = 200,
    ) {
    }

    public function run(string $trigger = 'manual'): int
    {
        $lock = $this->acquireLock();
        $runId = $this->runs?->start($this->provider->name(), $trigger);

        try {
            SyncPerformanceProfiler::start('sync.total');
            $syncSeenSince = $this->repository->databaseTimestamp();
            SyncPerformanceProfiler::start('provider.fetch_total');
            $fetchedPerformers = $this->provider->fetch();
            SyncPerformanceProfiler::stop('provider.fetch_total');
            SyncPerformanceProfiler::meta('rows_fetched', count($fetchedPerformers));
            if ($fetchedPerformers === [] && !$this->allowEmpty) {
                throw new RuntimeException('The provider returned no performers; the existing catalog was preserved.');
            }
            SyncPerformanceProfiler::start('sync.filter_types');
            $allowedTypes = PerformerTypes::normalize($this->allowedPerformerTypes);
            $performers = array_values(array_filter(
                $fetchedPerformers,
                static fn ($performer): bool => PerformerTypes::accepts($performer->gender, $allowedTypes)
            ));
            // The normalized pipeline creates immutable Performer copies for ranking. Release the
            // provider fetch array before that work so large feeds (notably Stripchat) do not keep
            // an unnecessary second reference set alive throughout the database phase.
            unset($fetchedPerformers);
            SyncPerformanceProfiler::stop('sync.filter_types');
            SyncPerformanceProfiler::meta('rows_after_filter', count($performers));
            SyncPerformanceProfiler::start('sync.normalize_popularity');
            $performers = $this->normalizePopularity($performers);
            SyncPerformanceProfiler::stop('sync.normalize_popularity');
            SyncPerformanceProfiler::start('sync.apply_sort_scores');
            $performers = $this->applySortScores($performers);
            SyncPerformanceProfiler::stop('sync.apply_sort_scores');
            $this->pdo->beginTransaction();
            $staleMediaUrls = [];
            try {
                SyncPerformanceProfiler::start('db.upsert_all');
                $this->repository->upsertMany($performers, $this->dbBatchSize);
                SyncPerformanceProfiler::stop('db.upsert_all');
                SyncPerformanceProfiler::meta('rows_upserted', count($performers));
                SyncPerformanceProfiler::meta('db_upsert_mode', 'batch_' . $this->dbBatchSize);
                SyncPerformanceProfiler::start('db.mark_offline');
                $this->repository->markProviderOfflineBefore($this->provider->name(), $syncSeenSince);
                SyncPerformanceProfiler::stop('db.mark_offline');
                if ($this->provider instanceof DeletedPerformersProviderInterface) {
                    SyncPerformanceProfiler::start('db.delete_provider_removed');
                    $this->repository->deleteByUsernames(
                        $this->provider->name(),
                        $this->provider->deletedUsernames()
                    );
                    SyncPerformanceProfiler::stop('db.delete_provider_removed');
                }
                if ($this->policy !== null) {
                    // When image caching is disabled there is no per-sync disk cache to maintain.
                    // Older releases scanned every provider media URL and hashed/unlinked them on
                    // every sync, which was especially expensive for large Stripchat catalogs.
                    // Cached files are now purged only when stale performer rows are actually
                    // removed and only if image caching is enabled.
                    $retentionDays = !$this->policy->offlineRetention
                        ? null
                        : ($this->policy->offlineRetentionDays > 0 ? $this->policy->offlineRetentionDays : false);
                    if ($retentionDays !== false) {
                        if ($this->policy->cacheImages) {
                            SyncPerformanceProfiler::start('db.stale_lookup');
                            $staleMediaUrls = $this->repository->staleMediaUrls($this->provider->name(), $retentionDays);
                            SyncPerformanceProfiler::stop('db.stale_lookup');
                        } else {
                            SyncPerformanceProfiler::meta('media_cache_maintenance', 'skipped_disabled');
                        }
                        SyncPerformanceProfiler::start('db.delete_stale');
                        $this->repository->deleteStale($this->provider->name(), $retentionDays);
                        SyncPerformanceProfiler::stop('db.delete_stale');
                    }
                }
                SyncPerformanceProfiler::start('db.commit');
                $this->pdo->commit();
                SyncPerformanceProfiler::stop('db.commit');
            } catch (\Throwable $exception) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $exception;
            }
            if ($staleMediaUrls !== [] && $this->mediaCachePath !== null) {
                SyncPerformanceProfiler::start('media.purge');
                MediaProxy::purgeUrls($staleMediaUrls, $this->mediaCachePath);
                SyncPerformanceProfiler::stop('media.purge');
            }

            $count = count($performers);
            if ($runId !== null) {
                SyncPerformanceProfiler::start('sync.history_write');
                $this->runs?->succeed($runId, $count);
                SyncPerformanceProfiler::stop('sync.history_write');
            }
            SyncPerformanceProfiler::stop('sync.total');

            return $count;
        } catch (\Throwable $exception) {
            if ($runId !== null) {
                $this->runs?->fail($runId, $exception->getMessage());
            }
            throw $exception;
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }

    /** @param list<\LiveCamForge\Models\Performer> $performers */
    private function normalizePopularity(array $performers): array
    {
        if (!$this->provider->capabilities()->viewers) {
            return $performers;
        }

        $ranked = array_values(array_filter(
            $performers,
            static fn ($performer): bool => $performer->viewers !== null
        ));
        usort($ranked, static fn ($left, $right): int => $right->viewers <=> $left->viewers);
        $count = count($ranked);
        if ($count === 0) {
            return $performers;
        }

        $scores = [];
        $position = 0;
        while ($position < $count) {
            $end = $position;
            while ($end + 1 < $count && $ranked[$end + 1]->viewers === $ranked[$position]->viewers) {
                $end++;
            }
            $score = $count === 1 ? 1.0 : 1.0 - ($position / ($count - 1));
            for ($index = $position; $index <= $end; $index++) {
                $scores[spl_object_id($ranked[$index])] = $score;
            }
            $position = $end + 1;
        }

        return array_map(
            static fn ($performer) => $performer->withPopularityScore(
                $scores[spl_object_id($performer)] ?? null
            ),
            $performers
        );
    }

    /** @param list<\LiveCamForge\Models\Performer> $performers */
    private function applySortScores(array $performers): array
    {
        $hasReliableRoomStatus = $this->provider->capabilities()->roomStatus;

        return array_map(
            static fn ($performer) => $performer->withSortScores(
                !$hasReliableRoomStatus || $performer->roomStatus === 'public'
            ),
            $performers
        );
    }

    /** @return resource|null */
    private function acquireLock()
    {
        if ($this->lockPath === null || $this->lockPath === '') {
            return null;
        }

        $directory = dirname($this->lockPath);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the synchronization lock directory.');
        }
        $handle = fopen($this->lockPath, 'c+');
        if ($handle === false || !flock($handle, LOCK_EX | LOCK_NB)) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            throw new RuntimeException('A synchronization is already running.');
        }

        ftruncate($handle, 0);
        fwrite($handle, (string) getmypid());
        fflush($handle);

        return $handle;
    }
}
