UPDATE performers SET age = NULL WHERE age = 99;

ALTER TABLE performers
    ADD INDEX idx_provider_online_age (provider, is_online, age),
    ADD INDEX idx_provider_online_status (provider, is_online, room_status),
    ADD INDEX idx_provider_online_created (provider, is_online, created_at);
