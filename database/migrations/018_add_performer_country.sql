ALTER TABLE performers
    ADD COLUMN country_code CHAR(2) NULL AFTER age,
    ADD INDEX idx_country_online (country_code, is_online, gender, provider);
