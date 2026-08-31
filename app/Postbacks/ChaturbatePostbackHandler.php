<?php

declare(strict_types=1);

namespace LiveCamForge\Postbacks;

use LiveCamForge\Core\Config;
use LiveCamForge\Repositories\ClickRepository;
use LiveCamForge\Repositories\ConversionRepository;

final class ChaturbatePostbackHandler implements PostbackHandlerInterface
{
    public function __construct(
        private Config $config,
        private ClickRepository $clicks,
        private ConversionRepository $conversions,
    ) {
    }

    public function handle(array $payload): array
    {
        if (!(bool) $this->config->get('chaturbate.postback.enabled', false)) {
            return $this->result(404, ['ok' => false, 'message' => 'Postback disabled']);
        }

        $logId = $this->safeToken($payload['log_id'] ?? '', 190);
        $attempt = $this->safeToken($payload['attempt'] ?? '', 40);
        $checksum = strtolower($this->safeToken($payload['checksum'] ?? '', 64));
        if ((bool) $this->config->get('chaturbate.postback.require_checksum', true)) {
            $salt = (string) $this->config->get('chaturbate.postback.validation_salt', '');
            if ($salt === '' || $logId === '' || $attempt === '' || $checksum === '') {
                return $this->result(400, ['ok' => false, 'message' => 'Missing checksum fields']);
            }
            $expected = md5($salt . $logId . $attempt);
            if (!hash_equals($expected, $checksum)) {
                return $this->result(403, ['ok' => false, 'message' => 'Invalid checksum']);
            }
        }

        $sid = $this->safeToken($payload['sid'] ?? '', 120);
        $track = $this->safeToken($payload['track'] ?? '', 120);
        $expectedTrack = $this->safeToken(
            $this->config->get('chaturbate.postback.track', 'livecamforge'),
            120
        ) ?: 'livecamforge';
        if (!self::belongsToTracker($sid, $track, $expectedTrack)) {
            return $this->result(200, [
                'ok' => true,
                'ignored' => true,
                'reason' => 'foreign tracker',
            ]);
        }
        $eventType = $this->safeToken($payload['type'] ?? '', 120) ?: 'unknown';
        $transactionId = $this->safeToken($payload['transaction_id'] ?? '', 190);
        $providerClickId = $this->safeToken($payload['click_id'] ?? '', 190);
        $eventTimestamp = substr(trim((string) ($payload['timestamp'] ?? '')), 0, 80) ?: null;
        $payout = $this->number($payload['payout'] ?? 0);
        $amount = $this->number($payload['amount'] ?? 0);
        $currency = strtoupper($this->safeToken($payload['currency'] ?? 'USD', 10)) ?: 'USD';
        $tokenAmount = self::tokenAmount($payload);
        $dedupeKey = $logId !== ''
            ? 'log:' . $logId
            : ($transactionId !== ''
                ? 'tx:' . $transactionId
                : 'hash:' . hash('sha256', implode('|', [
                    $sid, $eventType, (string) $eventTimestamp, (string) $payout, (string) $amount,
                ])));

        $click = $sid !== '' ? $this->clicks->findBySid('chaturbate', $sid) : null;
        $stored = $this->conversions->insert([
            'provider' => 'chaturbate',
            'dedupe_key' => $dedupeKey,
            'external_event_id' => $logId ?: null,
            'affiliate_click_id' => $click['id'] ?? null,
            'event_type' => $eventType,
            'sid' => $sid ?: null,
            'track' => $track ?: null,
            'transaction_id' => $transactionId ?: null,
            'provider_click_id' => $providerClickId ?: null,
            'payout' => $payout,
            'amount' => $amount,
            'currency' => $currency,
            'token_amount' => $tokenAmount,
            'is_test' => $eventType === 'test_conversion' ? 1 : 0,
            'event_timestamp' => $eventTimestamp,
            'details_json' => null,
        ]);

        return $this->result(200, [
            'ok' => true,
            'duplicate' => $stored['duplicate'],
            'attributed' => $click !== null,
        ]);
    }

    /** @return array{status:int,body:array<string,mixed>} */
    private function result(int $status, array $body): array
    {
        return ['status' => $status, 'body' => $body];
    }

    private function safeToken(mixed $value, int $max): string
    {
        $value = preg_replace('/[^a-z0-9_.-]/i', '', (string) $value) ?? '';
        return substr($value, 0, $max);
    }

    public static function belongsToTracker(string $sid, string $track, string $expectedTrack = 'livecamforge'): bool
    {
        if ($sid !== '' && !str_starts_with($sid, 'lcf_')) {
            return false;
        }
        if ($track !== '' && !hash_equals($expectedTrack, $track)) {
            return false;
        }

        return true;
    }

    public static function tokenAmount(array $payload): int
    {
        $value = $payload['tokens'] ?? $payload['token'] ?? 0;
        return is_numeric($value) ? (int) $value : 0;
    }

    private function number(mixed $value): float
    {
        if (!is_numeric($value)) {
            return 0.0;
        }
        $number = (float) $value;
        return is_finite($number) && abs($number) <= 9999999999 ? $number : 0.0;
    }
}
