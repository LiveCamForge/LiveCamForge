<?php

declare(strict_types=1);

namespace LiveCamForge\Repositories;

use PDO;

final class ConversionSyncRunRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function start(string $provider, string $trigger): int
    {
        $statement = $this->pdo->prepare(
            "INSERT INTO conversion_sync_runs (provider, trigger_source, status, started_at) "
            . "VALUES (:provider, :trigger_source, 'running', NOW())"
        );
        $statement->execute(['provider' => $provider, 'trigger_source' => $trigger]);

        return (int) $this->pdo->lastInsertId();
    }

    /** @param array{received:int,inserted:int,duplicates:int,attributed:int} $stats */
    public function succeed(int $id, array $stats): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE conversion_sync_runs SET status = 'success', received_count = :received_count, "
            . 'inserted_count = :inserted_count, duplicate_count = :duplicate_count, '
            . 'attributed_count = :attributed_count, finished_at = NOW(), '
            . 'duration_ms = TIMESTAMPDIFF(MICROSECOND, started_at, NOW()) DIV 1000 WHERE id = :id'
        );
        $statement->execute([
            'id' => $id,
            'received_count' => $stats['received'],
            'inserted_count' => $stats['inserted'],
            'duplicate_count' => $stats['duplicates'],
            'attributed_count' => $stats['attributed'],
        ]);
    }

    public function fail(int $id, string $message): void
    {
        $statement = $this->pdo->prepare(
            "UPDATE conversion_sync_runs SET status = 'failed', error_message = :error_message, finished_at = NOW(), "
            . 'duration_ms = TIMESTAMPDIFF(MICROSECOND, started_at, NOW()) DIV 1000 WHERE id = :id'
        );
        $statement->execute(['id' => $id, 'error_message' => substr($message, 0, 2000)]);
    }

    public function recent(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return $this->pdo->query('SELECT * FROM conversion_sync_runs ORDER BY id DESC LIMIT ' . $limit)->fetchAll();
    }

    public function prune(int $historyDays = 30): int
    {
        $historyDays = max(1, min(3650, $historyDays));
        return $this->pdo->exec(
            "DELETE FROM conversion_sync_runs WHERE started_at < DATE_SUB(NOW(), INTERVAL {$historyDays} DAY)"
        );
    }

    public function interruptStaleRunning(int $staleMinutes = 60): int
    {
        $staleMinutes = max(5, min(1440, $staleMinutes));
        $message = 'Conversion polling was interrupted before LiveCamForge could record a normal completion.';
        $statement = $this->pdo->prepare(
            "UPDATE conversion_sync_runs SET status = 'interrupted', error_message = :error_message, finished_at = NOW(), "
            . 'duration_ms = TIMESTAMPDIFF(MICROSECOND, started_at, NOW()) DIV 1000 '
            . "WHERE status = 'running' AND started_at < DATE_SUB(NOW(), INTERVAL {$staleMinutes} MINUTE)"
        );
        $statement->execute(['error_message' => $message]);

        return $statement->rowCount();
    }
}
