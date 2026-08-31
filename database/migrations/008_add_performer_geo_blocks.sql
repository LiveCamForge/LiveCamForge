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

-- Existing LiveJasmin rows predate geo restrictions and must not be exposed
-- until a fresh synchronization stores their bannedCountries values.
UPDATE performers SET is_online = 0 WHERE provider = 'livejasmin';
