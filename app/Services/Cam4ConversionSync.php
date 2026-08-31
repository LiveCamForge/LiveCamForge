<?php

declare(strict_types=1);

namespace LiveCamForge\Services;

use DateTimeImmutable;
use DateTimeZone;
use LiveCamForge\Core\Config;
use LiveCamForge\Repositories\ClickRepository;
use LiveCamForge\Repositories\ConversionRepository;
use RuntimeException;

final class Cam4ConversionSync
{
    public function __construct(
        private Config $config,
        private ClickRepository $clicks,
        private ConversionRepository $conversions,
    ) {
    }

    /** @return array{received:int,inserted:int,duplicates:int,attributed:int} */
    public function run(?string $startDate = null, ?string $endDate = null): array
    {
        $apiKey = trim((string) $this->config->get('cam4.tune.api_key', ''));
        $networkId = trim((string) $this->config->get('cam4.tune.network_id', 'cam4com'));
        if ($apiKey === '' || $networkId === '') {
            throw new RuntimeException('Configure cam4.tune.api_key and cam4.tune.network_id in config/local.php.');
        }

        $timezone = new DateTimeZone((string) $this->config->get('timezone', 'UTC'));
        $today = new DateTimeImmutable('today', $timezone);
        $lookback = max(1, min(30, (int) $this->config->get('cam4.tune.lookback_days', 3)));
        $startDate ??= $today->modify('-' . ($lookback - 1) . ' days')->format('Y-m-d');
        $endDate ??= $today->format('Y-m-d');
        $this->assertDateWindow($startDate, $endDate);

        $page = 1;
        $pageSize = max(1, min(1000, (int) $this->config->get('cam4.tune.page_size', 100)));
        $stats = ['received' => 0, 'inserted' => 0, 'duplicates' => 0, 'attributed' => 0];

        while ($page <= 100) {
            $payload = $this->request($apiKey, $networkId, $startDate, $endDate, $page, $pageSize);
            $data = $payload['response']['data'] ?? null;
            $rows = is_array($data) && is_array($data['data'] ?? null) ? $data['data'] : [];
            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $stat = is_array($row['Stat'] ?? null) ? $row['Stat'] : $row;
                $externalId = $this->token($stat['id'] ?? null, 190);
                if ($externalId === '') {
                    continue;
                }
                $stats['received']++;
                $sid = $this->token($stat['affiliate_info1'] ?? null, 120);
                $click = $sid !== '' ? $this->clicks->findBySid('cam4', $sid) : null;
                $goalId = $this->token($stat['goal_id'] ?? null, 80);
                $status = $this->token($stat['conversion_status'] ?? null, 80);
                $eventType = $goalId !== '' ? 'goal_' . $goalId : 'conversion';
                if ($status !== '') {
                    $eventType .= '_' . strtolower($status);
                }
                $stored = $this->conversions->insert([
                    'provider' => 'cam4',
                    'dedupe_key' => 'tune:' . $externalId,
                    'external_event_id' => $externalId,
                    'affiliate_click_id' => $click['id'] ?? null,
                    'event_type' => substr($eventType, 0, 120),
                    'sid' => $sid ?: null,
                    'track' => 'tune',
                    'transaction_id' => null,
                    'provider_click_id' => null,
                    'payout' => $this->number($stat['approved_payout'] ?? null),
                    'amount' => $this->number($stat['sale_amount'] ?? null),
                    'currency' => strtoupper($this->token($stat['currency'] ?? 'USD', 10)) ?: 'USD',
                    'token_amount' => 0,
                    'is_test' => 0,
                    'event_timestamp' => substr(trim((string) ($stat['datetime'] ?? '')), 0, 80) ?: null,
                    'details_json' => json_encode([
                        'affiliate_network' => 'tune',
                        'offer_id' => $stat['offer_id'] ?? null,
                        'goal_id' => $stat['goal_id'] ?? null,
                        'conversion_status' => $stat['conversion_status'] ?? null,
                    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
                ]);
                if ($stored['duplicate']) {
                    $stats['duplicates']++;
                } else {
                    $stats['inserted']++;
                    if ($click !== null) {
                        $stats['attributed']++;
                    }
                }
            }

            $pageCount = is_numeric($data['pageCount'] ?? null) ? (int) $data['pageCount'] : null;
            if (($pageCount !== null && $page >= $pageCount) || count($rows) < $pageSize) {
                break;
            }
            $page++;
        }

        return $stats;
    }

    private function request(string $apiKey, string $networkId, string $startDate, string $endDate, int $page, int $pageSize): array
    {
        $fields = [
            'Stat.id', 'Stat.datetime', 'Stat.offer_id', 'Stat.goal_id', 'Stat.conversion_status',
            'Stat.approved_payout', 'Stat.currency', 'Stat.sale_amount', 'Stat.affiliate_info1',
        ];
        $query = http_build_query([
            'NetworkId' => $networkId,
            'Target' => 'Affiliate_Report',
            'Method' => 'getConversions',
            'api_key' => $apiKey,
            'fields' => $fields,
            'filters' => [
                'Stat.datetime' => [
                    'conditional' => 'BETWEEN',
                    'values' => [$startDate, $endDate],
                ],
            ],
            'data_start' => $startDate,
            'data_end' => $endDate,
            'page' => $page,
            'limit' => $pageSize,
        ]);
        $endpoint = trim((string) $this->config->get('cam4.tune.endpoint', 'https://api.hasoffers.com/Apiv3/json'));
        if (!$this->isTuneEndpointAllowed($endpoint)) {
            throw new RuntimeException('The configured CAM4 TUNE endpoint is not allowed.');
        }
        $url = rtrim($endpoint, '?') . '?' . $query;
        $context = stream_context_create(['http' => [
            'timeout' => max(5, min(60, (int) $this->config->get('cam4.tune.timeout_seconds', 25))),
            'user_agent' => 'LiveCamForge/' . (string) $this->config->get('version', 'unknown') . ' (+https://livecamforge.com)',
            'header' => "Accept: application/json\r\n",
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $context);
        if (!is_string($body)) {
            throw new RuntimeException('Unable to contact the CAM4/TUNE conversion API.');
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('CAM4/TUNE returned an invalid conversion response.');
        }
        if ((int) ($decoded['response']['status'] ?? -1) !== 1) {
            $message = trim((string) ($decoded['response']['errorMessage'] ?? ''));
            throw new RuntimeException($message !== '' ? 'CAM4/TUNE: ' . $message : 'CAM4/TUNE conversion request failed.');
        }

        return $decoded;
    }

    private function assertDateWindow(string $startDate, string $endDate): void
    {
        $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
        $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
        if (!$start || !$end || $start > $end || $start->diff($end)->days > 30) {
            throw new RuntimeException('CAM4 conversion date range must be valid and no longer than 31 days.');
        }
    }

    private function token(mixed $value, int $max): string
    {
        return substr(preg_replace('/[^a-z0-9_.:-]/i', '', (string) $value) ?? '', 0, $max);
    }

    private function number(mixed $value): float
    {
        $number = is_numeric($value) ? (float) $value : 0.0;
        return is_finite($number) && abs($number) <= 9999999999 ? $number : 0.0;
    }

    private function isTuneEndpointAllowed(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'api.hasoffers.com'
            && rtrim((string) parse_url($url, PHP_URL_PATH), '/') === '/Apiv3/json'
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

}
