ALTER TABLE performers
    ADD INDEX idx_catalog_online_gender_provider (is_online, gender, provider, viewers);
