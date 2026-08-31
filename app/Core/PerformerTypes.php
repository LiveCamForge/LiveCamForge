<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final class PerformerTypes
{
    public const WOMEN = 'f';
    public const MEN = 'm';
    public const TRANS = 't';
    public const COUPLES = 'c';
    public const VALUES = [self::WOMEN, self::MEN, self::TRANS, self::COUPLES];

    /** @return list<string> */
    public static function fromConfig(Config $config): array
    {
        return self::normalize($config->get('catalog.performer_types', self::VALUES));
    }

    /** @return list<string> */
    public static function normalize(mixed $value): array
    {
        $items = is_array($value)
            ? $value
            : (preg_split('/[\s,]+/', strtolower(trim((string) $value)), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $aliases = [
            'women' => self::WOMEN,
            'woman' => self::WOMEN,
            'female' => self::WOMEN,
            'men' => self::MEN,
            'man' => self::MEN,
            'male' => self::MEN,
            'transgender' => self::TRANS,
            'transgendered' => self::TRANS,
            'couple' => self::COUPLES,
            'couples' => self::COUPLES,
        ];
        $selected = [];
        foreach ($items as $item) {
            $type = strtolower(trim((string) $item));
            $type = $aliases[$type] ?? $type;
            if (in_array($type, self::VALUES, true)) {
                $selected[$type] = true;
            }
        }

        $normalized = array_values(array_filter(
            self::VALUES,
            static fn (string $type): bool => isset($selected[$type])
        ));

        // Invalid or missing file-level values must preserve the historical,
        // all-types behavior. Admin validation prevents saving an empty scope.
        return $normalized !== [] ? $normalized : self::VALUES;
    }

    public static function accepts(?string $gender, array $allowed): bool
    {
        return $gender !== null && in_array($gender, self::normalize($allowed), true);
    }
}
