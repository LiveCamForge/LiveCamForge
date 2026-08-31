<?php

declare(strict_types=1);

namespace LiveCamForge\Repositories;

use PDO;

final class SettingsRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    public function get(string $key): ?string
    {
        $statement = $this->pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = :setting_key LIMIT 1');
        $statement->execute(['setting_key' => $key]);
        $value = $statement->fetchColumn();

        return is_string($value) ? $value : null;
    }

    public function set(string $key, ?string $value): void
    {
        $statement = $this->pdo->prepare(
            'INSERT INTO settings (setting_key, setting_value) VALUES (:setting_key, :setting_value) '
            . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
        );
        $statement->execute(['setting_key' => $key, 'setting_value' => $value]);
    }

    public function setMany(array $values): void
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        }

        try {
            foreach ($values as $key => $value) {
                $this->set((string) $key, $value === null ? null : (string) $value);
            }
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->commit();
            }
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $exception;
        }
    }
}
