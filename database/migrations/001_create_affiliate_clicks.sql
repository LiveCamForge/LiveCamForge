CREATE TABLE IF NOT EXISTS affiliate_clicks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    provider_id VARCHAR(190) NOT NULL,
    username VARCHAR(190) NOT NULL,
    clicked_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_click_provider_date (provider, clicked_at),
    INDEX idx_click_performer (provider, provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

