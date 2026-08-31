CREATE TABLE IF NOT EXISTS traffic_landings (
    slug VARCHAR(61) PRIMARY KEY,
    is_standard TINYINT(1) NOT NULL DEFAULT 0,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    indexable TINYINT(1) NOT NULL DEFAULT 0,
    show_in_navigation TINYINT(1) NOT NULL DEFAULT 0,
    minimum_results SMALLINT UNSIGNED NOT NULL DEFAULT 8,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 100,
    filters_json JSON NOT NULL,
    content_json JSON NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_traffic_landing_visibility (enabled, indexable, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
