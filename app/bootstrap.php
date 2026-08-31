<?php

declare(strict_types=1);

use LiveCamForge\Core\Config;
use LiveCamForge\Core\Logger;

define('LIVECAMFORGE_ROOT', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'LiveCamForge\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = LIVECAMFORGE_ROOT . '/app/' . $relative . '.php';
    if (is_file($path)) {
        require $path;
    }
});

$config = Config::load(LIVECAMFORGE_ROOT);
date_default_timezone_set((string) $config->get('timezone', 'UTC'));

$logger = new Logger(LIVECAMFORGE_ROOT . '/storage/logs/app.log');
set_exception_handler(static function (Throwable $exception) use ($config, $logger): void {
    $logger->error($exception->getMessage(), ['file' => $exception->getFile(), 'line' => $exception->getLine()]);
    http_response_code(500);
    $message = $config->get('debug', false) ? $exception->getMessage() : 'An error occurred.';
    echo '<h1>LiveCamForge</h1><p>' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</p>';
});

return $config;
