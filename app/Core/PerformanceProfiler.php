<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

/** Lightweight request profiler for local/debug performance diagnostics. */
final class PerformanceProfiler
{
    private static bool $enabled = false;
    /** @var array<string,float> */
    private static array $started = [];
    /** @var array<string,float> */
    private static array $durations = [];
    /** @var array<string,string|int|float|bool> */
    private static array $meta = [];

    public static function enable(bool $enabled = true): void
    {
        self::$enabled = $enabled;
        if ($enabled) {
            self::$durations = [];
            self::$started = [];
            self::$meta = [];
        }
    }

    public static function enabled(): bool
    {
        return self::$enabled;
    }

    public static function start(string $name): void
    {
        if (self::$enabled) {
            self::$started[$name] = microtime(true);
        }
    }

    public static function stop(string $name): float
    {
        if (!self::$enabled || !isset(self::$started[$name])) {
            return 0.0;
        }
        $duration = microtime(true) - self::$started[$name];
        unset(self::$started[$name]);
        self::$durations[$name] = (self::$durations[$name] ?? 0.0) + $duration;
        return $duration;
    }

    public static function add(string $name, float $seconds): void
    {
        if (self::$enabled) {
            self::$durations[$name] = (self::$durations[$name] ?? 0.0) + max(0.0, $seconds);
        }
    }

    public static function meta(string $name, string|int|float|bool $value): void
    {
        if (self::$enabled) {
            self::$meta[$name] = $value;
        }
    }

    /** @return array{timings:array<string,float>,meta:array<string,string|int|float|bool>} */
    public static function report(): array
    {
        return ['timings' => self::$durations, 'meta' => self::$meta];
    }

    public static function headerValue(): string
    {
        $parts = [];
        foreach (self::$durations as $name => $seconds) {
            $safeName = preg_replace('/[^a-z0-9_.-]/i', '_', $name) ?: 'metric';
            $parts[] = $safeName . ';dur=' . number_format($seconds * 1000, 2, '.', '');
        }
        return implode(', ', $parts);
    }

    public static function metaHeaderValue(): string
    {
        $parts = [];
        foreach (self::$meta as $name => $value) {
            if (!in_array($name, ['countries_cache', 'count_cache', 'page_cache', 'result_count', 'page', 'per_page', 'count_strategy'], true)) {
                continue;
            }
            $safeName = preg_replace('/[^a-z0-9_.-]/i', '_', $name) ?: 'meta';
            $safeValue = preg_replace('/[^a-z0-9_.-]/i', '_', (string) $value) ?: 'unknown';
            $parts[] = $safeName . '=' . $safeValue;
        }
        return implode('; ', $parts);
    }

    public static function comment(): string
    {
        $json = json_encode(self::report(), JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        return "\n<!-- LiveCamForge performance\n" . ($json ?: '{}') . "\n-->\n";
    }
}
