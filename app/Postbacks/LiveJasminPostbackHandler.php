<?php

declare(strict_types=1);

namespace LiveCamForge\Postbacks;

use LiveCamForge\Core\Config;
use LiveCamForge\Repositories\ClickRepository;
use LiveCamForge\Repositories\ConversionRepository;

final class LiveJasminPostbackHandler implements PostbackHandlerInterface
{
    private const DEFAULT_PARAMETERS = [
        'event_hash' => 'eventHash',
        'transaction_hash' => 'transactionHash',
        'sub_affiliate_id' => 'subAffiliateId',
        'commission' => 'commission',
        'base_amount' => 'baseAmount',
        'bonus_amount' => 'bonusAmount',
        'credit_amount' => 'creditAmount',
        'date' => 'date',
        'country' => 'country',
        'program_code' => 'programCode',
        'is_first_bill' => 'isFirstBill',
        'is_rebill' => 'isRebill',
        'campaign_id' => 'campaignId',
        'campaign_name' => 'campaignName',
        'site_code' => 'siteCode',
        'member_nick' => 'memberNick',
        'transaction_type' => 'transactionType',
        'static_parameter' => 'secret',
    ];

    public function __construct(
        private Config $config,
        private ClickRepository $clicks,
        private ConversionRepository $conversions,
    ) {
    }

