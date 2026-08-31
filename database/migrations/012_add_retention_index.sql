ALTER TABLE performers
    ADD INDEX idx_provider_offline_last_seen (provider, is_online, last_seen_at);
