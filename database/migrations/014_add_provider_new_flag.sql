ALTER TABLE performers
    ADD COLUMN provider_is_new TINYINT(1) NULL AFTER tags_json,
    ADD INDEX idx_provider_online_new (provider, is_online, provider_is_new, created_at);
