<?php

declare(strict_types=1);

namespace LiveCamForge\Providers\CrakRevenue;

use LiveCamForge\Core\PerformerCountry;
use LiveCamForge\Core\Config;
use LiveCamForge\Core\PerformerTypes;
use LiveCamForge\Models\Performer;
use LiveCamForge\Providers\AffiliateTrackingProviderInterface;
use LiveCamForge\Providers\ProviderCapabilities;
use LiveCamForge\Providers\ProviderInterface;
use LiveCamForge\Providers\ProviderPlayer;
use RuntimeException;

final class CrakRevenueAdapter implements ProviderInterface, AffiliateTrackingProviderInterface
{
    private const SOURCES = [
        'crakrevenue_mfc' => ['brand' => 'mfc', 'label' => 'MyFreeCams via CrakRevenue'],
        'crakrevenue_streamate' => ['brand' => 'streamate', 'label' => 'Jerkmate via CrakRevenue'],
        'crakrevenue_chaturbate' => ['brand' => 'chaturbate', 'label' => 'Chaturbate via CrakRevenue'],
        'crakrevenue_awempire' => ['brand' => 'awempire', 'label' => 'LiveJasmin via CrakRevenue'],
        'crakrevenue_stripchat' => ['brand' => 'stripchat', 'label' => 'Stripchat via CrakRevenue'],
        'crakrevenue_imlive' => ['brand' => 'imlive', 'label' => 'ImLive via CrakRevenue'],
        'crakrevenue_bongacash' => ['brand' => 'bongacash', 'label' => 'BongaCams via CrakRevenue'],
    ];

    private const OFFERS = [
        'crakrevenue_mfc' => ['offer_id' => 779, 'goals' => [0 => 'spending', 3529 => 'lead']],
        'crakrevenue_streamate' => ['offer_id' => 6224, 'goals' => [0 => 'spending', 24527 => 'lead']],
        'crakrevenue_chaturbate' => ['offer_id' => 3688, 'goals' => [0 => 'spending']],
        'crakrevenue_awempire' => ['offer_id' => 4487, 'goals' => [0 => 'click', 18290 => 'conversion', 32910 => 'lead']],
        'crakrevenue_stripchat' => ['offer_id' => 3778, 'goals' => [0 => 'spending', 32528 => 'soi', 35529 => 'doi', 33281 => 'chargeback']],
        'crakrevenue_imlive' => ['offer_id' => 2118, 'goals' => [0 => 'spending']],
        'crakrevenue_bongacash' => ['offer_id' => 7683, 'goals' => [0 => 'spending', 30664 => 'lead']],
    ];

    private CrakRevenueClient $client;

    public function __construct(private Config $config, private string $providerName)
    {
        if (!isset(self::SOURCES[$providerName])) {
            throw new RuntimeException('Unsupported CrakRevenue source.');
        }
        $this->client = new CrakRevenueClient($config);
    }

    /** @return list<string> */
    public static function providerNames(): array
    {
        return array_keys(self::SOURCES);
    }

    public static function brandForProvider(string $providerName): ?string
    {
        return self::SOURCES[$providerName]['brand'] ?? null;
    }

    public static function providerForOfferId(int $offerId): ?string
    {
        foreach (self::OFFERS as $provider => $offer) {
            if ($offer['offer_id'] === $offerId) {
                return $provider;
            }
        }
        return null;
    }

    public static function goalName(string $providerName, int $goalId): string
    {
        return self::OFFERS[$providerName]['goals'][$goalId] ?? ('goal_' . $goalId);
    }

    public static function offerId(string $providerName): ?int
    {
        return self::OFFERS[$providerName]['offer_id'] ?? null;
    }

    public function name(): string
    {
        return $this->providerName;
    }