    public function handle(array $payload): array
    {
        if (!(bool) $this->config->get('livejasmin.postback.enabled', false)) {
            return $this->result(404, ['ok' => false, 'message' => 'Postback disabled']);
        }

        if ((bool) $this->config->get('livejasmin.postback.require_secret', true)) {
            $expected = (string) $this->config->get('livejasmin.postback.secret', '');
            $received = (string) $this->value($payload, 'static_parameter');
            if ($expected === '' || $received === '' || !hash_equals($expected, $received)) {
                return $this->result(403, ['ok' => false, 'message' => 'Invalid postback secret']);
            }
        }

        $eventHash = $this->safeText($this->value($payload, 'event_hash'), 190);
        $transactionHash = $this->safeText($this->value($payload, 'transaction_hash'), 190);
        $memberNick = $this->safeText($this->value($payload, 'member_nick'), 190);
        $isSignup = $eventHash === '' && $transactionHash === '' && $memberNick !== '';
        if ($isSignup && !(bool) $this->config->get('livejasmin.postback.accept_signups', false)) {
            return $this->result(200, ['ok' => true, 'ignored' => true, 'reason' => 'signup disabled']);
        }
        if (!$isSignup && $eventHash === '' && $transactionHash === '') {
            return $this->result(200, ['ok' => true, 'verification' => true]);
        }

        $sid = $this->safeToken($this->value($payload, 'sub_affiliate_id'), 120);
        $commission = $this->number($this->value($payload, 'commission'));
        $baseAmount = $this->number($this->value($payload, 'base_amount'));
        $bonusAmount = $this->number($this->value($payload, 'bonus_amount'));
        $creditAmount = $this->number($this->value($payload, 'credit_amount'));
        $isFirstBill = $this->flag($this->value($payload, 'is_first_bill'));
        $isRebill = $this->flag($this->value($payload, 'is_rebill'));
        $eventTimestamp = substr(trim((string) $this->value($payload, 'date')), 0, 80) ?: null;
        $eventType = $isSignup
            ? 'signup'
            : $this->eventType($payload, $commission, $baseAmount, $isFirstBill, $isRebill);
        $track = $this->safeToken(
            $this->config->get('livejasmin.postback.track', 'livecamforge'),
            120
        ) ?: 'livecamforge';
        $currency = strtoupper($this->safeToken(
            $this->config->get('livejasmin.postback.currency', 'USD'),
            10
        )) ?: 'USD';
        $dedupeKey = $isSignup
            ? 'signup:' . hash('sha256', implode('|', [$memberNick, $sid, (string) $eventTimestamp]))
            : ($eventHash !== ''
            ? 'event:' . $eventHash
            : 'hash:' . hash('sha256', implode('|', [
                $transactionHash, $sid, $eventType, (string) $eventTimestamp, (string) $commission,
            ])));
        $click = $sid !== '' ? $this->clicks->findBySid('livejasmin', $sid) : null;

        $stored = $this->conversions->insert([
            'provider' => 'livejasmin',
            'dedupe_key' => $dedupeKey,
            'external_event_id' => $eventHash ?: null,
            'affiliate_click_id' => $click['id'] ?? null,
            'event_type' => $eventType,
            'sid' => $sid ?: null,
            'track' => $track,
            'transaction_id' => $transactionHash ?: null,
            'provider_click_id' => null,
            'payout' => $commission,
            'amount' => $baseAmount,
            'currency' => $currency,
            'token_amount' => (int) round($creditAmount),
            'is_test' => str_starts_with($eventHash, 'test_') ? 1 : 0,
            'event_timestamp' => $eventTimestamp,
            'details_json' => json_encode([
                'bonus_amount' => $bonusAmount,
                'credit_amount' => $creditAmount,
                'country' => strtoupper($this->safeToken($this->value($payload, 'country'), 8)),
                'program_code' => strtoupper($this->safeToken($this->value($payload, 'program_code'), 30)),
                'is_first_bill' => $isFirstBill,
                'is_rebill' => $isRebill,
                'campaign_id' => $this->safeToken($this->value($payload, 'campaign_id'), 50),
                'campaign_name' => $this->safeText($this->value($payload, 'campaign_name'), 190),
                'site_code' => $this->safeToken($this->value($payload, 'site_code'), 80),
                'signup' => $isSignup,
                'payload' => $this->sanitizedPayload($payload),
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
        $eventHash = 'test_' . bin2hex(random_bytes(6));
        $values = [
            'event_hash' => $eventHash,
            'transaction_hash' => 'tx_' . $eventHash,
            'sub_affiliate_id' => $sid,
            'commission' => '4.20',
            'base_amount' => '21.00',
            'bonus_amount' => '0',
            'credit_amount' => '20',
            'date' => date('Y-m-d H:i'),
            'country' => 'IT',
            'program_code' => strtoupper((string) $this->config->get('livejasmin.program', 'revs')),
            'is_first_bill' => '1',
            'is_rebill' => '0',
            'campaign_id' => (string) $this->config->get('livejasmin.campaign_id', ''),
            'campaign_name' => 'LiveCamForge test',
            'site_code' => 'jasmin',
            'static_parameter' => (string) $this->config->get('livejasmin.postback.secret', ''),
        ];
        $payload = [];
        foreach ($values as $canonical => $value) {
            $payload[$this->parameterName($canonical)] = $value;
        }
        return $payload;
    }

    public function parameterName(string $canonical): string
    {
        $configured = $this->config->get('livejasmin.postback.parameters.' . $canonical);
        $candidate = is_string($configured) ? trim($configured) : '';
        if ($candidate === '' || preg_match('/^[A-Za-z][A-Za-z0-9_.-]{0,79}$/', $candidate) !== 1) {
            return self::DEFAULT_PARAMETERS[$canonical] ?? $canonical;
        }
        return $candidate;
    }

    public static function inferEventType(
        string $explicit,
        float $commission,
        float $baseAmount,
        bool $isFirstBill,
        bool $isRebill,
    ): string {
        $explicit = strtolower(trim($explicit));
        if ($explicit !== '') {
            return preg_replace('/[^a-z0-9_.-]/', '_', $explicit) ?: 'transaction';
        }
        if ($commission < 0 || $baseAmount < 0) {
            return 'chargeback';
        }
        if ($isFirstBill) {
            return 'first_bill';
        }
        if ($isRebill) {
            return 'rebill';
        }
        return 'transaction';
    }

    private function eventType(array $payload, float $commission, float $baseAmount, bool $first, bool $rebill): string
    {
        return self::inferEventType(
            (string) $this->value($payload, 'transaction_type'),
            $commission,
            $baseAmount,
            $first,
            $rebill,
        );
    }

    private function value(array $payload, string $canonical): mixed
    {
        return $payload[$this->parameterName($canonical)] ?? null;
    }

    private function sanitizedPayload(array $payload): array
    {
        unset($payload[$this->parameterName('static_parameter')]);
        unset($payload[$this->parameterName('member_nick')]);
        $clean = [];
        foreach (array_slice($payload, 0, 50, true) as $key => $value) {
            if (!is_scalar($value) && $value !== null) {
                continue;
            }
            $clean[substr((string) $key, 0, 80)] = substr((string) $value, 0, 1000);
        }
        return $clean;
    }

    /** @return array{status:int,body:array<string,mixed>} */
    private function result(int $status, array $body): array
    {
        return ['status' => $status, 'body' => $body];
    }

    private function safeToken(mixed $value, int $max): string
    {
        $value = preg_replace('/[^a-z0-9_.:-]/i', '', (string) $value) ?? '';
        return substr($value, 0, $max);
    }

    private function safeText(mixed $value, int $max): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]/u', '', trim((string) $value)) ?? '';
        return substr($value, 0, $max);
    }

    private function number(mixed $value): float
    {
        if (!is_numeric($value)) {
            return 0.0;
        }
        $number = (float) $value;
        return is_finite($number) && abs($number) <= 9999999999 ? $number : 0.0;
    }

    private function flag(mixed $value): bool
    {
        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes'], true);
    }
}
