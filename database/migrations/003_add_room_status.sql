ALTER TABLE performers ADD COLUMN room_status VARCHAR(30) NOT NULL DEFAULT 'unknown' AFTER embed_url;
