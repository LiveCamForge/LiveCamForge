ALTER TABLE affiliate_clicks
    ADD COLUMN interaction_type VARCHAR(30) NOT NULL DEFAULT 'click' AFTER track,
    ADD INDEX idx_click_provider_type_date (provider, interaction_type, clicked_at);

ALTER TABLE affiliate_conversions
    ADD COLUMN details_json JSON NULL AFTER event_timestamp;
