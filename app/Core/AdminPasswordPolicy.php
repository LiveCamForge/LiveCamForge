<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final class AdminPasswordPolicy
{
    public const MIN_LENGTH = 12;

    public static function isWeak(string $password, string $username = '', string $siteName = 'LiveCamForge'): bool
    {
        if (strlen($password) < self::MIN_LENGTH) {
            return true;
        }

        $normalized = strtolower(trim($password));
        $common = [
            'password1234', 'password123!', 'administrator', 'admin12345678',
            'qwerty123456', '123456789012', 'livecamforge', 'letmein123456',
        ];
        if (in_array($normalized, $common, true)) {
            return true;
        }

        foreach ([$username, $siteName] as $personalValue) {
            $personalValue = strtolower(trim($personalValue));
            if ($personalValue !== '' && $normalized === $personalValue) {
                return true;
            }
        }

        return preg_match('/^(.)\\1{11,}$/', $password) === 1
            || preg_match('/^\\d{12,}$/', $password) === 1;
    }
}
