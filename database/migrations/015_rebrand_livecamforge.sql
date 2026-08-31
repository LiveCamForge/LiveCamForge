UPDATE settings
SET setting_value = 'LiveCamForge'
WHERE setting_key = 'site.name' AND setting_value = 'Cam Engine';

UPDATE affiliate_clicks
SET track = 'livecamforge'
WHERE track = 'cam_engine';

UPDATE affiliate_conversions
SET track = 'livecamforge'
WHERE track = 'cam_engine';
