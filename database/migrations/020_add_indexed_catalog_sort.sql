ALTER TABLE performers
    ADD COLUMN watch_sort_score BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER popularity_score,
    ADD COLUMN provider_sort_score BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER watch_sort_score,
    ADD INDEX idx_online_watch_sort (is_online, watch_sort_score, id),
    ADD INDEX idx_online_provider_sort (is_online, provider_sort_score, id);

UPDATE performers
SET watch_sort_score =
        (CASE
            WHEN room_status = 'public' OR provider IN (
                'demo', 'bongacams',
                'crakrevenue_mfc', 'crakrevenue_streamate', 'crakrevenue_chaturbate',
                'crakrevenue_awempire', 'crakrevenue_stripchat',
                'crakrevenue_imlive', 'crakrevenue_bongacash'
            ) THEN 4500000000000000000
            ELSE 0
        END)
        + (CASE
            WHEN viewers IS NOT NULL THEN 4000000000000000000
                + LEAST(viewers, 4294967295) * 1000000
                + ROUND(COALESCE(popularity_score, 0) * 1000000)
            ELSE ROUND(COALESCE(popularity_score, 0) * 1000000)
        END),
    provider_sort_score =
        (CASE
            WHEN room_status = 'public' OR provider IN (
                'demo', 'bongacams',
                'crakrevenue_mfc', 'crakrevenue_streamate', 'crakrevenue_chaturbate',
                'crakrevenue_awempire', 'crakrevenue_stripchat',
                'crakrevenue_imlive', 'crakrevenue_bongacash'
            ) THEN 1000000000000000000
            ELSE 0
        END)
        + (CASE WHEN popularity_score IS NULL THEN 0 ELSE 20000000000000000 END)
        + ROUND(COALESCE(popularity_score, 0) * 1000000) * 10000000000
        + (CASE
            WHEN viewers IS NULL THEN 0
            ELSE 5000000000 + LEAST(viewers, 4294967295)
        END);
