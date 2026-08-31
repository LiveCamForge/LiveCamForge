CREATE TABLE IF NOT EXISTS performers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    provider_id VARCHAR(190) NOT NULL,
    username VARCHAR(190) NOT NULL,
    display_name VARCHAR(190) NOT NULL,
    gender VARCHAR(30) NULL,
    age TINYINT UNSIGNED NULL,
    image_url TEXT NULL,
    preview_url TEXT NULL,
    room_url TEXT NOT NULL,
    viewers INT UNSIGNED NOT NULL DEFAULT 0,
    tags_json JSON NOT NULL,
    is_online TINYINT(1) NOT NULL DEFAULT 1,
    last_seen_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_provider_performer (provider, provider_id),
    INDEX idx_online_viewers (is_online, viewers),
    INDEX idx_gender (gender)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(190) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS performer_geo_blocks (
    provider VARCHAR(50) NOT NULL,
    provider_id VARCHAR(190) NOT NULL,
    country_code VARCHAR(8) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (provider, provider_id, country_code),
    INDEX idx_geo_block_code (country_code, provider),
    CONSTRAINT fk_geo_block_performer
        FOREIGN KEY (provider, provider_id)
        REFERENCES performers (provider, provider_id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

CREATE TABLE IF NOT EXISTS sync_runs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    trigger_source VARCHAR(20) NOT NULL,
    status VARCHAR(20) NOT NULL,
    imported_count INT UNSIGNED NULL,
    duration_ms BIGINT UNSIGNED NULL,
    error_message TEXT NULL,
    started_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    INDEX idx_sync_provider_started (provider, started_at),
    INDEX idx_sync_status_started (status, started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
