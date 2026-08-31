ALTER TABLE performers
    ADD INDEX idx_provider_username (provider, username),
    ADD INDEX idx_provider_online_public_viewers (provider, is_online, room_status, viewers);
