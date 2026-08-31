UPDATE performers SET room_status = 'public' WHERE room_status = 'unknown' AND is_online = 1;
