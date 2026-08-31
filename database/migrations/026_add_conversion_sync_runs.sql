CREATE TABLE IF NOT EXISTS conversion_sync_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    trigger_source VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,
    received_count INT UNSIGNED NULL,
    inserted_count INT UNSIGNED NULL,
    duplicate_count INT UNSIGNED NULL,
    attributed_count INT UNSIGNED NULL,
    duration_ms BIGINT UNSIGNED NULL,
    error_message TEXT NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    INDEX idx_conversion_sync_provider_started (provider, started_at),
    INDEX idx_conversion_sync_status_started (status, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
