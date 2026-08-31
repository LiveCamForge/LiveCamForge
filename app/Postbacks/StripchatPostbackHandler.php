<?php

declare(strict_types=1);

namespace LiveCamForge\Postbacks;

use LiveCamForge\Core\Config;
use LiveCamForge\Repositories\ClickRepository;
use LiveCamForge\Repositories\ConversionRepository;

final class StripchatPostbackHandler implements PostbackHandlerInterface
{
    public function __construct(
        private Config $config,
        private ClickRepository $clicks,
        private ConversionRepository $conversions,
    ) {
    }

    public function handle(array $payload): array
    {
        if (!(bool) $this->config->get('stripchat.postback.enabled', false)) {
            return $this->result(404, ['ok' => false, 'message' => 'Postback disabled']);
        }
        if ((bool) $this->config->get('stripchat.postback.require_secret', true)) {
            $expected = trim((string) $this->config->get('stripchat.postback.secret', ''));
            $received = trim((string) ($payload['secret'] ?? ''));
            if ($expected === '' || $received === '' || !hash_equals($expected, $received)) {
                return $this->result(403, ['ok' => false, 'message' => 'Invalid postback secret']);
            }
        }

        $transactionId = $this->token($payload['transactionId'] ?? null, 190);
        $sid = $this->token($payload['memberId'] ?? null, 120);
        $event = $this->event($payload['event'] ?? $payload['transactionType'] ?? 'transaction');
        if ($transactionId === '' && $sid === '' && !isset($payload['amount'], $payload['revenue'])) {
            return $this->result(200, ['ok' => true, 'verification' => true]);
        }
        $amount = $this->number($payload['amount'] ?? null);
        $revenue = $this->number($payload['revenue'] ?? null);
        $click = $sid !== '' ? $this->clicks->findBySid('stripchat', $sid) : null;
        $dedupeKey = $transactionId !== ''
            ? 'transaction:' . $transactionId
            : 'hash:' . hash('sha256', implode('|', [$sid, $event, (string) $amount, (string) $revenue]));
        $currency = strtoupper($this->token($this->config->get('stripchat.postback.currency', 'USD'), 3)) ?: 'USD';
        $stored = $this->conversions->insert([
            'provider' => 'stripchat',
            'dedupe_key' => $dedupeKey,
            'external_event_id' => null,
            'affiliate_click_id' => $click['id'] ?? null,
            'event_type' => $event,
            'sid' => $sid ?: null,
            'track' => 'livecamforge',
            'transaction_id' => $transactionId ?: null,
            'provider_click_id' => null,
            'payout' => $revenue,
            'amount' => $amount,
            'currency' => $currency,
            'token_amount' => 0,
            'is_test' => str_starts_with($transactionId, 'test_') ? 1 : 0,
            'event_timestamp' => null,
            'details_json' => json_encode([
                'transaction_type' => $this->token($payload['transactionType'] ?? null, 80),
                'campaign_id' => $this->token($payload['campaignId'] ?? null, 120),
                'creative_id' => $this->token($payload['creativeId'] ?? null, 120),
                'source_id' => $this->token($payload['sourceId'] ?? null, 120),
                'device' => $this->token($payload['device'] ?? null, 80),
                'user_channel' => $this->token($payload['userChannel'] ?? null, 80),
                'p1' => $this->token($payload['p1'] ?? null, 120),
                'p2' => $this->token($payload['p2'] ?? null, 120),
                'p3' => $this->token($payload['p3'] ?? null, 120),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
        ]);

        return $this->result(200, [
            'ok' => true,
            'duplicate' => $stored['duplicate'],
            'attributed' => $click !== null,
        ]);
    }

    public function testPayload(string $sid = ''): array
    {
        return [
            'secret' => (string) $this->config->get('stripchat.postback.secret', ''),
            'event' => 'first_purchase',
            'transactionId' => 'test_' . bin2hex(random_bytes(6)),
            'memberId' => $sid,
            'amount' => '21.00',
            'revenue' => '4.20',
            'transactionType' => 'purchase',
            'sourceId' => 'livecamforge_test',
        ];
    }

    private function event(mixed $value): string
    {
        $event = strtolower(trim((string) $value));
        return substr(preg_replace('/[^a-z0-9_.-]/', '_', $event) ?: 'transaction', 0, 80);
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
