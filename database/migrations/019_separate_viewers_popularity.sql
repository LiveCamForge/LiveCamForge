ALTER TABLE performers
    MODIFY COLUMN viewers INT UNSIGNED NULL DEFAULT NULL,
    ADD COLUMN popularity_score DECIMAL(9,6) NULL AFTER viewers,
    ADD INDEX idx_online_popularity (is_online, popularity_score);

UPDATE performers
SET popularity_score = LEAST(1, GREATEST(0, viewers / 1000000)),
    viewers = NULL
WHERE LEFT(provider, 12) = 'crakrevenue_';

UPDATE performers
SET viewers = NULL
WHERE provider = 'livejasmin';
