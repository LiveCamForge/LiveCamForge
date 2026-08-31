ALTER TABLE performers
    ADD COLUMN structural_hash CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL AFTER has_geo_blocks;

UPDATE performers
SET structural_hash = MD5(CONCAT_WS(CHAR(31),
    IFNULL(username, '<NULL>'),
    IFNULL(display_name, '<NULL>'),
    IFNULL(gender, '<NULL>'),
    IFNULL(CAST(age AS CHAR), '<NULL>'),
    IFNULL(country_code, '<NULL>'),
    IFNULL(CAST(provider_is_new AS CHAR), '<NULL>'),
    IFNULL(CAST(has_geo_blocks AS CHAR), '<NULL>')
));

ALTER TABLE performers
    MODIFY structural_hash CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL;
