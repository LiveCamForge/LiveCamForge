<?php

declare(strict_types=1);

namespace LiveCamForge\Database;

use PDO;
use RuntimeException;

final class Migrator
{
    public static function run(PDO $pdo, string $migrationsPath): void
    {
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS schema_migrations ('
            . 'migration VARCHAR(190) PRIMARY KEY, '
            . 'applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP'
            . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $applied = array_fill_keys($pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN), true);
        $paths = glob(rtrim($migrationsPath, '/') . '/*.sql') ?: [];
        sort($paths);

        foreach ($paths as $path) {
            $migration = basename($path);
            if (isset($applied[$migration])) {
                continue;
            }

            $sql = file_get_contents($path);
            if ($sql === false) {
                throw new RuntimeException("Unable to read migration: {$migration}");
            }

            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                $pdo->exec($statement);
            }

            $record = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (:migration)');
            $record->execute(['migration' => $migration]);
        }
    }
}

