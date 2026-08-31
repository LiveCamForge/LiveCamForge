<?php

declare(strict_types=1);

namespace LiveCamForge\Repositories;

use PDO;

final class ClickRepository
{
    public function __construct(private PDO $pdo)
    {
    }

    /** @return array{id:int,sid:string,track:string} */
    public function record(
        array $performer,
        string $track = 'livecamforge',
        string $interactionType = 'click',
        string $sourcePage = 'catalog'
    ): array
    {
        $sid = $this->generateSid();
        $track = $this->safeToken($track, 100) ?: 'livecamforge';
        $interactionType = in_array($interactionType, ['click', 'widget'], true) ? $interactionType : 'click';
        $sourcePage = $this->safeToken($sourcePage, 80) ?: 'catalog';
        $statement = $this->pdo->prepare(
            'INSERT INTO affiliate_clicks (provider, provider_id, username, sid, track, interaction_type, source_page) '
            . 'VALUES (:provider, :provider_id, :username, :sid, :track, :interaction_type, :source_page)'
        );
        $statement->execute([
            'provider' => $performer['provider'],
            'provider_id' => $performer['provider_id'],
            'username' => $performer['username'],
            'sid' => $sid,
            'track' => $track,
            'interaction_type' => $interactionType,
            'source_page' => $sourcePage,
        ]);

        return ['id' => (int) $this->pdo->lastInsertId(), 'sid' => $sid, 'track' => $track];
    }

    public function findBySid(string $provider, string $sid): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM affiliate_clicks WHERE provider = :provider AND sid = :sid LIMIT 1'
        );
        $statement->execute(['provider' => $provider, 'sid' => $sid]);
        $click = $statement->fetch();

        return is_array($click) ? $click : null;
    }

    public function countSince(?string $since = null, ?string $provider = null): int
    {
        $where = [];
        $params = [];
        if ($since !== null) {
            $where[] = 'clicked_at >= :since';
            $params['since'] = $since;
        }
        if ($provider !== null) {
            $where[] = 'provider = :provider';
            $params['provider'] = $provider;
        }
        $conditions = array_merge(['interaction_type = \'click\''], $where);
        $sql = 'SELECT COUNT(*) FROM affiliate_clicks WHERE ' . implode(' AND ', $conditions);
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return (int) $statement->fetchColumn();
    }

    public function latestByProvider(string $provider): ?array
    {
        $statement = $this->pdo->prepare(
            'SELECT * FROM affiliate_clicks WHERE provider = :provider AND sid IS NOT NULL '
            . 'ORDER BY clicked_at DESC, id DESC LIMIT 1'
        );
        $statement->execute(['provider' => $provider]);
        $click = $statement->fetch();

        return is_array($click) ? $click : null;
    }

    public function sourcePerformance(?string $since = null): array
    {
        $conditions = ["clicks.interaction_type = 'click'"];
        $params = [];
        if ($since !== null) {
            $conditions[] = 'clicks.clicked_at >= :since';
            $params['since'] = $since;
        }
        $statement = $this->pdo->prepare(
            'SELECT clicks.source_page, COUNT(DISTINCT clicks.id) AS clicks, '
            . 'COUNT(DISTINCT conversions.id) AS conversions, '
            . 'COALESCE(SUM(conversions.payout), 0) AS payout '
            . 'FROM affiliate_clicks clicks '
            . 'LEFT JOIN affiliate_conversions conversions ON conversions.affiliate_click_id = clicks.id AND conversions.is_test = 0 '
            . 'WHERE ' . implode(' AND ', $conditions) . ' '
            . 'GROUP BY clicks.source_page ORDER BY clicks DESC, conversions DESC, clicks.source_page'
        );
        $statement->execute($params);
        return $statement->fetchAll();
    }

    private function generateSid(): string
    {
        return 'lcf_' . bin2hex(random_bytes(12));
    }

    private function safeToken(string $value, int $max): string
    {
        $value = preg_replace('/[^a-z0-9_.-]/i', '', $value) ?? '';

        return substr($value, 0, $max);
    }
}
