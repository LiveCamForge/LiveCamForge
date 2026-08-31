ALTER TABLE performers
    ADD INDEX idx_provider_id (provider, id),
    ADD INDEX idx_provider_online_id (provider, is_online, id);
