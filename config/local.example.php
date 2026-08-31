<?php

declare(strict_types=1);

// Copy to config/local.php. Only machine-specific values, credentials and
// security boundaries belong here. Day-to-day options are managed in Admin.
return [
    'demo_mode' => ['enabled' => false],
    'debug' => false,
    'admin' => [
        'enabled' => true,
        'session_name' => 'livecamforge_admin',
        // Set true on HTTPS sites behind a reverse proxy if PHP does not receive HTTPS=on.
        'secure_cookies' => null,
        'session_idle_timeout_seconds' => 3600,
        'login_max_attempts' => 5,
        'login_window_seconds' => 300,
        'login_lockout_seconds' => 600,
    ],
    'geo' => [
        // auto: hosting GeoIP variables/PHP extension; cloudflare: trusted CF headers.
        'source' => 'auto',
        // Local tests only while debug=true. Keep empty in production.
        'test_country' => '',
        'test_region' => '',
    ],
    'seo' => [
        // Final public HTTPS URL, for example https://example.com/livecamforge.
        'base_url' => '',
    ],
    'database' => [
        'host' => 'localhost', 'port' => 3306, 'name' => 'livecamforge',
        'user' => 'root', 'password' => '',
    ],
    'chaturbate' => [
        'wm' => '',
        'postback' => ['validation_salt' => '', 'require_checksum' => true],
    ],
    'bongacams' => ['campaign_id' => 0, 'client_ip' => ''],
    'cam4' => [
        'affiliate_id' => 0,
        'tune' => ['network_id' => 'cam4com', 'api_key' => ''],
    ],
    'livejasmin' => [
        'ps_id' => '', 'access_key' => '',
        'postback' => ['secret' => '', 'require_secret' => true],
    ],
    'stripchat' => [
        // Bearer key for the Models API for aggregators and public affiliate userId.
        'api_key' => '',
        'user_id' => '',
        // Optional override; 'all' is the distributed default and starts the stream automatically.
        'player' => ['autoplay' => 'all'],
        'postback' => ['secret' => '', 'require_secret' => true],
    ],
    'crakrevenue' => [
        'api_key' => '',
        'token' => '',
        'postback' => ['secret' => '', 'require_secret' => true],
    ],
];
