<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final class TagCursor
{
    public static function encode(array $performer, string $sort): string
    {
        $scoreKey = $sort === 'provider_popular' ? 'provider_sort_score' : 'watch_sort_score';
        $score = (string) ($performer[$scoreKey] ?? '');
        $id = (string) ($performer['id'] ?? '');
        if (!ctype_digit($score) || !ctype_digit($id)) {
            return '';
        }

        $json = json_encode(['s' => $score, 'i' => $id], JSON_UNESCAPED_SLASHES);
        if (!is_string($json)) {
            return '';
        }

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    public static function decode(string $value): ?array
    {
        if ($value === '' || strlen($value) > 120 || preg_match('/^[A-Za-z0-9_-]+$/', $value) !== 1) {
            return null;
        }
        $padding = (4 - strlen($value) % 4) % 4;
        $decoded = base64_decode(strtr($value . str_repeat('=', $padding), '-_', '+/'), true);
        if (!is_string($decoded)) {
            return null;
        }
        $data = json_decode($decoded, true);
        if (!is_array($data)) {
            return null;
        }
        $score = (string) ($data['s'] ?? '');
        $id = (string) ($data['i'] ?? '');
        if (!ctype_digit($score) || !ctype_digit($id)) {
            return null;
        }

        return ['score' => $score, 'id' => (int) $id];
    }
}
