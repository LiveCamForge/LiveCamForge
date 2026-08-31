<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

use RuntimeException;

final class Translator
{
    private array $messages;
    private array $fallbackMessages;

    public function __construct(
        private string $languagesPath,
        private string $locale = 'en',
        private string $fallbackLocale = 'en',
    ) {
        $this->locale = $this->sanitizeLocale($this->locale);
        $this->fallbackLocale = $this->sanitizeLocale($this->fallbackLocale);
        $this->fallbackMessages = $this->load($this->fallbackLocale);
        $this->messages = $this->locale === $this->fallbackLocale
            ? $this->fallbackMessages
            : $this->load($this->locale, false);
    }

    public function get(string $key, array $replace = []): string
    {
        $value = $this->messages[$key] ?? $this->fallbackMessages[$key] ?? $key;
        if (!is_string($value)) {
            return $key;
        }

        $replacements = [];
        foreach ($replace as $name => $replacement) {
            $replacements['{' . $name . '}'] = (string) $replacement;
        }

        return strtr($value, $replacements);
    }

    public function locale(): string
    {
        return $this->locale;
    }

    public function fallbackLocale(): string
    {
        return $this->fallbackLocale;
    }

    public function available(): array
    {
        $languages = [];
        foreach (glob(rtrim($this->languagesPath, '/') . '/*.json') ?: [] as $path) {
            $code = pathinfo($path, PATHINFO_FILENAME);
            if (preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $code) !== 1) {
                continue;
            }
            $messages = $this->decode($path);
            $languages[$code] = [
                'code' => $code,
                'name' => (string) ($messages['_meta.name'] ?? strtoupper($code)),
                'author' => (string) ($messages['_meta.author'] ?? ''),
                'version' => (string) ($messages['_meta.version'] ?? ''),
            ];
        }

        ksort($languages);
        return $languages;
    }

    private function load(string $locale, bool $required = true): array
    {
        $path = rtrim($this->languagesPath, '/') . '/' . $locale . '.json';
        if (!is_file($path)) {
            if ($required) {
                throw new RuntimeException("Language pack not found: {$locale}");
            }
            return [];
        }

        return $this->decode($path);
    }

    private function decode(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read language pack.');
        }

        $messages = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($messages)) {
            throw new RuntimeException('Invalid language pack.');
        }

        return $messages;
    }

    private function sanitizeLocale(string $locale): string
    {
        return preg_match('/^[a-z]{2}(?:-[A-Z]{2})?$/', $locale) ? $locale : 'en';
    }
}
