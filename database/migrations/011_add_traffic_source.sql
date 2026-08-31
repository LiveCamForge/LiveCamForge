ALTER TABLE affiliate_clicks
    ADD COLUMN source_page VARCHAR(80) NOT NULL DEFAULT 'catalog' AFTER interaction_type,
    ADD INDEX idx_click_source_date (source_page, clicked_at);
