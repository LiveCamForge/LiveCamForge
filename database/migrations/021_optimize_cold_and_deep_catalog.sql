ALTER TABLE performers
    ADD COLUMN has_geo_blocks TINYINT(1) NOT NULL DEFAULT 0 AFTER provider_is_new,
    DROP INDEX idx_online_watch_sort,
    DROP INDEX idx_online_provider_sort,
    ADD INDEX idx_online_watch_sort
        (is_online, watch_sort_score, id, provider, gender, provider_id),
    ADD INDEX idx_online_provider_sort
        (is_online, provider_sort_score, id, provider, gender, provider_id),
    ADD INDEX idx_online_geo_watch_sort
        (is_online, has_geo_blocks, watch_sort_score, id, provider, gender, provider_id),
    ADD INDEX idx_online_geo_provider_sort
        (is_online, has_geo_blocks, provider_sort_score, id, provider, gender, provider_id),
    ADD INDEX idx_online_geo_country
        (is_online, has_geo_blocks, country_code, provider, gender, provider_id),
    ADD INDEX idx_online_geo_catalog
        (is_online, has_geo_blocks, gender, provider),
    ADD INDEX idx_online_geo_new
        (is_online, has_geo_blocks, provider_is_new, created_at, provider, gender,
         room_status, popularity_score, viewers, id);

UPDATE performers p
SET has_geo_blocks = CASE WHEN EXISTS (
    SELECT 1
    FROM performer_geo_blocks geo
    WHERE geo.provider = p.provider
      AND geo.provider_id = p.provider_id
) THEN 1 ELSE 0 END;
