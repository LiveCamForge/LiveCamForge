<?php

declare(strict_types=1);

namespace LiveCamForge\Repositories;

use LiveCamForge\Core\NewnessStrategy;
use LiveCamForge\Core\PerformerTypes;
use LiveCamForge\Core\PerformanceProfiler;
use LiveCamForge\Core\SyncPerformanceProfiler;
use LiveCamForge\Models\Performer;
use PDO;

final class PerformerRepository
{
    private array $allowedPerformerTypes;

    public function __construct(private PDO $pdo, array $allowedPerformerTypes = PerformerTypes::VALUES)
    {
        $this->allowedPerformerTypes = PerformerTypes::normalize($allowedPerformerTypes);
    }

    public function upsert(Performer $performer): void
    {
        $sql = <<<'SQL'
            INSERT INTO performers
                (provider, provider_id, username, display_name, gender, age, country_code, image_url, preview_url, embed_url, room_status, room_url, viewers, popularity_score, watch_sort_score, provider_sort_score, tags_json, provider_is_new, has_geo_blocks, structural_hash, is_online, last_seen_at)
            VALUES
                (:provider, :provider_id, :username, :display_name, :gender, :age, :country_code, :image_url, :preview_url, :embed_url, :room_status, :room_url, :viewers, :popularity_score, :watch_sort_score, :provider_sort_score, :tags_json, :provider_is_new, :has_geo_blocks, :structural_hash, :is_online, NOW())
            ON DUPLICATE KEY UPDATE
                username = VALUES(username), display_name = VALUES(display_name), gender = VALUES(gender),
                age = VALUES(age), country_code = VALUES(country_code), image_url = VALUES(image_url), preview_url = VALUES(preview_url), embed_url = VALUES(embed_url),
                room_status = VALUES(room_status), room_url = VALUES(room_url), viewers = VALUES(viewers), popularity_score = VALUES(popularity_score),
                watch_sort_score = VALUES(watch_sort_score), provider_sort_score = VALUES(provider_sort_score), tags_json = VALUES(tags_json),
                provider_is_new = VALUES(provider_is_new), has_geo_blocks = VALUES(has_geo_blocks), structural_hash = VALUES(structural_hash),
                is_online = VALUES(is_online), last_seen_at = NOW()
            SQL;

        $data = $performer->toArray();
        $data['tags_json'] = json_encode($performer->tags, JSON_UNESCAPED_SLASHES);
        $data['provider_is_new'] = $performer->providerNew === null ? null : ($performer->providerNew ? 1 : 0);
        $data['has_geo_blocks'] = $performer->geoBlocks === [] ? 0 : 1;
        $data['structural_hash'] = $this->structuralHash($performer);
        $data['is_online'] = $performer->online ? 1 : 0;
        unset($data['tags'], $data['online'], $data['geo_blocks']);
        $this->pdo->prepare($sql)->execute($data);
        $this->replaceGeoBlocks($performer);
    }


    /**
     * Batch upsert used by provider synchronization.
     *
     * Preparing/executing one INSERT and geo-block maintenance statement per performer
     * dominated large-provider syncs. This path keeps the same data semantics while
     * reducing round-trips to a bounded number of statements per chunk.
     *
     * @param list<Performer> $performers
     */
    public function upsertMany(array $performers, int $chunkSize = 200): void
    {
        $chunkSize = max(25, min(500, $chunkSize));
        if ($performers === []) {
            return;
        }

        // Provider synchronization normally contains a single provider. Keep mixed-provider
        // input supported, but process each provider in bounded chunks so large feeds do not
        // need full-size inserted/changed/unchanged and geo-diff arrays at the same time.
        $byProvider = [];
        foreach ($performers as $performer) {
            $byProvider[$performer->provider][] = $performer;
        }

        foreach ($byProvider as $provider => $providerPerformers) {
            foreach (array_chunk($providerPerformers, $chunkSize) as $chunk) {
                $providerIds = array_map(static fn (Performer $p): string => $p->providerId, $chunk);

                SyncPerformanceProfiler::start('db.existing_lookup');
                $existingHashes = $this->existingStructuralHashesForIds($provider, $providerIds);
                SyncPerformanceProfiler::stop('db.existing_lookup');

                $writeRows = [];
                $structuralUnchanged = [];
                $structuralChanged = [];
                $insertedCount = 0;
                foreach ($chunk as $performer) {
                    $storedHash = $existingHashes[$performer->providerId] ?? null;
                    if ($storedHash === null) {
                        $writeRows[] = $performer;
                        $insertedCount++;
                        continue;
                    }
                    if ($storedHash !== '' && hash_equals($storedHash, $this->structuralHash($performer))) {
                        $structuralUnchanged[] = $performer;
                    } else {
                        $writeRows[] = $performer;
                        $structuralChanged[] = $performer;
                    }
                }
                unset($existingHashes);

                $this->profileStructuralChanges($provider, $structuralChanged);
                $changedCount = count($structuralChanged);
                $unchangedCount = count($structuralUnchanged);
                SyncPerformanceProfiler::increment('rows_inserted', $insertedCount);
                SyncPerformanceProfiler::increment('rows_structural_changed', $changedCount);
                SyncPerformanceProfiler::increment('rows_structural_unchanged', $unchangedCount);
                SyncPerformanceProfiler::increment('rows_changed', $changedCount);
                SyncPerformanceProfiler::increment('rows_unchanged', $unchangedCount);

                if ($writeRows !== []) {
                    $this->upsertPerformerChunk($writeRows);
                }
                if ($structuralUnchanged !== []) {
                    $this->updateVolatileChunk($provider, $structuralUnchanged);
                }
                unset($writeRows, $structuralChanged, $structuralUnchanged);

                SyncPerformanceProfiler::start('db.geo_lookup');
                $existingGeo = $this->existingGeoBlocksForIds($provider, $providerIds);
                SyncPerformanceProfiler::stop('db.geo_lookup');
                $geoChanged = [];
                $geoUnchanged = 0;
                foreach ($chunk as $performer) {
                    $wanted = array_values(array_unique(array_map('strtoupper', $performer->geoBlocks)));
                    sort($wanted, SORT_STRING);
                    $stored = $existingGeo[$performer->providerId] ?? [];
                    sort($stored, SORT_STRING);
                    if ($wanted === $stored) {
                        $geoUnchanged++;
                    } else {
                        $geoChanged[] = $performer;
                    }
                }
                unset($existingGeo);
                SyncPerformanceProfiler::increment('geo_changed_performers', count($geoChanged));
                SyncPerformanceProfiler::increment('geo_unchanged_performers', $geoUnchanged);
                if ($geoChanged !== []) {
                    $this->replaceGeoBlocksMany($geoChanged);
                }
                unset($geoChanged, $providerIds, $chunk);
            }
            unset($providerPerformers);
        }
    }

