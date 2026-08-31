<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final class NewnessStrategy
{
    public const AUTOMATIC = 'automatic';
    public const PROVIDER = 'provider';
    public const FIRST_SEEN = 'first_seen';

    /** @return list<string> */
    public static function values(): array
    {
        return [self::AUTOMATIC, self::PROVIDER, self::FIRST_SEEN];
    }

    public static function normalize(mixed $value): string
    {
        $value = strtolower(trim((string) $value));

        return in_array($value, self::values(), true) ? $value : self::AUTOMATIC;
    }

    public static function for(Config $config, string $provider): string
    {
        $strategies = $config->get('catalog.new_strategies', []);
        $strategies = is_array($strategies) ? $strategies : [];
        $provider = strtolower(trim($provider));

        return self::normalize($strategies[$provider] ?? $strategies['default'] ?? self::AUTOMATIC);
    }
}
