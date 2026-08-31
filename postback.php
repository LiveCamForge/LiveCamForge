<?php

declare(strict_types=1);

use LiveCamForge\Database\Connection;
use LiveCamForge\Database\Migrator;
use LiveCamForge\Core\OperationalSettings;
use LiveCamForge\Core\SecurityHeaders;
use LiveCamForge\Core\Translator;
use LiveCamForge\Postbacks\PostbackHandlerFactory;
use LiveCamForge\Providers\ProviderFactory;
use LiveCamForge\Repositories\ClickRepository;
use LiveCamForge\Repositories\ConversionRepository;
use LiveCamForge\Repositories\SettingsRepository;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$root = __DIR__;
if (!is_file($root . '/config/local.php')) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'message' => 'Not installed']);
    exit;
}

try {
    $baseConfig = require $root . '/app/bootstrap.php';
    SecurityHeaders::sendBase();
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    $maximumPayloadBytes = 65536;
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($contentLength > $maximumPayloadBytes) {
        http_response_code(413);
        echo json_encode(['ok' => false, 'message' => 'Payload too large']);
        exit;
    }
    if (!in_array($method, ['GET', 'POST'], true)) {
        http_response_code(405);
        header('Allow: GET, POST');
        echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
        exit;
    }

    if ($method === 'POST') {
        $contentType = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
        if (str_contains($contentType, 'application/json')) {
            $rawBody = file_get_contents('php://input', false, null, 0, $maximumPayloadBytes + 1) ?: '';
            if (strlen($rawBody) > $maximumPayloadBytes) {
                http_response_code(413);
                echo json_encode(['ok' => false, 'message' => 'Payload too large']);
                exit;
            }
            $decoded = json_decode($rawBody, true);
            $payload = is_array($decoded) ? $decoded : null;
        } else {
            $payload = $_POST;
        }
        if (!is_array($payload)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'message' => 'Invalid payload']);
            exit;
        }
    } else {
        $payload = $_GET;
    }
    if ($method === 'POST') {
        // Providers may keep the static verification token in the endpoint URL
        // while sending transaction macros in the POST body. Body values win.
        $payload = array_replace($_GET, $payload);
    }

    $provider = strtolower(trim((string) ($_GET['provider'] ?? $payload['provider'] ?? 'chaturbate')));
    if (!PostbackHandlerFactory::supports($provider)) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'message' => 'Unsupported provider']);
        exit;
    }
    unset($payload['provider']);

    $pdo = Connection::make($baseConfig);
    Migrator::run($pdo, $root . '/database/migrations');
    $settings = new SettingsRepository($pdo);
    $languages = (new Translator($root . '/languages', 'en', 'en'))->available();
    $config = (new OperationalSettings(
        $settings,
        $baseConfig,
        ProviderFactory::availableNames(),
        array_keys($languages)
    ))->effectiveConfig();
    $handler = PostbackHandlerFactory::make(
        $provider,
        $config,
        new ClickRepository($pdo),
        new ConversionRepository($pdo),
    );
    $result = $handler->handle($payload);
    http_response_code($result['status']);
    echo json_encode($result['body'], JSON_UNESCAPED_SLASHES);
} catch (Throwable) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Postback processing failed']);
}
