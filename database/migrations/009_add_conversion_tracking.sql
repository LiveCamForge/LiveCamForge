ALTER TABLE affiliate_clicks
    ADD COLUMN sid VARCHAR(120) NULL AFTER username,
    ADD COLUMN track VARCHAR(100) NOT NULL DEFAULT 'livecamforge' AFTER sid,
    ADD UNIQUE INDEX uq_click_provider_sid (provider, sid);

CREATE TABLE IF NOT EXISTS affiliate_conversions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider VARCHAR(50) NOT NULL,
    dedupe_key VARCHAR(255) NOT NULL,
    external_event_id VARCHAR(190) NULL,
    affiliate_click_id BIGINT UNSIGNED NULL,
    event_type VARCHAR(120) NOT NULL,
    sid VARCHAR(120) NULL,
    track VARCHAR(120) NULL,
    transaction_id VARCHAR(190) NULL,
    provider_click_id VARCHAR(190) NULL,
    payout DECIMAL(14,4) NOT NULL DEFAULT 0,
    amount DECIMAL(14,4) NOT NULL DEFAULT 0,
    currency VARCHAR(10) NOT NULL DEFAULT 'USD',
    token_amount INT NOT NULL DEFAULT 0,
    is_test TINYINT(1) NOT NULL DEFAULT 0,
    event_timestamp VARCHAR(80) NULL,
    received_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE INDEX uq_conversion_provider_dedupe (provider, dedupe_key),
    INDEX idx_conversion_provider_date (provider, received_at),
    INDEX idx_conversion_click (affiliate_click_id),
    CONSTRAINT fk_conversion_click
        FOREIGN KEY (affiliate_click_id)
        REFERENCES affiliate_clicks (id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
