<?php

declare(strict_types=1);

namespace LiveCamForge\Postbacks;

use LiveCamForge\Core\Config;
use LiveCamForge\Providers\CrakRevenue\CrakRevenueAdapter;
use LiveCamForge\Repositories\ClickRepository;
use LiveCamForge\Repositories\ConversionRepository;

final class CrakRevenuePostbackHandler implements PostbackHandlerInterface
{
    public function __construct(
        private Config $config,
        private ClickRepository $clicks,
        private ConversionRepository $conversions,
    ) {
    }

    public function handle(array $payload): array
    {
        if (!(bool) $this->config->get('crakrevenue.postback.enabled', false)) {
            return $this->result(404, ['ok' => false, 'message' => 'Postback disabled']);
        }
        if ((bool) $this->config->get('crakrevenue.postback.require_secret', true)) {
            $expected = trim((string) $this->config->get('crakrevenue.postback.secret', ''));
            $received = trim((string) ($payload['secret'] ?? ''));
            if ($expected === '' || $received === '' || !hash_equals($expected, $received)) {
                return $this->result(403, ['ok' => false, 'message' => 'Invalid postback secret']);
            }
        }

        $offerId = $this->integer($payload['offer_id'] ?? null);
        $goalId = $this->integer($payload['goal_id'] ?? null);
        $provider = $offerId !== null ? CrakRevenueAdapter::providerForOfferId($offerId) : null;
        if ($provider === null || $goalId === null) {
            return $this->result(400, ['ok' => false, 'message' => 'Unknown CrakRevenue offer or goal']);
        }

        $sid = $this->token($payload['click_id'] ?? $payload['aff_sub'] ?? null, 120);
        $transactionId = $this->token($payload['transaction_id'] ?? null, 190);
        if ($transactionId === '' && $sid === '') {
            return $this->result(400, ['ok' => false, 'message' => 'Missing transaction and click identifiers']);
        }

        $eventType = CrakRevenueAdapter::goalName($provider, $goalId);
        $payout = $this->number($payload['payout'] ?? null);
        $amount = $this->number($payload['sale_amount'] ?? $payload['amount'] ?? null);
        $currency = strtoupper($this->token($payload['currency'] ?? 'USD', 10)) ?: 'USD';
        $eventTimestamp = substr(trim((string) ($payload['datetime'] ?? '')), 0, 80) ?: null;
        $click = $sid !== '' ? $this->clicks->findBySid($provider, $sid) : null;
        $dedupeKey = $transactionId !== ''
            ? 'transaction:' . $transactionId . ':goal:' . $goalId
            : 'hash:' . hash('sha256', implode('|', [$offerId, $goalId, $sid, (string) $payout, (string) $amount, (string) $eventTimestamp]));

        $stored = $this->conversions->insert([
            'provider' => $provider,
            'dedupe_key' => $dedupeKey,
            'external_event_id' => null,
            'affiliate_click_id' => $click['id'] ?? null,
            'event_type' => $eventType,
            'sid' => $sid ?: null,
            'track' => 'crakrevenue',
            'transaction_id' => $transactionId ?: null,
            'provider_click_id' => null,
            'payout' => $payout,
            'amount' => $amount,
            'currency' => $currency,
            'token_amount' => 0,
            'is_test' => str_starts_with($transactionId, 'test_') ? 1 : 0,
            'event_timestamp' => $eventTimestamp,
            'details_json' => json_encode([
                'affiliate_network' => 'crakrevenue',
                'offer_id' => $offerId,
                'goal_id' => $goalId,
                'goal_name' => $eventType,
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        return $this->result(200, [
            'ok' => true,
            'duplicate' => $stored['duplicate'],
            'attributed' => $click !== null,
            'provider' => $provider,
            'event_type' => $eventType,
        ]);
    }

    public function testPayload(string $provider, string $sid = ''): array
    {
        $offerId = CrakRevenueAdapter::offerId($provider) ?? 0;
        return [
            'secret' => (string) $this->config->get('crakrevenue.postback.secret', ''),
            'click_id' => $sid,
            'transaction_id' => 'test_' . bin2hex(random_bytes(6)),
            'offer_id' => (string) $offerId,
            'goal_id' => '0',
            'payout' => '4.20',
            'sale_amount' => '21.00',
            'currency' => 'USD',
            'datetime' => date('Y-m-d H:i:s'),
        ];
    }

    private function integer(mixed $value): ?int
    {
        $value = trim((string) $value);
        return preg_match('/^\d+$/', $value) === 1 ? (int) $value : null;
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

    /** @return array{status:int,body:array<string,mixed>} */
    private function result(int $status, array $body): array
    {
        return ['status' => $status, 'body' => $body];
    }
}
