<?php

declare(strict_types=1);

namespace LiveCamForge\Providers\Cam4;

use LiveCamForge\Core\Config;
use LiveCamForge\Core\PerformerCountry;
use LiveCamForge\Core\SyncPerformanceProfiler;
use LiveCamForge\Models\Performer;
use LiveCamForge\Providers\AffiliateTrackingProviderInterface;
use LiveCamForge\Providers\ProviderCapabilities;
use LiveCamForge\Providers\ProviderInterface;
use LiveCamForge\Providers\ProviderPlayer;
use RuntimeException;

final class Cam4Adapter implements ProviderInterface, AffiliateTrackingProviderInterface
{
    public function __construct(private Config $config)
    {
    }

    public function name(): string
    {
        return 'cam4';
    }

    public function displayName(): string
    {
        return 'CAM4';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            embed: true,
            roomStatus: true,
            age: true,
            viewers: true,
            tags: true,
            mediaProxy: true,
            affiliateLinks: true,
            offlineFallback: false,
            postbackTracking: false,
            conversionPolling: true,
        );
    }

    public function fetch(): array
    {
        $affiliateId = $this->affiliateId();
        $pageSize = max(1, min(500, (int) $this->config->get('cam4.page_size', 500)));
        $maxPages = max(1, min(100, (int) $this->config->get('cam4.max_pages', 20)));
        $performers = [];

        SyncPerformanceProfiler::start('provider.normalize');
        for ($page = 1; $page <= $maxPages; $page++) {
            $rows = $this->request($affiliateId, $page, $pageSize);
            SyncPerformanceProfiler::increment('pages_received');
            SyncPerformanceProfiler::increment('rows_received', count($rows));
            if ($rows === []) {
                break;
            }

            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $performer = $this->normalize($row);
                if ($performer !== null) {
                    $performers[$performer->providerId] = $performer;
                }
            }

            if (count($rows) < $pageSize) {
                break;
            }
        }

        SyncPerformanceProfiler::stop('provider.normalize');
        SyncPerformanceProfiler::meta('rows_normalized', count($performers));
        return array_values($performers);
    }

    public function player(array $performer, array $options = []): ?ProviderPlayer
    {
        $streamUrl = trim((string) ($performer['embed_url'] ?? ''));
        if (!$this->isStreamUrlAllowed($streamUrl)) {
            return null;
        }

        return new ProviderPlayer(
            ProviderPlayer::MODE_HLS,
            $streamUrl,
            max(5000, min(30000, (int) $this->config->get('cam4.player_timeout_ms', 12000)))
        );
    }

    public function resolvePlayer(ProviderPlayer $player): ?ProviderPlayer
    {
        return $player->mode === ProviderPlayer::MODE_HLS && $this->isStreamUrlAllowed($player->url)
            ? $player
            : null;
    }

    public function isEmbedUrlAllowed(string $url): bool
    {
        return $this->isStreamUrlAllowed($url);
    }

    public function isRoomUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'https'
            && in_array($host, ['offers.cam4tracking.com', 'www.cam4.com', 'cam4.com'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function isMediaUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'https'
            && ($host === 'snapshots.xcdnpro.com'
                || $host === 'stackvaults-media.xcdnpro.com'
                || $host === 'static.cam4.com'
                || str_ends_with($host, '.xcdnpro.com'))
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function trackedRoomUrl(string $url, string $sid, string $track): string
    {
        if (!$this->isRoomUrlAllowed($url)) {
            return $url;
        }
        $sid = $this->trackingToken($sid, 120);
        $track = $this->trackingToken($track, 100);
        if ($sid === '') {
            return $url;
        }

        $url = preg_replace('/([?&])aff_sub=[^&]*/i', '$1aff_sub=' . rawurlencode($sid), $url) ?? $url;
        if (!preg_match('/[?&]aff_sub=/i', $url)) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'aff_sub=' . rawurlencode($sid);
        }
        if ($track !== '' && !preg_match('/[?&]aff_sub2=/i', $url)) {
            $url .= '&aff_sub2=' . rawurlencode($track);
        }

        return $url;
    }

    /** @return list<array<string,mixed>> */
    private function request(int $affiliateId, int $page, int $pageSize): array
    {
        $program = strtolower(trim((string) $this->config->get('cam4.revenue_program', 'rs')));
        if (!in_array($program, ['rs', 'ppl'], true)) {
            $program = 'rs';
        }
        $query = http_build_query([
            'aid' => $affiliateId,
            'rp' => $program,
            'extended' => 1,
            'limit' => $pageSize,
            'page' => $page,
            'order_by' => 'viewers_desc',
        ]);
        $endpoint = trim((string) $this->config->get('cam4.endpoint', 'https://api.cam4pays.com/api/v1/cams/online.json'));
        if (!$this->isApiEndpointAllowed($endpoint)) {
            throw new RuntimeException('The configured CAM4 endpoint is not allowed.');
        }
        $url = rtrim($endpoint, '?') . '?' . $query;
        $context = stream_context_create(['http' => [
            'timeout' => max(5, min(60, (int) $this->config->get('cam4.timeout_seconds', 25))),
            'user_agent' => 'LiveCamForge/' . (string) $this->config->get('version', 'unknown') . ' (+https://livecamforge.com)',
            'header' => "Accept: application/json\r\n",
            'ignore_errors' => true,
        ]]);
        SyncPerformanceProfiler::increment('remote_requests');
        $remoteStarted = microtime(true);
        $body = @file_get_contents($url, false, $context);
        SyncPerformanceProfiler::add('remote.fetch', microtime(true) - $remoteStarted);
        if (is_string($body)) {
            SyncPerformanceProfiler::increment('remote_bytes', strlen($body));
        }
        if (!is_string($body)) {
            throw new RuntimeException('Unable to contact the CAM4 provider.');
        }
        $decodeStarted = microtime(true);
        $decoded = json_decode($body, true);
        SyncPerformanceProfiler::add('provider.decode', microtime(true) - $decodeStarted);
        if (!is_array($decoded)) {
            throw new RuntimeException('CAM4 returned an invalid response.');
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    private function normalize(array $row): ?Performer
    {
        $username = trim((string) ($row['nickname'] ?? ''));
        if ($username === '') {
            return null;
        }
        $providerId = trim((string) ($row['id'] ?? $username));
        $roomUrl = trim((string) ($row['link'] ?? ''));
        if (!$this->isRoomUrlAllowed($roomUrl)) {
            $roomUrl = 'https://www.cam4.com/' . rawurlencode($username);
        }

        $imageUrl = $this->firstAllowedMediaUrl([
            $row['thumb_big'] ?? null,
            $row['thumb'] ?? null,
            $row['profile_thumb'] ?? null,
            $row['thumb_error'] ?? null,
        ]);
        $previewUrl = $this->firstAllowedMediaUrl([
            $row['thumb_big'] ?? null,
            $row['thumb'] ?? null,
            $row['profile_thumb'] ?? null,
        ]);
        $streamUrl = trim((string) ($row['preview_url'] ?? ''));
        $streamUrl = $this->isStreamUrlAllowed($streamUrl) ? $streamUrl : null;
        $tags = is_array($row['show_tags'] ?? null) ? $row['show_tags'] : [];
        $tags = array_values(array_unique(array_filter(array_map(
            static fn (mixed $tag): string => strtolower(trim((string) $tag)),
            $tags
        ), static fn (string $tag): bool => $tag !== '' && strlen($tag) <= 80)));

        return new Performer(
            provider: $this->name(),
            providerId: $providerId !== '' ? $providerId : $username,
            username: $username,
            displayName: $username,
            gender: $this->normalizeGender($row['gender'] ?? null),
            age: $this->normalizeAge($row['age'] ?? null),
            imageUrl: $imageUrl,
            previewUrl: $previewUrl,
            embedUrl: $streamUrl,
            roomStatus: $this->normalizeRoomStatus($row),
            roomUrl: $roomUrl,
            viewers: is_numeric($row['viewers'] ?? null) ? max(0, (int) $row['viewers']) : null,
            tags: array_slice($tags, 0, 80),
            online: true,
            providerNew: filter_var($row['new_performer'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            countryCode: PerformerCountry::normalize($row['country'] ?? null),
        );
    }

    private function normalizeRoomStatus(array $row): string
    {
        if (filter_var($row['private_room'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return 'private';
        }
        $showType = strtoupper(trim((string) ($row['show_type'] ?? '')));
        return match ($showType) {
            'NORMAL', 'PUBLIC', '' => 'public',
            'PRIVATE' => 'private',
            'GROUP', 'TICKET' => 'group',
            'AWAY' => 'away',
            default => 'unknown',
        };
    }

    private function normalizeGender(mixed $value): ?string
    {
        return match (strtolower(trim((string) $value))) {
            'female' => 'f',
            'male' => 'm',
            'shemale', 'trans', 'transgender' => 't',
            'couple' => 'c',
            default => null,
        };
    }

    private function normalizeAge(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }
        $age = (int) $value;
        return $age >= 18 && $age <= 98 ? $age : null;
    }

    private function isStreamUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $path = strtolower((string) parse_url($url, PHP_URL_PATH));

        return $scheme === 'https'
            && ($host === 'cam4-hls.xcdnpro.com' || str_ends_with($host, '.xcdnpro.com'))
            && str_ends_with($path, '.m3u8')
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function firstAllowedMediaUrl(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            $url = trim((string) $candidate);
            if ($this->isMediaUrlAllowed($url)) {
                return $url;
            }
        }
        return null;
    }

    private function affiliateId(): int
    {
        $affiliateId = (int) $this->config->get('cam4.affiliate_id', 0);
        if ($affiliateId <= 0) {
            throw new RuntimeException('Enter the CAM4Pays Affiliate ID in config/local.php.');
        }
        return $affiliateId;
    }

    private function trackingToken(string $value, int $max): string
    {
        return substr(preg_replace('/[^a-z0-9_.:-]/i', '', $value) ?? '', 0, $max);
    }

    private function isApiEndpointAllowed(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'api.cam4pays.com'
            && rtrim((string) parse_url($url, PHP_URL_PATH), '/') === '/api/v1/cams/online.json'
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

}
