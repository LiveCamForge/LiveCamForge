<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final class Config
{
    public function __construct(private array $values)
    {
    }

    public static function load(string $root): self
    {
        $base = require $root . '/config/app.php';
        $localPath = $root . '/config/local.php';
        $local = is_file($localPath) ? require $localPath : [];

        return new self(self::merge($base, is_array($local) ? $local : []));
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function withOverrides(array $overrides): self
    {
        return new self(self::merge($this->values, $overrides));
    }

    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            $baseValue = $base[$key] ?? null;
            // Associative sections are merged recursively, while ordered
            // option lists must be replaced as a whole. Merging list indexes
            // would retain trailing defaults and make checkbox subsets unable
            // to disable values such as performer types or feed categories.
            $base[$key] = is_array($value)
                && is_array($baseValue)
                && !array_is_list($value)
                && !array_is_list($baseValue)
                    ? self::merge($baseValue, $value)
                    : $value;
        }

        return $base;
    }
}