    public function displayName(): string
    {
        return self::SOURCES[$this->providerName]['label'];
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            embed: true,
            roomStatus: false,
            age: true,
            viewers: false,
            tags: true,
            mediaProxy: false,
            affiliateLinks: true,
            offlineFallback: false,
            postbackTracking: true,
        );
    }

    public function fetch(): array
    {
        $brand = self::SOURCES[$this->providerName]['brand'];
        // The API recommends small pages for individual requests, but a full
        // multi-brand sync must also stay within ordinary PHP execution
        // limits. The 100-record maximum keeps the number of sequential
        // requests practical; short final pages still stop the loop early.
        $pageSize = max(1, min(100, (int) $this->config->get('crakrevenue.page_size', 100)));
        $maxPages = max(1, min(100, (int) $this->config->get('crakrevenue.max_pages', 10)));
        $types = PerformerTypes::fromConfig($this->config);
        $performers = [];

        foreach ($types as $gender) {
            for ($page = 1; $page <= $maxPages; $page++) {
                $payload = $this->client->fetchPage($brand, $gender, $page, $pageSize);
                $rows = $payload['performers'];
                foreach ($rows as $row) {
                    if (!is_array($row)) {
                        continue;
                    }
                    $performer = $this->normalize($row);
                    if ($performer !== null) {
                        $performers[$performer->providerId] = $performer;
                    }
                }

                $received = count($rows);
                $count = is_numeric($payload['count'] ?? null) ? (int) $payload['count'] : null;
                if ($received < $pageSize || ($count !== null && $page * $pageSize >= $count)) {
                    break;
                }
            }
        }

        return array_values($performers);
    }

    public function player(array $performer, array $options = []): ?ProviderPlayer
    {
        $url = trim((string) ($performer['embed_url'] ?? ''));
        if (!$this->isEmbedUrlAllowed($url)) {
            return null;
        }

        return new ProviderPlayer(
            ProviderPlayer::MODE_SCRIPT,
            $url,
            max(5000, min(30000, (int) $this->config->get('crakrevenue.player_timeout_ms', 12000))),
            sandboxWrapper: true,
        );
    }

    public function resolvePlayer(ProviderPlayer $player): ?ProviderPlayer
    {
        return $player->mode === ProviderPlayer::MODE_SCRIPT && $this->isEmbedUrlAllowed($player->url)
            ? new ProviderPlayer(ProviderPlayer::MODE_WRAPPED_IFRAME, $player->url)
            : null;
    }

    public function isEmbedUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = [
            'suaplf.live',
            'hybridclient.naiadsystems.com',
            'bongacams.com',
            'chaturbate.com',
            'edwmcr.com',
        ];

        $allowedHost = in_array($host, $allowed, true)
            || str_ends_with($host, '.chaturbate.com')
            || str_ends_with($host, '.edwmcr.com');

        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && $allowedHost
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function isRoomUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowedSuffixes = ['camsk7.com', 'crjmpy.com', 'chaturbate.com', 'ctwmsg.com', 'frtayb.com', 'ajrkmx3.com'];
        $allowed = false;
        foreach ($allowedSuffixes as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                $allowed = true;
                break;
            }
        }

        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && $allowed
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function trackedRoomUrl(string $url, string $sid, string $track): string
    {
        if (!(bool) $this->config->get('crakrevenue.postback.enabled', false)
            || !$this->isRoomUrlAllowed($url)
            || preg_match('/^[A-Za-z0-9_.-]{1,120}$/', $sid) !== 1
        ) {
            return $url;
        }

        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return $url;
        }
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['aff_sub'] = $sid;

        $tracked = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . (string) ($parts['path'] ?? '')
            . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986)
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');

        return $this->isRoomUrlAllowed($tracked) ? $tracked : $url;
    }

    public function isMediaUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowedSuffixes = [
            'mfcimg.com',
            'icfcdn.com',
            'chaturbate.com',
            'highwebmedia.com',
            'live.mmcdn.com',
            'vcmdiawe.com',
            'strpst.com',
            'doppiocdn.com',
            'imlmediahub.com',
            'bgicdn.com',
        ];
        $allowed = false;
        foreach ($allowedSuffixes as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                $allowed = true;
                break;
            }
        }

        return $allowed
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function normalize(array $row): ?Performer
    {
        $itemId = trim((string) ($row['itemId'] ?? ''));
        $username = trim((string) ($row['name'] ?? $row['nameClean'] ?? ''));
        $characteristic = is_array($row['characteristic'] ?? null) ? $row['characteristic'] : [];
        $gender = strtolower(trim((string) ($characteristic['genderCode'] ?? $characteristic['gender'] ?? '')));
        if ($itemId === '' || $username === '' || !in_array($gender, ['f', 'm', 't', 'c'], true)) {
            return null;
        }

        $imageUrl = $this->allowedUrl($row['thumbnailUrl'] ?? null, 'media');
        $previewUrl = $this->allowedUrl(
            $row['liveSnapshotURL'] ?? $row['liveSnapshotUrl'] ?? null,
            'media'
        );
        $embedUrl = $this->allowedUrl(
            $row['iframeFeedURL'] ?? $row['iframeFeedUrl'] ?? $row['streamFeedUrl'] ?? null,
            'embed'
        );
        $roomUrl = $this->allowedUrl($row['roomUrl'] ?? null, 'room') ?? '#';
        $tags = [];
        foreach (['customTags', 'characteristicsTags', 'autoTags'] as $tagField) {
            foreach (is_array($row[$tagField] ?? null) ? $row[$tagField] : [] as $tag) {
                $tag = strtolower(trim((string) $tag));
                if ($tag !== '' && strlen($tag) <= 80) {
                    $tags[$tag] = true;
                }
            }
        }
        $score = is_numeric($row['systemScore'] ?? null) ? (float) $row['systemScore'] : null;
        $popularityScore = $score !== null && $score >= 0 && $score <= 1 ? $score : null;

        return new Performer(
            provider: $this->name(),
            providerId: $itemId,
            username: $username,
            displayName: trim((string) ($row['name'] ?? '')) ?: $username,
            gender: $gender,
            age: $this->normalizeAge($characteristic['age'] ?? null),
            imageUrl: $imageUrl,
            previewUrl: $previewUrl ?: $imageUrl,
            embedUrl: $embedUrl,
            roomStatus: 'unknown',
            roomUrl: $roomUrl,
            // CrakRevenue Lite exposes a provider ranking score, not viewers.
            viewers: null,
            tags: array_slice(array_keys($tags), 0, 120),
            online: (bool) ($row['live'] ?? true),
            countryCode: PerformerCountry::normalize(
                $characteristic['countryCode'] ?? $characteristic['country'] ?? null
            ),
            popularityScore: $popularityScore,
        );
    }

    private function normalizeAge(mixed $value): ?int
    {
        $age = is_numeric($value) ? (int) $value : 0;

        return $age >= 18 && $age <= 98 ? $age : null;
    }

    private function allowedUrl(mixed $value, string $type): ?string
    {
        $url = trim((string) $value);
        $allowed = match ($type) {
            'media' => $this->isMediaUrlAllowed($url),
            'embed' => $this->isEmbedUrlAllowed($url),
            'room' => $this->isRoomUrlAllowed($url),
            default => false,
        };

        return $allowed ? $url : null;
    }
}
