<?php

declare(strict_types=1);

namespace LiveCamForge\Core;

final readonly class VisitorGeo
{
    private function __construct(
        public ?string $country,
        public ?string $region,
        public string $source,
        public array $languages,
    ) {
    }

    public static function detect(Config $config, array $server): self
    {
        $languages = self::normalizeLanguages((string) ($server['HTTP_ACCEPT_LANGUAGE'] ?? ''));
        $testCountry = self::normalizeCountry((string) $config->get('geo.test_country', ''));
        $testRegion = self::normalizeRegion((string) $config->get('geo.test_region', ''));
        if ((bool) $config->get('debug', false) && $testCountry !== null) {
            return new self($testCountry, $testRegion, 'test', $languages);
        }

        $source = strtolower(trim((string) $config->get('geo.source', 'auto')));
        if ($source === 'cloudflare') {
            $country = self::normalizeCountry((string) ($server['HTTP_CF_IPCOUNTRY'] ?? ''));
            $region = self::normalizeRegion((string) ($server['HTTP_CF_REGION_CODE'] ?? ''));
            if ($country !== null && !in_array($country, ['XX', 'T1'], true)) {
                return new self($country, $region, 'cloudflare', $languages);
            }
            return new self(null, null, 'unknown', $languages);
        }

        if (in_array($source, ['auto', 'server'], true)) {
            $country = self::normalizeCountry((string) ($server['GEOIP_COUNTRY_CODE'] ?? ''));
            $region = self::normalizeRegion((string) ($server['GEOIP_REGION'] ?? ''));
            if ($country !== null) {
                return new self($country, $region, 'server', $languages);
            }
            if ($source === 'server') {
                return new self(null, null, 'unknown', $languages);
            }
        }

        if (in_array($source, ['auto', 'geoip'], true) && function_exists('geoip_record_by_name')) {
            $ip = filter_var($server['REMOTE_ADDR'] ?? null, FILTER_VALIDATE_IP);
            if (is_string($ip)) {
                $record = @geoip_record_by_name($ip);
                if (is_array($record)) {
                    $country = self::normalizeCountry((string) ($record['country_code'] ?? ''));
                    $region = self::normalizeRegion((string) ($record['region'] ?? ''));
                    if ($country !== null) {
                        return new self($country, $region, 'geoip', $languages);
                    }
                }
            }
        }

        return new self(null, null, 'unknown', $languages);
    }

    public function known(): bool
    {
        return $this->country !== null;
    }

    public function complete(): bool
    {
        // Stripchat may restrict a performer by country, region or browser
        // language. Treat the visitor position as complete only when all
        // three dimensions are available; otherwise restricted performers
        // remain hidden by SAFE MODE instead of risking a regional bypass.
        return $this->country !== null
            && $this->region !== null
            && $this->languages !== [];
    }

    /** @return list<string> */
    public function restrictionCodes(): array
    {
        if ($this->country === null) {
            return [];
        }

        $codes = [$this->country];
        if ($this->region !== null) {
            $codes[] = $this->country . ':' . $this->region;
        }

        foreach ($this->languages as $language) {
            $codes[] = 'LANG:' . strtoupper($language);
        }

        return $codes;
    }

    public static function normalizeBlock(mixed $value): ?string
    {
        $code = strtoupper(trim((string) $value));
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if ($code === '') {
            return null;
        }
        [$country, $region] = array_pad(explode(':', $code, 2), 2, null);
        $country = self::normalizeCountry($country);
        $region = self::normalizeRegion((string) $region);
        if ($country === null) {
            return null;
        }
        if ($region !== null) {
            return $country . ':' . $region;
        }

        return $country;
    }

    public static function normalizeLanguageBlock(mixed $value): ?string
    {
        $language = strtolower(trim((string) $value));
        $language = explode('-', str_replace('_', '-', $language), 2)[0];

        return preg_match('/^[a-z]{2,3}$/', $language) === 1
            ? 'LANG:' . strtoupper($language)
            : null;
    }

    private static function normalizeCountry(string $value): ?string
    {
        $country = strtoupper(trim($value));
        $country = match ($country) {
            'UK' => 'GB',
            default => $country,
        };

        return preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : null;
    }

    private static function normalizeRegion(string $value): ?string
    {
        $region = strtoupper(trim($value));

        return preg_match('/^[A-Z0-9]{1,3}$/', $region) === 1 ? $region : null;
    }

    /** @return list<string> */
    private static function normalizeLanguages(string $header): array
    {
        $languages = [];
        foreach (explode(',', $header) as $part) {
            $language = trim(explode(';', $part, 2)[0]);
            $code = self::normalizeLanguageBlock($language);
            if ($code !== null) {
                $languages[strtolower(substr($code, 5))] = true;
            }
        }

        return array_keys($languages);
    }
}
