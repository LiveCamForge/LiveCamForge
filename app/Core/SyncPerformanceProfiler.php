<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

/** Lightweight profiler for CLI provider synchronization diagnostics. */
final class SyncPerformanceProfiler
{
    private static bool $enabled = false;
    /** @var array<string,float> */
    private static array $started = [];
    /** @var array<string,float> */
    private static array $durations = [];
    /** @var array<string,int|float|string|bool> */
    private static array $meta = [];

    public static function enable(bool $enabled = true): void
    {
        self::$enabled = $enabled;
        self::reset();
    }

    public static function reset(): void
    {
        self::$started = [];
        self::$durations = [];
        self::$meta = [];
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
        $seconds = microtime(true) - self::$started[$name];
        unset(self::$started[$name]);
        self::$durations[$name] = (self::$durations[$name] ?? 0.0) + $seconds;
        return $seconds;
    }

    public static function add(string $name, float $seconds): void
    {
        if (self::$enabled) {
            self::$durations[$name] = (self::$durations[$name] ?? 0.0) + max(0.0, $seconds);
        }
    }

    public static function increment(string $name, int $amount = 1): void
    {
        if (self::$enabled) {
            self::$meta[$name] = (int) (self::$meta[$name] ?? 0) + $amount;
        }
    }

    public static function meta(string $name, int|float|string|bool $value): void
    {
        if (self::$enabled) {
            self::$meta[$name] = $value;
        }
    }

    /** @return array{timings:array<string,float>,meta:array<string,int|float|string|bool>} */
    public static function report(): array
    {
        return ['timings' => self::$durations, 'meta' => self::$meta];
    }
}
