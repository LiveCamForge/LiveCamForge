ALTER TABLE performers
    ADD INDEX idx_online_provider_gender (is_online, provider, gender);

ALTER TABLE sync_runs
    ADD INDEX idx_sync_provider_status_id (provider, status, id);
