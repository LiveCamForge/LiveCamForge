<?php

declare(strict_types=1);

namespace LiveCamForge\Providers\CrakRevenue;

use LiveCamForge\Core\Config;
use RuntimeException;

final class CrakRevenueClient
{
    public const BRANDS = ['mfc', 'streamate', 'chaturbate', 'bongacash', 'awempire', 'stripchat', 'imlive'];

    public function __construct(private Config $config)
    {
    }

    public function configured(): bool
    {
        return trim((string) $this->config->get('crakrevenue.api_key', '')) !== ''
            && trim((string) $this->config->get('crakrevenue.token', '')) !== '';
    }

    /** @return array<string, mixed> */
    public function fetchPage(
        string $brand,
        string $gender,
        int $page,
        int $size,
        ?int $timeoutSeconds = null
    ): array
    {
        if (!in_array($brand, self::BRANDS, true)) {
            throw new RuntimeException('Unsupported CrakRevenue brand.');
        }
        if (!in_array($gender, ['f', 'm', 't', 'c'], true)) {
            throw new RuntimeException('Unsupported CrakRevenue performer type.');
        }

        $apiKey = trim((string) $this->config->get('crakrevenue.api_key', ''));
        $token = trim((string) $this->config->get('crakrevenue.token', ''));
        if ($apiKey === '' || $token === '') {
            throw new RuntimeException('Configure the CrakRevenue api_key and token in Admin > Integrations first.');
        }

        $endpoint = trim((string) $this->config->get(
            'crakrevenue.endpoint',
            'https://performersext-api.pcvdaa.com/performers-ext'
        ));
        if (!$this->isApiEndpointAllowed($endpoint)) {
            throw new RuntimeException('The configured CrakRevenue endpoint is not allowed.');
        }

        $query = http_build_query([
            'token' => $token,
            'brands' => $brand,
            'page' => max(1, $page),
            'size' => max(1, min(100, $size)),
            'sorting' => 'score',
            'gender' => $gender,
            'live' => 'true',
            'lang' => $this->language(),
        ], '', '&', PHP_QUERY_RFC3986);
        $context = stream_context_create(['http' => [
            'timeout' => max(5, min(60, $timeoutSeconds
                ?? (int) $this->config->get('crakrevenue.timeout_seconds', 25))),
            'user_agent' => trim((string) $this->config->get(
                'crakrevenue.user_agent',
                'LiveCamForge/' . (string) $this->config->get('version', 'unknown') . ' (+https://livecamforge.com)'
            )),
            'header' => "Accept: application/json\r\nx-api-key: {$apiKey}\r\n",
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($endpoint . '?' . $query, false, $context);
        if (!is_string($body)) {
            throw new RuntimeException('Unable to contact the CrakRevenue API.');
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('CrakRevenue returned an invalid response.');
        }
        if (isset($decoded['message']) && !isset($decoded['performers'])) {
            throw new RuntimeException('CrakRevenue rejected the request: ' . substr((string) $decoded['message'], 0, 160));
        }
        if (!isset($decoded['performers']) || !is_array($decoded['performers'])) {
            throw new RuntimeException('CrakRevenue returned a response without a performers list.');
        }

        return $decoded;
    }

    /** @return array<string, bool> */
    public function testBrands(): array
    {
        return array_map(
            static fn (string $status): bool => $status === 'authorized',
            $this->testBrandsDetailed()
        );
    }

    /** @return array<string, 'authorized'|'not_authorized'|'error'> */
    public function testBrandsDetailed(): array
    {
        $results = [];
        foreach (self::BRANDS as $brand) {
            try {
                $this->fetchPage($brand, 'f', 1, 1, 8);
                $results[$brand] = 'authorized';
            } catch (\Throwable $exception) {
                $message = strtolower($exception->getMessage());
                $results[$brand] = str_contains($message, 'brands not allowed')
                    || str_contains($message, 'brands invalid')
                    ? 'not_authorized'
                    : 'error';
            }
        }

        return $results;
    }

    private function language(): string
    {
        $locale = strtolower(substr(trim((string) $this->config->get('locale', 'en')), 0, 2));

        return in_array($locale, ['en', 'es', 'fr', 'it', 'de', 'pt'], true) ? $locale : 'en';
    }

    private function isApiEndpointAllowed(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'performersext-api.pcvdaa.com'
            && rtrim((string) parse_url($url, PHP_URL_PATH), '/') === '/performers-ext'
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
