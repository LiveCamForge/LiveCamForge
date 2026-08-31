<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final class Logger
{
    private const MAX_BYTES = 5_242_880;

    public function __construct(private string $path)
    {
    }

    public function error(string $message, array $context = []): void
    {
        $directory = dirname($this->path);
        if (!is_dir($directory) && !@mkdir($directory, 0775, true)) {
            return;
        }
        if (is_file($this->path) && (int) @filesize($this->path) >= self::MAX_BYTES) {
            @rename($this->path, $this->path . '.1');
        }

        $safeMessage = $this->redact(substr($message, 0, 4000));
        $safeContext = $this->sanitizeContext($context);
        $encoded = json_encode($safeContext, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
        $line = sprintf("[%s] ERROR %s %s\n", date('c'), $safeMessage, is_string($encoded) ? $encoded : '{}');
        @error_log($line, 3, $this->path);
    }

    private function sanitizeContext(array $context): array
    {
        $result = [];
        foreach (array_slice($context, 0, 40, true) as $key => $value) {
            $name = substr((string) $key, 0, 100);
            if (preg_match('/(?:pass|secret|token|api[_-]?key|access[_-]?key|authorization|cookie)/i', $name)) {
                $result[$name] = '[redacted]';
                continue;
            }
            if (is_scalar($value) || $value === null) {
                $result[$name] = $this->redact(substr((string) $value, 0, 1000));
            } else {
                $result[$name] = '[' . get_debug_type($value) . ']';
            }
        }
        return $result;
    }

    private function redact(string $value): string
    {
        $value = preg_replace('/([?&](?:api[_-]?key|access[_-]?key|token|secret|password)=)[^&\s]*/i', '$1[redacted]', $value) ?? $value;
        $value = preg_replace('/(Authorization:\s*(?:Bearer|Basic)\s+)[^\s]+/i', '$1[redacted]', $value) ?? $value;
        return $value;
    }
}
