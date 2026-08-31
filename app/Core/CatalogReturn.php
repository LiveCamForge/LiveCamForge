<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final class CatalogReturn
{
    private const ALLOWED_KEYS = [
        'q',
        'provider',
        'gender',
        'country',
        'tag',
        'age',
        'room_status',
        'new',
        'sort',
        'per_page',
        'page',
        'tag_cursor',
        'tag_dir',
    ];

    public static function query(array $source): string
    {
        $candidate = $source;

        if (isset($source['return']) && is_string($source['return'])) {
            parse_str(substr($source['return'], 0, 1500), $parsed);
            $candidate = is_array($parsed) ? $parsed : [];
        }

        $safe = [];
        foreach (self::ALLOWED_KEYS as $key) {
            if (!isset($candidate[$key]) || !is_scalar($candidate[$key])) {
                continue;
            }

            $value = trim(substr((string) $candidate[$key], 0, 100));
            if ($value !== '') {
                $safe[$key] = $value;
            }
        }

        return http_build_query($safe);
    }

    public static function url(string $baseUrl, string $query): string
    {
        return $baseUrl . ($query !== '' ? '?' . $query : '');
    }
}
