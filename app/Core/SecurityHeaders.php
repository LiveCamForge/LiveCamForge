<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final class SecurityHeaders
{
    public static function sendBase(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('X-Frame-Options: SAMEORIGIN');
        header("Content-Security-Policy: frame-ancestors 'self'");
    }

    public static function sendPrivatePage(): void
    {
        self::sendBase();
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    }
}