    /** @param list<string> $providerIds @return array<string,string> */
    private function existingStructuralHashesForIds(string $provider, array $providerIds): array
    {
        if ($providerIds === []) {
            return [];
        }
        $sql = 'SELECT provider_id, structural_hash FROM performers WHERE provider = ? AND provider_id IN ('
            . implode(', ', array_fill(0, count($providerIds), '?')) . ')';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(array_merge([$provider], $providerIds));
        $result = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $result[(string) $row['provider_id']] = (string) ($row['structural_hash'] ?? '');
        }
        SyncPerformanceProfiler::meta('structural_lookup_mode', 'persisted_hash_chunked');
        return $result;
    }

    /** @param list<string> $providerIds @return array<string,list<string>> */
    private function existingGeoBlocksForIds(string $provider, array $providerIds): array
    {
        if ($providerIds === []) {
            return [];
        }
        $sql = 'SELECT provider_id, country_code FROM performer_geo_blocks WHERE provider = ? AND provider_id IN ('
            . implode(', ', array_fill(0, count($providerIds), '?')) . ') ORDER BY provider_id, country_code';
        $statement = $this->pdo->prepare($sql);
        $statement->execute(array_merge([$provider], $providerIds));
        $result = [];
        while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
            $result[(string) $row['provider_id']][] = strtoupper((string) $row['country_code']);
        }
        return $result;
    }

    /** @param list<Performer> $chunk */
    private function upsertPerformerChunk(array $chunk): void
    {
        $columns = [
            'provider', 'provider_id', 'username', 'display_name', 'gender', 'age',
            'country_code', 'image_url', 'preview_url', 'embed_url', 'room_status',
            'room_url', 'viewers', 'popularity_score', 'watch_sort_score',
            'provider_sort_score', 'tags_json', 'provider_is_new', 'has_geo_blocks',
            'structural_hash', 'is_online',
        ];
        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($columns), '?')) . ', NOW())';
        $valuesSql = implode(', ', array_fill(0, count($chunk), $rowPlaceholder));
        $sql = 'INSERT INTO performers (' . implode(', ', $columns) . ', last_seen_at) VALUES ' . $valuesSql
            . ' ON DUPLICATE KEY UPDATE '
            . 'username = VALUES(username), display_name = VALUES(display_name), gender = VALUES(gender), '
            . 'age = VALUES(age), country_code = VALUES(country_code), image_url = VALUES(image_url), '
            . 'preview_url = VALUES(preview_url), embed_url = VALUES(embed_url), room_status = VALUES(room_status), '
            . 'room_url = VALUES(room_url), viewers = VALUES(viewers), popularity_score = VALUES(popularity_score), '
            . 'watch_sort_score = VALUES(watch_sort_score), provider_sort_score = VALUES(provider_sort_score), '
            . 'tags_json = VALUES(tags_json), provider_is_new = VALUES(provider_is_new), '
            . 'has_geo_blocks = VALUES(has_geo_blocks), structural_hash = VALUES(structural_hash), '
            . 'is_online = VALUES(is_online), last_seen_at = NOW()';

        $params = [];
        foreach ($chunk as $performer) {
            foreach ($this->performerSqlValues($performer) as $value) {
                $params[] = $value;
            }
        }
        $statement = $this->pdo->prepare($sql);
        SyncPerformanceProfiler::start('db.performers_upsert');
        $statement->execute($params);
        SyncPerformanceProfiler::stop('db.performers_upsert');
        SyncPerformanceProfiler::increment('performer_batches');
        SyncPerformanceProfiler::increment('performer_sql_bytes', strlen($sql));
    }

    /** @param list<Performer> $performers */
    private function profileStructuralChanges(string $provider, array $performers): void
    {
        if (!SyncPerformanceProfiler::enabled() || $performers === []) {
            return;
        }

        $changeFieldCounts = [];
        $changeCombinationCounts = [];
        foreach (array_chunk($performers, 500) as $chunk) {
            $ids = array_map(static fn (Performer $p): string => $p->providerId, $chunk);
            $sql = 'SELECT provider_id, username, display_name, gender, age, country_code, provider_is_new, has_geo_blocks '
                . 'FROM performers WHERE provider = ? AND provider_id IN ('
                . implode(', ', array_fill(0, count($ids), '?')) . ')';
            $statement = $this->pdo->prepare($sql);
            $statement->execute(array_merge([$provider], $ids));
            $stored = [];
            while ($row = $statement->fetch(PDO::FETCH_ASSOC)) {
                $stored[(string) $row['provider_id']] = $row;
            }

            foreach ($chunk as $performer) {
                $row = $stored[$performer->providerId] ?? null;
                if ($row === null) {
                    continue;
                }
                $changedFields = $this->structuralChangedFieldsFromRow($row, $performer);
                foreach ($changedFields as $field) {
                    $changeFieldCounts[$field] = ($changeFieldCounts[$field] ?? 0) + 1;
                }
                if ($changedFields !== []) {
                    sort($changedFields, SORT_STRING);
                    $combination = implode('+', $changedFields);
                    $changeCombinationCounts[$combination] = ($changeCombinationCounts[$combination] ?? 0) + 1;
                }
            }
        }

        arsort($changeFieldCounts, SORT_NUMERIC);
        foreach ($changeFieldCounts as $field => $count) {
            SyncPerformanceProfiler::meta('structural_change.' . $field, (int) $count);
        }
        arsort($changeCombinationCounts, SORT_NUMERIC);
        $rank = 1;
        foreach (array_slice($changeCombinationCounts, 0, 10, true) as $combination => $count) {
            SyncPerformanceProfiler::meta('structural_combo_' . $rank, $combination . '=' . $count);
            $rank++;
        }
    }

    /** @return array<string,string|int|null> */
    private function structuralFieldValues(Performer $performer): array
    {
        return [
            'username' => $performer->username,
            'display_name' => $performer->displayName,
            'gender' => $performer->gender,
            'age' => $performer->age,
            'country_code' => $performer->countryCode,
            'provider_is_new' => $performer->providerNew === null ? null : ($performer->providerNew ? 1 : 0),
            'has_geo_blocks' => $performer->geoBlocks === [] ? 0 : 1,
        ];
    }

    private function structuralHash(Performer $performer): string
    {
        $null = '<NULL>';
        return md5(implode(chr(31), array_map(
            static fn ($value): string => $value === null ? $null : (string) $value,
            array_values($this->structuralFieldValues($performer))
        )));
    }

    /** @param array<string,mixed> $stored @return list<string> */
    private function structuralChangedFieldsFromRow(array $stored, Performer $performer): array
    {
        $changed = [];
        foreach ($this->structuralFieldValues($performer) as $field => $value) {
            $storedValue = $stored[$field] ?? null;
            if ($field === 'age' || $field === 'provider_is_new' || $field === 'has_geo_blocks') {
                $storedValue = $storedValue === null ? null : (int) $storedValue;
            }
            if ($storedValue !== $value) {
                $changed[] = $field;
            }
        }
        return $changed;
    }

    /** @param list<Performer> $chunk */
    private function updateVolatileChunk(string $provider, array $chunk): void
    {
        $columns = [
            'image_url' => static fn (Performer $p) => $p->imageUrl,
            'preview_url' => static fn (Performer $p) => $p->previewUrl,
            'embed_url' => static fn (Performer $p) => $p->embedUrl,
            'room_status' => static fn (Performer $p) => $p->roomStatus,
            'room_url' => static fn (Performer $p) => $p->roomUrl,
            'viewers' => static fn (Performer $p) => $p->viewers,
            'popularity_score' => static fn (Performer $p) => $p->popularityScore,
            'watch_sort_score' => static fn (Performer $p) => $p->watchSortScore,
            'provider_sort_score' => static fn (Performer $p) => $p->providerSortScore,
            'tags_json' => static fn (Performer $p) => json_encode($p->tags, JSON_UNESCAPED_SLASHES),
            'is_online' => static fn (Performer $p) => $p->online ? 1 : 0,
        ];

        $sets = [];
        $params = [];
        foreach ($columns as $column => $valueOf) {
            $case = $column . ' = CASE provider_id ';
            foreach ($chunk as $performer) {
                $case .= 'WHEN ? THEN ? ';
                $params[] = $performer->providerId;
                $params[] = $valueOf($performer);
            }
            $sets[] = $case . 'ELSE ' . $column . ' END';
        }
        $sets[] = 'last_seen_at = NOW()';

        $ids = array_map(static fn (Performer $p): string => $p->providerId, $chunk);
        $sql = 'UPDATE performers SET ' . implode(', ', $sets)
            . ' WHERE provider = ? AND provider_id IN ('
            . implode(', ', array_fill(0, count($ids), '?')) . ')';
        $params[] = $provider;
        foreach ($ids as $id) {
            $params[] = $id;
        }

        $statement = $this->pdo->prepare($sql);
        SyncPerformanceProfiler::start('db.volatile_update');
        $statement->execute($params);
        SyncPerformanceProfiler::stop('db.volatile_update');
        SyncPerformanceProfiler::increment('volatile_batches');
        SyncPerformanceProfiler::increment('volatile_sql_bytes', strlen($sql));
        SyncPerformanceProfiler::meta('volatile_update_mode', 'case_update_' . count($chunk));
    }

    /** @return list<mixed> */
    private function performerSqlValues(Performer $performer): array
    {
        return [
            $performer->provider,
            $performer->providerId,
            $performer->username,
            $performer->displayName,
            $performer->gender,
            $performer->age,
            $performer->countryCode,
            $performer->imageUrl,
            $performer->previewUrl,
            $performer->embedUrl,
            $performer->roomStatus,
            $performer->roomUrl,
            $performer->viewers,
            $performer->popularityScore,
            $performer->watchSortScore,
            $performer->providerSortScore,
            json_encode($performer->tags, JSON_UNESCAPED_SLASHES),
            $performer->providerNew === null ? null : ($performer->providerNew ? 1 : 0),
            $performer->geoBlocks === [] ? 0 : 1,
            $this->structuralHash($performer),
            $performer->online ? 1 : 0,
        ];
    }

    /** @param list<Performer> $performers */
    private function replaceGeoBlocksMany(array $performers): void
    {
        if ($performers === []) {
            return;
        }

        $byProvider = [];
        foreach ($performers as $performer) {
            $byProvider[$performer->provider][$performer->providerId] = $performer;
        }

        foreach ($byProvider as $provider => $performersById) {
            $providerIds = array_keys($performersById);
            $deleteSql = 'DELETE FROM performer_geo_blocks WHERE provider = ? AND provider_id IN ('
                . implode(', ', array_fill(0, count($providerIds), '?')) . ')';
            $delete = $this->pdo->prepare($deleteSql);
            SyncPerformanceProfiler::start('db.geo_delete');
            $delete->execute(array_merge([$provider], $providerIds));
            SyncPerformanceProfiler::stop('db.geo_delete');

            $rows = [];
            $params = [];
            foreach ($performersById as $performer) {
                foreach (array_values(array_unique($performer->geoBlocks)) as $countryCode) {
                    $rows[] = '(?, ?, ?)';
                    $params[] = $provider;
                    $params[] = $performer->providerId;
                    $params[] = $countryCode;
                }
            }
            if ($rows !== []) {
                $insertSql = 'INSERT IGNORE INTO performer_geo_blocks (provider, provider_id, country_code) VALUES '
                    . implode(', ', $rows);
                $insert = $this->pdo->prepare($insertSql);
                SyncPerformanceProfiler::start('db.geo_insert');
                $insert->execute($params);
                SyncPerformanceProfiler::stop('db.geo_insert');
                SyncPerformanceProfiler::increment('geo_relations', count($rows));
                SyncPerformanceProfiler::increment('geo_sql_bytes', strlen($insertSql));
            }
        }
    }

    public function databaseTimestamp(): string
    {
        return (string) $this->pdo->query('SELECT NOW()')->fetchColumn();
    }

    /**
     * Mark only performers that were not refreshed by the current sync as offline.
     * This avoids toggling every current row 1 -> 0 -> 1, which rewrites the many
     * online-prefixed catalog indexes twice on every synchronization.
     */
    public function markProviderOfflineBefore(string $provider, string $seenSince): void
    {
        $statement = $this->pdo->prepare(
            'UPDATE performers SET is_online = 0 '
            . 'WHERE provider = :provider AND is_online = 1 AND last_seen_at < :seen_since'
        );
        $statement->execute(['provider' => $provider, 'seen_since' => $seenSince]);
    }

    public function markProviderOffline(string $provider): void
    {
        $statement = $this->pdo->prepare('UPDATE performers SET is_online = 0 WHERE provider = :provider');
        $statement->execute(['provider' => $provider]);
    }

    public function staleMediaUrls(string $provider, ?int $retentionDays): array
    {
        $params = ['provider' => $provider];
        $expiry = '';
        if ($retentionDays !== null) {
            $retentionDays = max(1, min(3650, $retentionDays));
            $expiry = ' AND last_seen_at < DATE_SUB(NOW(), INTERVAL ' . $retentionDays . ' DAY)';
        }
        $statement = $this->pdo->prepare(
            'SELECT image_url, preview_url FROM performers '
            . 'WHERE provider = :provider AND is_online = 0' . $expiry
        );
        $statement->execute($params);
        $urls = [];
        foreach ($statement->fetchAll() as $row) {
            foreach (['image_url', 'preview_url'] as $field) {
                $url = trim((string) ($row[$field] ?? ''));
                if ($url !== '') {
                    $urls[$url] = true;
                }
            }
        }

        return array_keys($urls);
    }

    public function providerMediaUrls(string $provider): array
    {
        $statement = $this->pdo->prepare(
            'SELECT image_url, preview_url FROM performers WHERE provider = :provider'
        );
        $statement->execute(['provider' => $provider]);
        $urls = [];
        foreach ($statement->fetchAll() as $row) {
            foreach (['image_url', 'preview_url'] as $field) {
                $url = trim((string) ($row[$field] ?? ''));
                if ($url !== '') {
                    $urls[$url] = true;
                }
            }
        }

        return array_keys($urls);
    }

    public function deleteStale(string $provider, ?int $retentionDays): int
    {
        $expiry = '';
        if ($retentionDays !== null) {
            $retentionDays = max(1, min(3650, $retentionDays));
            $expiry = ' AND last_seen_at < DATE_SUB(NOW(), INTERVAL ' . $retentionDays . ' DAY)';
        }
        $statement = $this->pdo->prepare(
            'DELETE FROM performers WHERE provider = :provider AND is_online = 0' . $expiry
        );
        $statement->execute(['provider' => $provider]);

        return $statement->rowCount();
    }

    /** @param list<string> $usernames */
    public function deleteByUsernames(string $provider, array $usernames): int
    {
        $usernames = array_values(array_unique(array_filter(array_map(
            static fn (mixed $username): string => trim((string) $username),
            $usernames
        ), static fn (string $username): bool => $username !== '' && strlen($username) <= 190)));
        $deleted = 0;
        foreach (array_chunk($usernames, 500) as $chunk) {
            $params = ['provider' => $provider];
            $placeholders = [];
            foreach ($chunk as $index => $username) {
                $key = 'deleted_username_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $username;
            }
            $statement = $this->pdo->prepare(
                'DELETE FROM performers WHERE provider = :provider AND username IN ('
                . implode(', ', $placeholders) . ')'
            );
            $statement->execute($params);
            $deleted += $statement->rowCount();
        }

        return $deleted;
    }

    public function online(array $filters = [], int $limit = 24, int $offset = 0): array
    {
        [$where, $params] = $this->buildFilters($filters);

        $limit = max(1, min(97, $limit));
        $offset = max(0, $offset);
        $hideRestrictedWhenUnknown = (bool) ($filters['hide_restricted_when_geo_unknown'] ?? false)
            && (is_array($filters['geo_codes'] ?? null) ? $filters['geo_codes'] : []) === [];
        $sort = (string) ($filters['sort'] ?? 'popular');
        $orderBy = match ($sort) {
            'newest' => $this->newnessOrderSql($filters, $params),
            'provider_popular' => "provider_sort_score DESC, id DESC",
            'youngest' => "(age IS NULL) ASC, age ASC, popularity_score DESC, display_name ASC",
            'oldest' => "(age IS NULL) ASC, age DESC, popularity_score DESC, display_name ASC",
            'name' => "display_name ASC, popularity_score DESC",
            default => "watch_sort_score DESC, id DESC",
        };
        $statusOrder = in_array($sort, ['popular', 'provider_popular'], true)
            ? ''
            : "(room_status = 'public') DESC, ";
        $providersWithoutRoomStatus = is_array($filters['providers_without_room_status'] ?? null)
            ? array_values(array_unique(array_filter(array_map('strval', $filters['providers_without_room_status']))))
            : [];
        if ($statusOrder !== '' && $providersWithoutRoomStatus !== []) {
            $statusOrderPlaceholders = [];
            foreach ($providersWithoutRoomStatus as $index => $provider) {
                $placeholder = 'status_order_provider_' . $index;
                $statusOrderPlaceholders[] = ':' . $placeholder;
                $params[$placeholder] = $provider;
            }
            $statusOrder = "(room_status = 'public' OR provider IN ("
                . implode(', ', $statusOrderPlaceholders) . ')) DESC, ';
        }
        $indexHint = match ($sort) {
            'provider_popular' => ' FORCE INDEX (idx_online_provider_sort)',
            'popular' => ' FORCE INDEX (idx_online_watch_sort)',
            default => '',
        };
        if ($hideRestrictedWhenUnknown) {
            $indexHint = match ($sort) {
                'provider_popular' => ' FORCE INDEX (idx_online_geo_provider_sort)',
                'popular' => ' FORCE INDEX (idx_online_geo_watch_sort)',
                'newest' => ' FORCE INDEX (idx_online_geo_new)',
                default => $indexHint,
            };
        }
        $sql = 'SELECT id FROM performers' . $indexHint . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . $statusOrder . $orderBy . ' LIMIT ' . $limit . ' OFFSET ' . $offset;
        PerformanceProfiler::start('catalog.ids_query');
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        PerformanceProfiler::stop('catalog.ids_query');
        if ($ids === []) {
            return [];
        }

        $idPlaceholders = implode(', ', array_fill(0, count($ids), '?'));
        PerformanceProfiler::start('catalog.rows_query');
        $rowsStatement = $this->pdo->prepare('SELECT * FROM performers WHERE id IN (' . $idPlaceholders . ')');
        $rowsStatement->execute($ids);
        $rowsById = [];
        foreach ($rowsStatement->fetchAll() as $row) {
            $rowsById[(int) $row['id']] = $row;
        }
        PerformanceProfiler::stop('catalog.rows_query');

        return array_values(array_filter(array_map(
            static fn (int $id): ?array => $rowsById[$id] ?? null,
            $ids
        )));
    }

    /**
     * Cursor-based catalog window for the stable popularity sorts used by tag discovery.
     * This avoids OFFSET degradation on deep tag pages while preserving the existing
     * indexed order. Returns rows in normal display order for both directions.
     */
    public function onlineByPopularityCursor(
        array $filters,
        int $limit = 25,
        ?array $cursor = null,
        string $direction = 'next'
    ): array {
        [$where, $params] = $this->buildFilters($filters);
        $limit = max(1, min(97, $limit));
        $direction = $direction === 'prev' ? 'prev' : 'next';
        $sort = (string) ($filters['sort'] ?? 'popular');
        $scoreColumn = $sort === 'provider_popular' ? 'provider_sort_score' : 'watch_sort_score';
        $hideRestrictedWhenUnknown = (bool) ($filters['hide_restricted_when_geo_unknown'] ?? false)
            && (is_array($filters['geo_codes'] ?? null) ? $filters['geo_codes'] : []) === [];
        $indexHint = $sort === 'provider_popular'
            ? ' FORCE INDEX (' . ($hideRestrictedWhenUnknown ? 'idx_online_geo_provider_sort' : 'idx_online_provider_sort') . ')'
            : ' FORCE INDEX (' . ($hideRestrictedWhenUnknown ? 'idx_online_geo_watch_sort' : 'idx_online_watch_sort') . ')';

        if (is_array($cursor)
            && isset($cursor['score'], $cursor['id'])
            && ctype_digit((string) $cursor['score'])
            && ctype_digit((string) $cursor['id'])) {
            $comparison = $direction === 'prev' ? '>' : '<';
            $where[] = '(' . $scoreColumn . ' ' . $comparison . ' :cursor_score OR ('
                . $scoreColumn . ' = :cursor_score_equal AND id ' . $comparison . ' :cursor_id))';
            $params['cursor_score'] = (string) $cursor['score'];
            $params['cursor_score_equal'] = (string) $cursor['score'];
            $params['cursor_id'] = (int) $cursor['id'];
        }

        $sqlDirection = $direction === 'prev' ? 'ASC' : 'DESC';
        $sql = 'SELECT id FROM performers' . $indexHint . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY ' . $scoreColumn . ' ' . $sqlDirection . ', id ' . $sqlDirection
            . ' LIMIT ' . $limit;
        PerformanceProfiler::start('catalog.ids_query');
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        $ids = array_map('intval', $statement->fetchAll(PDO::FETCH_COLUMN));
        PerformanceProfiler::stop('catalog.ids_query');
        if ($ids === []) {
            return [];
        }

        if ($direction === 'prev') {
            $ids = array_reverse($ids);
        }

        $idPlaceholders = implode(', ', array_fill(0, count($ids), '?'));
        PerformanceProfiler::start('catalog.rows_query');
        $rowsStatement = $this->pdo->prepare('SELECT * FROM performers WHERE id IN (' . $idPlaceholders . ')');
        $rowsStatement->execute($ids);
        $rowsById = [];
        foreach ($rowsStatement->fetchAll() as $row) {
            $rowsById[(int) $row['id']] = $row;
        }
        PerformanceProfiler::stop('catalog.rows_query');

        return array_values(array_filter(array_map(
            static fn (int $id): ?array => $rowsById[$id] ?? null,
            $ids
        )));
    }

    public function countOnline(array $filters = []): int
    {
        [$where, $params] = $this->buildFilters($filters);
        $unknownGeo = (bool) ($filters['hide_restricted_when_geo_unknown'] ?? false)
            && (is_array($filters['geo_codes'] ?? null) ? $filters['geo_codes'] : []) === [];
        // Count queries have different access patterns from the paged catalog query.
        // In particular, forcing the generic geo index made cold filtered counts scan a
        // secondary index and then fetch table rows for predicates such as country/tag.
        // Prefer the dedicated country index when possible, and let MySQL/MariaDB choose
        // its own plan for text/JSON predicates (tag/search) instead of forcing a poor one.
        $hasCountry = !empty($filters['country'])
            && preg_match('/^[A-Z]{2}$/', (string) $filters['country']) === 1;
        $hasTextPredicate = !empty($filters['tag']) || !empty($filters['q']);
        if ($unknownGeo && !empty($filters['new_only'])) {
            $indexHint = ' FORCE INDEX (idx_online_geo_new)';
            $countStrategy = 'geo_new';
        } elseif ($unknownGeo && $hasCountry) {
            // Country counts are dramatically faster on the dedicated composite index.
            $indexHint = ' FORCE INDEX (idx_online_geo_country)';
            $countStrategy = 'geo_country';
        } elseif ($unknownGeo) {
            // Keep the pre-0.25.1 access path for tag/search predicates. Letting the
            // optimizer choose freely caused a ~3x regression on cold tag counts in
            // the 26k-performer stress dataset. The geo catalog index restores the
            // previous baseline while retaining the dedicated country optimization.
            $indexHint = ' FORCE INDEX (idx_online_geo_catalog)';
            $countStrategy = $hasTextPredicate ? 'geo_catalog_text' : 'geo_catalog';
        } else {
            $indexHint = '';
            $countStrategy = $hasTextPredicate ? 'optimizer_text' : 'optimizer_default';
        }
        PerformanceProfiler::meta('count_strategy', $countStrategy);
        PerformanceProfiler::start('catalog.count_query');
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) FROM performers' . $indexHint . ' WHERE ' . implode(' AND ', $where)
        );
        $statement->execute($params);
        $count = (int) $statement->fetchColumn();
        PerformanceProfiler::stop('catalog.count_query');

        return $count;
    }

    /** Return countries represented by online performers in the current catalog scope. */
    public function availableCountries(array $filters = []): array
    {
        unset($filters['country']);
        [$where, $params] = $this->buildFilters($filters);
        $where[] = 'country_code IS NOT NULL';
        $unknownGeo = (bool) ($filters['hide_restricted_when_geo_unknown'] ?? false)
            && (is_array($filters['geo_codes'] ?? null) ? $filters['geo_codes'] : []) === [];
        $indexHint = $unknownGeo ? ' FORCE INDEX (idx_online_geo_country)' : '';
        PerformanceProfiler::start('catalog.countries_query');
        $statement = $this->pdo->prepare(
            'SELECT country_code, COUNT(*) AS performer_count FROM performers' . $indexHint . ' WHERE '
            . implode(' AND ', $where) . ' GROUP BY country_code ORDER BY country_code'
        );
        $statement->execute($params);
        $countries = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $code = (string) ($row['country_code'] ?? '');
            if (preg_match('/^[A-Z]{2}$/', $code) === 1) {
                $countries[$code] = (int) ($row['performer_count'] ?? 0);
            }
        }

        PerformanceProfiler::stop('catalog.countries_query');
        return $countries;
    }

    /**
     * Count several landing filter sets from one small result set. Landing
     * management used to issue one COUNT query per landing; with custom
     * landings that multiplied full-table scans and made the admin tab slow.
     *
     * @param array<string,array<string,mixed>> $filterSets
     * @param list<string> $providers
     * @return array<string,int>
     */
    public function countOnlineForFilterSets(array $filterSets, array $providers = []): array
    {
        $keys = array_keys($filterSets);
        $counts = array_fill_keys($keys, 0);
        if ($keys === []) {
            return $counts;
        }

        $params = [];
        $where = ['p.is_online = 1'];
        $genderScope = $this->genderScopeSql($params, 'landing_count_gender');
        $where[] = trim(preg_replace('/^AND\\s+/', '', $genderScope) ?? '1 = 0');
        $providers = array_values(array_unique(array_filter(array_map('strval', $providers))));
        if ($providers !== []) {
            $placeholders = [];
            foreach ($providers as $index => $provider) {
                $placeholder = 'landing_count_provider_' . $index;
                $placeholders[] = ':' . $placeholder;
                $params[$placeholder] = $provider;
            }
            $where[] = 'p.provider IN (' . implode(', ', $placeholders) . ')';
        }
        $statement = $this->pdo->prepare(
            'SELECT p.provider, p.gender, p.age, p.country_code, p.room_status, p.tags_json, '
            . 'p.provider_is_new, p.created_at FROM performers p WHERE '
            . implode(' AND ', $where)
            . ' AND NOT EXISTS (SELECT 1 FROM performer_geo_blocks geo '
            . 'WHERE geo.provider = p.provider AND geo.provider_id = p.provider_id)'
        );
        $statement->execute($params);
        $prepared = [];
        foreach ($filterSets as $key => $filters) {
            $filters = is_array($filters) ? $filters : [];
            [$minimumAge, $maximumAge] = match ($filters['age'] ?? '') {
                '18-20' => [18, 20], '21-25' => [21, 25], '26-30' => [26, 30],
                '31-35' => [31, 35], '36-40' => [36, 40], '41-plus' => [41, null],
                default => [null, null],
            };
            $prepared[$key] = [
                'provider' => trim((string) ($filters['provider'] ?? '')),
                'providers' => array_values(array_unique(array_filter(array_map(
                    'strval', is_array($filters['providers'] ?? null) ? $filters['providers'] : []
                )))),
                'gender' => trim((string) ($filters['gender'] ?? '')),
                'country' => strtoupper(trim((string) ($filters['country'] ?? ''))),
                'minimum_age' => $minimumAge,
                'maximum_age' => $maximumAge,
                'room_status' => trim((string) ($filters['room_status'] ?? '')),
                'tag' => trim((string) ($filters['tag'] ?? '')),
                'new_only' => !empty($filters['new_only']),
                'new_cutoff' => time() - max(1, min(90, (int) ($filters['new_days'] ?? 7))) * 86400,
            ];
        }
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            foreach ($prepared as $key => $filters) {
                $provider = (string) $row['provider'];
                if ($filters['provider'] !== '' && $provider !== $filters['provider']) {
                    continue;
                }
                if ($filters['provider'] === '' && $filters['providers'] !== []
                    && !in_array($provider, $filters['providers'], true)) {
                    continue;
                }
                if ($filters['gender'] !== '' && (string) $row['gender'] !== $filters['gender']) {
                    continue;
                }
                if ($filters['country'] !== '' && (string) $row['country_code'] !== $filters['country']) {
                    continue;
                }
                $age = $row['age'] === null ? null : (int) $row['age'];
                if ($filters['minimum_age'] !== null && ($age === null || $age < $filters['minimum_age'])) {
                    continue;
                }
                if ($filters['maximum_age'] !== null && ($age === null || $age > $filters['maximum_age'])) {
                    continue;
                }
                if ($filters['room_status'] !== '' && (string) $row['room_status'] !== $filters['room_status']) {
                    continue;
                }
                if ($filters['tag'] !== '' && !str_contains((string) $row['tags_json'], '"' . $filters['tag'] . '"')) {
                    continue;
                }
                if ($filters['new_only']) {
                    $isProviderNew = $row['provider_is_new'] !== null && (int) $row['provider_is_new'] === 1;
                    $createdAt = strtotime((string) $row['created_at']) ?: 0;
                    if (!$isProviderNew && $createdAt < $filters['new_cutoff']) {
                        continue;
                    }
                }
                $counts[$key]++;
            }
        }

        return $counts;
    }

    /**
     * Return online counts for several providers with one grouped query.
     * The admin dashboard calls this for every enabled provider, so avoiding
     * one full count query per provider keeps the page responsive as sources
     * are added.
     *
     * @param list<string> $providers
     * @return array<string,int>
     */
    public function countOnlineByProviders(array $providers): array
    {
        $providers = array_values(array_unique(array_filter(array_map('strval', $providers))));
        $counts = array_fill_keys($providers, 0);
        if ($providers === []) {
            return $counts;
        }

        $params = [];
        $providerPlaceholders = [];
        foreach ($providers as $index => $provider) {
            $placeholder = 'online_count_provider_' . $index;
            $providerPlaceholders[] = ':' . $placeholder;
            $params[$placeholder] = $provider;
        }
        $genderScope = $this->genderScopeSql($params, 'online_count_gender');
        $statement = $this->pdo->prepare(
            'SELECT provider, COUNT(*) AS online_count FROM performers '
            . 'WHERE is_online = 1 AND provider IN (' . implode(', ', $providerPlaceholders) . ') '
            . $genderScope . 'GROUP BY provider'
        );
        $statement->execute($params);
        foreach ($statement->fetchAll() as $row) {
            $provider = (string) ($row['provider'] ?? '');
            if (array_key_exists($provider, $counts)) {
                $counts[$provider] = (int) ($row['online_count'] ?? 0);
            }
        }

        return $counts;
    }

    public function sitemap(array $providers, int $limit = 10000): array
    {
        $providers = array_values(array_unique(array_filter(array_map('strval', $providers))));
        if ($providers === []) {
            return [];
        }
        $limit = max(1, min(50000, $limit));
        $placeholders = [];
        $params = [];
        foreach ($providers as $index => $provider) {
            $placeholder = 'sitemap_provider_' . $index;
            $placeholders[] = ':' . $placeholder;
            $params[$placeholder] = $provider;
        }
        $genderScope = $this->genderScopeSql($params, 'sitemap_gender');
        $statement = $this->pdo->prepare(
            'SELECT provider, username, updated_at FROM performers '
            . 'WHERE is_online = 1 AND provider IN (' . implode(', ', $placeholders) . ') '
            . $genderScope . 'AND NOT EXISTS ('
            . 'SELECT 1 FROM performer_geo_blocks geo WHERE geo.provider = performers.provider '
            . 'AND geo.provider_id = performers.provider_id) '
            . 'ORDER BY updated_at DESC LIMIT ' . $limit
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function findByUsername(
        string $provider,
        string $username,
        array $geoCodes = [],
        bool $hideRestrictedWhenUnknown = false
    ): ?array
    {
        $params = ['provider' => $provider, 'username' => $username];
        $geoWhere = $this->geoVisibilitySql($geoCodes, $hideRestrictedWhenUnknown, $params, 'profile_geo');
        $genderScope = $this->genderScopeSql($params, 'profile_gender');
        $statement = $this->pdo->prepare(
            'SELECT * FROM performers WHERE provider = :provider AND username = :username '
            . $genderScope . $geoWhere . ' LIMIT 1'
        );
        $statement->execute($params);
        $performer = $statement->fetch();

        return is_array($performer) ? $performer : null;
    }

    public function similar(
        array $performer,
        int $limit = 8,
        bool $includeNonPublic = false,
        array $providers = [],
        array $providersWithoutRoomStatus = [],
        array $geoCodes = [],
        bool $hideRestrictedWhenUnknown = false
    ): array
    {
        $params = [
            'performer_id' => $performer['id'],
        ];
        $providers = array_values(array_unique(array_filter(array_map('strval', $providers))));
        if ($providers === []) {
            $providers = [(string) $performer['provider']];
        }
        $providerPlaceholders = [];
        foreach ($providers as $index => $provider) {
            $placeholder = 'similar_provider_' . $index;
            $providerPlaceholders[] = ':' . $placeholder;
            $params[$placeholder] = $provider;
        }
        $genderOrder = '';
        if (!empty($performer['gender'])) {
            $genderOrder = '(gender = :similar_gender) DESC, ';
            $params['similar_gender'] = $performer['gender'];
        }

        $roomStatusFilter = '';
        if (!$includeNonPublic) {
            $unknownStatusPlaceholders = [];
            foreach (array_values(array_intersect($providers, $providersWithoutRoomStatus)) as $index => $provider) {
                $placeholder = 'unknown_status_provider_' . $index;
                $unknownStatusPlaceholders[] = ':' . $placeholder;
                $params[$placeholder] = $provider;
            }
            $roomStatusFilter = $unknownStatusPlaceholders === []
                ? "AND room_status = 'public' "
                : "AND (room_status = 'public' OR provider IN (" . implode(', ', $unknownStatusPlaceholders) . ')) ';
        }
        $geoFilter = $this->geoVisibilitySql(
            $geoCodes,
            $hideRestrictedWhenUnknown,
            $params,
            'similar_geo'
        );
        $genderScope = $this->genderScopeSql($params, 'similar_gender_scope');
        $statement = $this->pdo->prepare(
            'SELECT * FROM performers '
            . 'WHERE provider IN (' . implode(', ', $providerPlaceholders) . ') AND is_online = 1 '
            . $genderScope . $roomStatusFilter . $geoFilter . 'AND id <> :performer_id '
            . 'ORDER BY ' . $genderOrder . '(provider = :target_provider) DESC, popularity_score DESC, viewers DESC LIMIT 80'
        );
        $params['target_provider'] = $performer['provider'];
        $statement->execute($params);
        $candidates = $statement->fetchAll();
        $targetTags = json_decode((string) $performer['tags_json'], true) ?: [];

        foreach ($candidates as &$candidate) {
            $candidateTags = json_decode((string) $candidate['tags_json'], true) ?: [];
            $sharedTags = count(array_intersect($targetTags, $candidateTags));
            $sameGender = $candidate['gender'] === $performer['gender'] ? 1 : 0;
            $sameProvider = $candidate['provider'] === $performer['provider'] ? 1 : 0;
            $candidate['_similarity'] = ($sameGender * 10) + ($sharedTags * 5) + ($sameProvider * 2)
                + (float) ($candidate['popularity_score'] ?? 0);
        }
        unset($candidate);

        usort($candidates, static fn (array $a, array $b): int => $b['_similarity'] <=> $a['_similarity']);
        $candidates = array_slice($candidates, 0, max(1, min(12, $limit)));
        foreach ($candidates as &$candidate) {
            unset($candidate['_similarity']);
        }
        unset($candidate);

        return $candidates;
    }

    private function buildFilters(array $filters): array
    {
        $where = ['is_online = 1'];
        $params = [];
        $where[] = trim(preg_replace(
            '/^AND\s+/',
            '',
            $this->genderScopeSql($params, 'catalog_gender')
        ) ?? '1 = 0');
        if (!empty($filters['provider'])) {
            $where[] = 'provider = :provider_filter';
            $params['provider_filter'] = $filters['provider'];
        } elseif (!empty($filters['providers']) && is_array($filters['providers'])) {
            $providerPlaceholders = [];
            foreach (array_values(array_unique(array_filter(array_map('strval', $filters['providers'])))) as $index => $provider) {
                $placeholder = 'provider_filter_' . $index;
                $providerPlaceholders[] = ':' . $placeholder;
                $params[$placeholder] = $provider;
            }
            if ($providerPlaceholders !== []) {
                $where[] = 'provider IN (' . implode(', ', $providerPlaceholders) . ')';
            }
        }
        if (!empty($filters['gender'])) {
            $where[] = 'gender = :gender';
            $params['gender'] = $filters['gender'];
        }
        if (!empty($filters['country']) && preg_match('/^[A-Z]{2}$/', (string) $filters['country']) === 1) {
            $where[] = 'country_code = :country_code';
            $params['country_code'] = strtoupper((string) $filters['country']);
        }
        if (!empty($filters['q'])) {
            $where[] = '(username LIKE :query_username OR display_name LIKE :query_display_name)';
            $params['query_username'] = '%' . $filters['q'] . '%';
            $params['query_display_name'] = '%' . $filters['q'] . '%';
        }
        if (!empty($filters['tag'])) {
            $where[] = 'tags_json LIKE :tag_json';
            $params['tag_json'] = '%"' . $filters['tag'] . '"%';
        }
        if (!empty($filters['age'])) {
            [$minimumAge, $maximumAge] = match ($filters['age']) {
                '18-20' => [18, 20],
                '21-25' => [21, 25],
                '26-30' => [26, 30],
                '31-35' => [31, 35],
                '36-40' => [36, 40],
                '41-plus' => [41, null],
                default => [null, null],
            };
            if ($minimumAge !== null) {
                $where[] = 'age >= :minimum_age';
                $params['minimum_age'] = $minimumAge;
            }
            if ($maximumAge !== null) {
                $where[] = 'age <= :maximum_age';
                $params['maximum_age'] = $maximumAge;
            }
        }
        if (!empty($filters['room_status'])) {
            $providersWithoutRoomStatus = is_array($filters['providers_without_room_status'] ?? null)
                ? array_values(array_unique(array_filter(array_map('strval', $filters['providers_without_room_status']))))
                : [];
            $capabilityProviderPlaceholders = [];
            if (in_array($filters['room_status'], ['public', 'unknown'], true)) {
                foreach ($providersWithoutRoomStatus as $index => $provider) {
                    $placeholder = 'status_capability_provider_' . $index;
                    $capabilityProviderPlaceholders[] = ':' . $placeholder;
                    $params[$placeholder] = $provider;
                }
            }
            if ($filters['room_status'] === 'public' && $capabilityProviderPlaceholders !== []) {
                $where[] = '(room_status = :room_status_filter OR provider IN ('
                    . implode(', ', $capabilityProviderPlaceholders) . '))';
            } elseif ($filters['room_status'] === 'unknown' && $capabilityProviderPlaceholders !== []) {
                $where[] = '(room_status = :room_status_filter AND provider NOT IN ('
                    . implode(', ', $capabilityProviderPlaceholders) . '))';
            } else {
                $where[] = 'room_status = :room_status_filter';
            }
            $params['room_status_filter'] = $filters['room_status'];
        }
        if (!empty($filters['new_only'])) {
            $newDays = max(1, min(90, (int) ($filters['new_days'] ?? 7)));
            $where[] = $this->newnessFilterSql($filters, $params, $newDays);
        }
        $geoCodes = is_array($filters['geo_codes'] ?? null) ? $filters['geo_codes'] : [];
        $hideRestrictedWhenUnknown = (bool) ($filters['hide_restricted_when_geo_unknown'] ?? false);
        $geoFilter = $this->geoVisibilitySql($geoCodes, $hideRestrictedWhenUnknown, $params, 'catalog_geo');
        if ($geoFilter !== '') {
            $where[] = trim(preg_replace('/^AND\s+/', '', $geoFilter) ?? $geoFilter);
        }

        return [$where, $params];
    }

    private function genderScopeSql(array &$params, string $prefix): string
    {
        $placeholders = [];
        foreach ($this->allowedPerformerTypes as $index => $type) {
            $placeholder = $prefix . '_' . $index;
            $placeholders[] = ':' . $placeholder;
            $params[$placeholder] = $type;
        }

        return $placeholders === []
            ? 'AND 1 = 0 '
            : 'AND gender IN (' . implode(', ', $placeholders) . ') ';
    }

    private function newnessFilterSql(array $filters, array &$params, int $newDays): string
    {
        $strategies = is_array($filters['new_strategies'] ?? null) ? $filters['new_strategies'] : [];
        $providers = [];
        if (!empty($filters['provider'])) {
            $providers[] = (string) $filters['provider'];
        } elseif (is_array($filters['providers'] ?? null)) {
            $providers = array_values(array_unique(array_filter(array_map('strval', $filters['providers']))));
        }

        if ($providers === []) {
            return "(provider_is_new = 1 OR (provider_is_new IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL {$newDays} DAY)))";
        }

        $clauses = [];
        foreach ($providers as $index => $provider) {
            $placeholder = 'new_provider_' . $index;
            $params[$placeholder] = $provider;
            $strategy = NewnessStrategy::normalize($strategies[$provider] ?? null);
            $condition = match ($strategy) {
                NewnessStrategy::PROVIDER => 'provider_is_new = 1',
                NewnessStrategy::FIRST_SEEN => "created_at >= DATE_SUB(NOW(), INTERVAL {$newDays} DAY)",
                default => "(provider_is_new = 1 OR (provider_is_new IS NULL AND created_at >= DATE_SUB(NOW(), INTERVAL {$newDays} DAY)))",
            };
            $clauses[] = '(provider = :' . $placeholder . ' AND ' . $condition . ')';
        }

        return '(' . implode(' OR ', $clauses) . ')';
    }

    private function newnessOrderSql(array $filters, array &$params): string
    {
        $strategies = is_array($filters['new_strategies'] ?? null) ? $filters['new_strategies'] : [];
        $providers = [];
        if (!empty($filters['provider'])) {
            $providers[] = (string) $filters['provider'];
        } elseif (is_array($filters['providers'] ?? null)) {
            $providers = array_values(array_unique(array_filter(array_map('strval', $filters['providers']))));
        }

        $officialFlagClauses = [];
        foreach ($providers as $index => $provider) {
            if (NewnessStrategy::normalize($strategies[$provider] ?? null) === NewnessStrategy::FIRST_SEEN) {
                continue;
            }
            $placeholder = 'new_order_provider_' . $index;
            $params[$placeholder] = $provider;
            $officialFlagClauses[] = '(provider = :' . $placeholder . ' AND provider_is_new = 1)';
        }

        if ($providers === []) {
            return 'COALESCE(provider_is_new, 0) DESC, created_at DESC, popularity_score DESC, viewers DESC';
        }
        if ($officialFlagClauses === []) {
            return 'created_at DESC, popularity_score DESC, viewers DESC';
        }

        return 'CASE WHEN ' . implode(' OR ', $officialFlagClauses)
            . ' THEN 1 ELSE 0 END DESC, created_at DESC, popularity_score DESC, viewers DESC';
    }

    private function replaceGeoBlocks(Performer $performer): void
    {
        $delete = $this->pdo->prepare(
            'DELETE FROM performer_geo_blocks WHERE provider = :provider AND provider_id = :provider_id'
        );
        $identity = ['provider' => $performer->provider, 'provider_id' => $performer->providerId];
        $delete->execute($identity);

        if ($performer->geoBlocks === []) {
            return;
        }

        $insert = $this->pdo->prepare(
            'INSERT IGNORE INTO performer_geo_blocks (provider, provider_id, country_code) '
            . 'VALUES (:provider, :provider_id, :country_code)'
        );
        foreach (array_values(array_unique($performer->geoBlocks)) as $countryCode) {
            $insert->execute($identity + ['country_code' => $countryCode]);
        }
    }

    private function geoVisibilitySql(
        array $geoCodes,
        bool $hideRestrictedWhenUnknown,
        array &$params,
        string $prefix
    ): string {
        $geoCodes = array_values(array_unique(array_filter(array_map('strval', $geoCodes))));
        if ($geoCodes === [] && !$hideRestrictedWhenUnknown) {
            return '';
        }

        if ($geoCodes === [] && $hideRestrictedWhenUnknown) {
            return 'AND has_geo_blocks = 0 ';
        }

        $codeFilter = '';
        if ($geoCodes !== []) {
            $placeholders = [];
            foreach ($geoCodes as $index => $code) {
                $placeholder = $prefix . '_' . $index;
                $placeholders[] = ':' . $placeholder;
                $params[$placeholder] = $code;
            }
            $codeFilter = ' AND geo.country_code IN (' . implode(', ', $placeholders) . ')';
        }

        return 'AND NOT EXISTS (SELECT 1 FROM performer_geo_blocks geo '
            . 'WHERE geo.provider = performers.provider '
            . 'AND geo.provider_id = performers.provider_id'
            . $codeFilter . ') ';
    }
}
