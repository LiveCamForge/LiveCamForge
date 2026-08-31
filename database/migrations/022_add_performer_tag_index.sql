CREATE TABLE IF NOT EXISTS performer_tags (
    performer_id BIGINT UNSIGNED NOT NULL,
    tag VARCHAR(80) NOT NULL,
    PRIMARY KEY (tag, performer_id),
    INDEX idx_performer_tag_performer (performer_id),
    CONSTRAINT fk_performer_tag_performer
        FOREIGN KEY (performer_id)
        REFERENCES performers (id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS performer_tag_index_state (
    provider VARCHAR(50) NOT NULL PRIMARY KEY,
    performer_count INT UNSIGNED NOT NULL DEFAULT 0,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
