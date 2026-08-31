<?php

declare(strict_types=1);

namespace LiveCamForge\Repositories;

use PDO;

final class SyncRunRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function start(string $provider, string $trigger): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO sync_runs (provider, trigger_source, status, started_at) "
            . "VALUES (:provider, :trigger_source, 'running', NOW())"
        );
        $statement->execute(['provider' => $provider, 'trigger_source' => $trigger]);

        return (int) $this->pdo->lastInsertId();
    }

    public function succeed(int $id, int $imported): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE sync_runs SET status = 'success', imported_count = :imported_count, finished_at = NOW(), "
            . 'duration_ms = TIMESTAMPDIFF(MICROSECOND, started_at, NOW()) DIV 1000 WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'imported_count' => $imported]);
    }

    public function fail(int $id, string $message): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE sync_runs SET status = 'failed', error_message = :error_message, finished_at = NOW(), "
            . 'duration_ms = TIMESTAMPDIFF(MICROSECOND, started_at, NOW()) DIV 1000 WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'error_message' => substr($message, 0, 2000)]);
    }

    public function recent(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return $this->pdo->query('SELECT * FROM sync_runs ORDER BY id DESC LIMIT ' . $limit)->fetchAll();
    }

    public function prune(int $historyDays): int
    {
        $historyDays = max(1, min(3650, $historyDays));
        return $this->pdo->exec(
            "DELETE FROM sync_runs WHERE started_at < DATE_SUB(NOW(), INTERVAL {$historyDays} DAY)"
        );
    }

    public function interruptStaleRunning(int $staleMinutes = 30): int
    {
        $staleMinutes = max(5, min(1440, $staleMinutes));
        $message = 'Synchronization was interrupted before LiveCamForge could record a normal completion.';
        $statement = $this->pdo->prepare(
            "UPDATE sync_runs SET status = 'interrupted', error_message = :error_message, finished_at = NOW(), "
            . 'duration_ms = TIMESTAMPDIFF(MICROSECOND, started_at, NOW()) DIV 1000 '
            . "WHERE status = 'running' AND started_at < DATE_SUB(NOW(), INTERVAL {$staleMinutes} MINUTE)"
        );
        $statement->execute(['error_message' => $message]);

        return $statement->rowCount();
    }

    public function latestSuccessful(string $provider): ?array
    {
        $statement = $this->pdo->prepare(
            "SELECT * FROM sync_runs WHERE provider = :provider AND status = 'success' ORDER BY id DESC LIMIT 1"
        );
        $statement->execute(['provider' => $provider]);
        $row = $statement->fetch();

        return is_array($row) ? $row : null;
    }

    /** @param list<string> $providers @return array<string,array<string,mixed>> */
    public function latestSuccessfulByProviders(array $providers): array
    {
        $providers = array_values(array_unique(array_filter(array_map('strval', $providers))));
        if ($providers === []) {
            return [];
        }
        $params = [];
        $placeholders = [];
        foreach ($providers as $index => $provider) {
            $placeholder = 'latest_provider_' . $index;
            $placeholders[] = ':' . $placeholder;
            $params[$placeholder] = $provider;
        }
        $statement = $this->pdo->prepare(
            "SELECT runs.* FROM sync_runs runs INNER JOIN ("
            . "SELECT provider, MAX(id) AS latest_id FROM sync_runs "
            . "WHERE status = 'success' AND provider IN (" . implode(', ', $placeholders) . ") "
            . 'GROUP BY provider) latest ON latest.latest_id = runs.id'
        );
        $statement->execute($params);
        $result = [];
        foreach ($statement->fetchAll() as $row) {
            if (is_array($row)) {
                $result[(string) $row['provider']] = $row;
            }
        }

        return $result;
    }
}
