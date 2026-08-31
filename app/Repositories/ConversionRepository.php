<?php

declare(strict_types=1);

namespace LiveCamForge\Repositories;

use PDO;
use PDOException;

final class ConversionRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{duplicate:bool,id:?int} */
    public function insert(array $conversion): array
    {
        $sql = <<<'SQL'
            INSERT INTO affiliate_conversions
                (provider, dedupe_key, external_event_id, affiliate_click_id, event_type, sid, track,
                 transaction_id, provider_click_id, payout, amount, currency, token_amount, is_test, event_timestamp,
                 details_json)
            VALUES
                (:provider, :dedupe_key, :external_event_id, :affiliate_click_id, :event_type, :sid, :track,
                 :transaction_id, :provider_click_id, :payout, :amount, :currency, :token_amount, :is_test, :event_timestamp,
                 :details_json)
            SQL;
        try {
            $this->pdo->prepare($sql)->execute($conversion);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return ['duplicate' => true, 'id' => null];
            }
            throw $exception;
        }

        return ['duplicate' => false, 'id' => (int) $this->pdo->lastInsertId()];
    }

    public function summary(?string $since = null, ?string $provider = null): array
    {
        [$where, $params] = $this->filters($since, $provider);
        $statement = $this->pdo->prepare(
            'SELECT COUNT(*) AS conversions, '
            . 'SUM(CASE WHEN affiliate_click_id IS NOT NULL THEN 1 ELSE 0 END) AS attributed, '
            . 'COALESCE(SUM(payout), 0) AS payout, COALESCE(SUM(amount), 0) AS amount '
            . 'FROM affiliate_conversions' . $where
        );
        $statement->execute($params);
        $row = $statement->fetch() ?: [];

        return [
            'conversions' => (int) ($row['conversions'] ?? 0),
            'attributed' => (int) ($row['attributed'] ?? 0),
            'payout' => (float) ($row['payout'] ?? 0),
            'amount' => (float) ($row['amount'] ?? 0),
        ];
    }

    public function recent(int $limit = 20): array
    {
        $limit = max(1, min(100, $limit));
        return $this->pdo->query(
            'SELECT conversions.*, clicks.username, clicks.source_page '
            . 'FROM affiliate_conversions conversions '
            . 'LEFT JOIN affiliate_clicks clicks ON clicks.id = conversions.affiliate_click_id '
            . 'ORDER BY conversions.received_at DESC, conversions.id DESC LIMIT ' . $limit
        )->fetchAll();
    }

    public function currencyTotals(?string $since = null, ?string $provider = null): array
    {
        [$where, $params] = $this->filters($since, $provider);
        $statement = $this->pdo->prepare(
            'SELECT currency, COALESCE(SUM(payout), 0) AS payout, COALESCE(SUM(amount), 0) AS amount '
            . 'FROM affiliate_conversions' . $where . ' GROUP BY currency ORDER BY currency'
        );
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function clickPerformance(?string $since = null, ?string $provider = null): array
    {
        $conditions = ['conversions.is_test = 0', "clicks.interaction_type = 'click'"];
        $params = [];
        if ($since !== null) {
            $conditions[] = 'conversions.received_at >= :since';
            $params['since'] = $since;
        }
        if ($provider !== null) {
            $conditions[] = 'conversions.provider = :provider';
            $params['provider'] = $provider;
        }
        $statement = $this->pdo->prepare(
            'SELECT conversions.currency, COUNT(*) AS conversions, COALESCE(SUM(conversions.payout), 0) AS payout '
            . 'FROM affiliate_conversions conversions '
            . 'INNER JOIN affiliate_clicks clicks ON clicks.id = conversions.affiliate_click_id '
            . 'WHERE ' . implode(' AND ', $conditions)
            . ' GROUP BY conversions.currency ORDER BY conversions.currency'
        );
        $statement->execute($params);
        return $statement->fetchAll();
    }

    public function eventTypes(?string $since = null, ?string $provider = null): array
    {
        [$where, $params] = $this->filters($since, $provider);
        $statement = $this->pdo->prepare(
            'SELECT event_type, currency, COUNT(*) AS conversions, COALESCE(SUM(payout), 0) AS payout '
            . 'FROM affiliate_conversions' . $where
            . ' GROUP BY event_type, currency ORDER BY conversions DESC, event_type, currency'
        );
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** @return array{0:string,1:array<string,string>} */
    private function filters(?string $since, ?string $provider): array
    {
        $conditions = ['is_test = 0'];
        $params = [];
        if ($since !== null) {
            $conditions[] = 'received_at >= :since';
            $params['since'] = $since;
        }
        if ($provider !== null) {
            $conditions[] = 'provider = :provider';
            $params['provider'] = $provider;
        }

        return [$conditions ? ' WHERE ' . implode(' AND ', $conditions) : '', $params];
    }
}
