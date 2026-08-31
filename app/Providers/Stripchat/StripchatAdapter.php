<?php

declare(strict_types=1);

namespace LiveCamForge\Providers\Stripchat;

use LiveCamForge\Core\PerformerCountry;
use LiveCamForge\Core\Config;
use LiveCamForge\Core\VisitorGeo;
use LiveCamForge\Core\SyncPerformanceProfiler;
use LiveCamForge\Models\Performer;
use LiveCamForge\Providers\ProviderCapabilities;
use LiveCamForge\Providers\DeletedPerformersProviderInterface;
use LiveCamForge\Providers\AffiliateTrackingProviderInterface;
use LiveCamForge\Providers\ProviderInterface;
use LiveCamForge\Providers\ProviderPlayer;
use RuntimeException;

final class StripchatAdapter implements ProviderInterface, DeletedPerformersProviderInterface, AffiliateTrackingProviderInterface
{
    /** @var list<string> */
    private array $deletedUsernames = [];

    public function __construct(private Config $config, private ?string $root = null)
    {
    }

    public function name(): string
    {
        return 'stripchat';
    }

    public function displayName(): string
    {
        return 'Stripchat';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            embed: true,
            roomStatus: true,
            age: false,
            viewers: true,
            tags: true,
            mediaProxy: false,
            affiliateLinks: true,
            offlineFallback: true,
            geoRestrictions: true,
            postbackTracking: true,
        );
    }

    public function fetch(): array
    {
        $apiKey = trim((string) $this->config->get('stripchat.api_key', ''));
        $userId = trim((string) $this->config->get('stripchat.user_id', ''));
        if ($apiKey === '' || $userId === '') {
            throw new RuntimeException('Configure the Stripchat api_key and user_id in Admin > Integrations first.');
        }

        $endpoint = trim((string) $this->config->get(
            'stripchat.endpoint',
            'https://go.whitetrafsa.com/app/models-ext/models'
        ));
        if (!$this->isApiEndpointAllowed($endpoint)) {
            throw new RuntimeException('The configured Stripchat endpoint is not allowed.');
        }
        $url = $endpoint . '?' . http_build_query(['userId' => $userId], '', '&', PHP_QUERY_RFC3986);
        $context = stream_context_create(['http' => [
            'timeout' => max(5, min(60, (int) $this->config->get('stripchat.timeout_seconds', 30))),
            'user_agent' => 'LiveCamForge/' . (string) $this->config->get('version', 'unknown') . ' (+https://livecamforge.com)',
            'header' => "Accept: application/json\r\nAuthorization: Bearer {$apiKey}\r\n",
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
            throw new RuntimeException('Unable to contact the Stripchat Aggregators API.');
        }
        SyncPerformanceProfiler::start('provider.decode');
        $decoded = json_decode($body, true);
        SyncPerformanceProfiler::stop('provider.decode');
        if (!is_array($decoded) || !isset($decoded['models']) || !is_array($decoded['models'])) {
            throw new RuntimeException('Stripchat returned an invalid response.');
        }

        SyncPerformanceProfiler::meta('rows_received', count($decoded['models']));
        SyncPerformanceProfiler::start('provider.normalize');
        $performers = [];
        foreach ($decoded['models'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $performer = $this->normalize($row);
            if ($performer !== null) {
                $performers[$performer->providerId] = $performer;
            }
        }

        SyncPerformanceProfiler::stop('provider.normalize');
        SyncPerformanceProfiler::meta('rows_normalized', count($performers));
        SyncPerformanceProfiler::start('provider.deleted_check');
        $this->deletedUsernames = $this->fetchDeletedUsernamesWhenDue($apiKey);
        SyncPerformanceProfiler::stop('provider.deleted_check');
        SyncPerformanceProfiler::meta('deleted_usernames', count($this->deletedUsernames));

        return array_values($performers);
    }

    public function deletedUsernames(): array
    {
        return $this->deletedUsernames;
    }

    public function player(array $performer, array $options = []): ?ProviderPlayer
    {
        $username = trim((string) ($performer['username'] ?? ''));
        $userId = trim((string) $this->config->get('stripchat.user_id', ''));
        if ($username === '' || $userId === '') {
            return null;
        }

        $query = [
            'modelName' => $username,
            'strict' => 1,
            'userId' => $userId,
            'autoplay' => $this->autoplay(),
            'volumeControl' => (bool) $this->config->get('stripchat.player.volume_control', true) ? 1 : 0,
            'fullscreen' => (bool) $this->config->get('stripchat.player.fullscreen', true) ? 1 : 0,
            'thumbFit' => 'smart',
            'quality' => $this->quality(),
            'usePreroll' => 2,
            'nonStopPlaying' => 1,
            'hideLiveBadge' => 1,
        ];
        foreach (['campaignId' => 'campaign_id', 'sourceId' => 'source_id', 'p1' => 'p1', 'p2' => 'p2', 'p3' => 'p3'] as $parameter => $configKey) {
            $value = trim((string) $this->config->get('stripchat.tracking.' . $configKey, ''));
            if ($value !== '') {
                $query[$parameter] = substr($value, 0, 120);
            }
        }
        $clickThroughUrl = trim((string) ($options['click_through_url'] ?? ''));
        if ($this->isHttpsUrl($clickThroughUrl)) {
            $query['clickThroughUrl'] = $clickThroughUrl;
        }

        $endpoint = rtrim((string) $this->config->get(
            'stripchat.player_endpoint',
            'https://creative.whitetrafsa.com/widgets/Player'
        ), '?');
        $url = $endpoint . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        if (!$this->isEmbedUrlAllowed($url)) {
            return null;
        }

        return new ProviderPlayer(
            ProviderPlayer::MODE_SCRIPT,
            $url,
            max(5000, min(30000, (int) $this->config->get('stripchat.player_timeout_ms', 12000))),
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
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'creative.whitetrafsa.com'
            && rtrim((string) parse_url($url, PHP_URL_PATH), '/') === '/widgets/Player'
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function isRoomUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && ($host === 'stripchat.com' || str_ends_with($host, '.stripchat.com') || $host === 'go.whitetrafsa.com')
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function isMediaUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $allowed = $host === 'static-proxy.strpst.com'
            || str_ends_with($host, '.strpst.com')
            || $host === 'img.doppiocdn.com'
            || str_ends_with($host, '.doppiocdn.com');

        return $allowed
            && strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function trackedRoomUrl(string $url, string $sid, string $track): string
    {
        if (!$this->isRoomUrlAllowed($url) || preg_match('/^[A-Za-z0-9_.-]{1,120}$/', $sid) !== 1) {
            return $url;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return $url;
        }
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['memberId'] = $sid;
        $tracked = $parts['scheme'] . '://' . $parts['host']
            . (isset($parts['port']) ? ':' . $parts['port'] : '')
            . (string) ($parts['path'] ?? '')
            . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986)
            . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');

        return $this->isRoomUrlAllowed($tracked) ? $tracked : $url;
    }

    private function normalize(array $row): ?Performer
    {
        $id = trim((string) ($row['id'] ?? ''));
        $username = trim((string) ($row['username'] ?? ''));
        $gender = $this->normalizeGender($row);
        if ($id === '' || $username === '' || $gender === null) {
            return null;
        }

        $tags = [];
        foreach (is_array($row['tags'] ?? null) ? $row['tags'] : [] as $tag) {
            $tag = strtolower(trim((string) $tag));
            if ($tag !== '' && strlen($tag) <= 100) {
                $tags[$tag] = true;
            }
        }
        foreach (is_array($row['languages'] ?? null) ? $row['languages'] : [] as $language) {
            $language = strtolower(trim((string) $language));
            if ($language !== '' && strlen($language) <= 20) {
                $tags['lang-' . $language] = true;
            }
        }
        $geoBlocks = $this->normalizeGeoBlocks($row['geobans'] ?? null);
        $imageUrl = $this->allowedMediaUrl($row['avatarUrl'] ?? $row['previewUrlThumbSmall'] ?? null);
        $previewUrl = $this->allowedMediaUrl($row['snapshotUrl'] ?? $row['popularSnapshotUrl'] ?? null);
        $roomUrl = trim((string) ($row['clickUrl'] ?? ''));
        if (!$this->isRoomUrlAllowed($roomUrl)) {
            $roomUrl = '#';
        }

        $viewerValue = $row['viewersCount'] ?? null;

        return new Performer(
            provider: $this->name(),
            providerId: $id,
            username: $username,
            displayName: $username,
            gender: $gender,
            age: null,
            imageUrl: $imageUrl,
            previewUrl: $previewUrl ?: $imageUrl,
            embedUrl: null,
            roomStatus: $this->normalizeStatus($row['status'] ?? null),
            roomUrl: $roomUrl,
            viewers: is_numeric($viewerValue) ? max(0, (int) $viewerValue) : null,
            tags: array_slice(array_keys($tags), 0, 120),
            online: true,
            geoBlocks: $geoBlocks,
            countryCode: PerformerCountry::normalize(
                $row['modelsCountry'] ?? $row['modelCountry'] ?? $row['countryCode'] ?? $row['country'] ?? null
            ),
        );
    }

    private function normalizeGender(array $row): ?string
    {
        foreach (is_array($row['tags'] ?? null) ? $row['tags'] : [] as $tag) {
            $root = strtolower((string) explode('/', trim((string) $tag), 2)[0]);
            $mapped = match ($root) {
                'girls' => 'f',
                'men' => 'm',
                'trans' => 't',
                'couples' => 'c',
                default => null,
            };
            if ($mapped !== null) {
                return $mapped;
            }
        }

        $gender = strtolower(trim((string) ($row['gender'] ?? '')));
        $broadcastGender = strtolower(trim((string) ($row['broadcastGender'] ?? '')));

        return match ($gender) {
            'female', 'females' => $broadcastGender === 'group' ? 'c' : 'f',
            'male', 'males' => 'm',
            'trans', 'transgender' => 't',
            'malefemale', 'couple', 'couples' => 'c',
            default => $broadcastGender === 'group' ? 'c' : null,
        };
    }

    private function normalizeStatus(mixed $value): string
    {
        return match (strtolower(trim((string) $value))) {
            'public' => 'public',
            'groupshow' => 'group',
            'private', 'p2p' => 'private',
            default => 'unknown',
        };
    }

    /** @return list<string> */
    private function normalizeGeoBlocks(mixed $value): array
    {
        $geobans = is_array($value) ? $value : [];
        $blocks = [];
        foreach (is_array($geobans['blockedCountries'] ?? null) ? $geobans['blockedCountries'] : [] as $country) {
            $code = VisitorGeo::normalizeBlock($country);
            if ($code !== null) {
                $blocks[$code] = true;
            }
        }
        foreach (is_array($geobans['blockedRegions'] ?? null) ? $geobans['blockedRegions'] : [] as $country => $regions) {
            foreach (is_array($regions) ? $regions : [] as $region) {
                $code = VisitorGeo::normalizeBlock((string) $country . ':' . (string) $region);
                if ($code !== null) {
                    $blocks[$code] = true;
                }
            }
        }
        foreach (is_array($geobans['blockedLanguages'] ?? null) ? $geobans['blockedLanguages'] : [] as $language) {
            $code = VisitorGeo::normalizeLanguageBlock($language);
            if ($code !== null) {
                $blocks[$code] = true;
            }
        }

        return array_keys($blocks);
    }

    private function allowedMediaUrl(mixed $value): ?string
    {
        $url = trim((string) $value);

        return $this->isMediaUrlAllowed($url) ? $url : null;
    }

    private function autoplay(): string
    {
        $value = trim((string) $this->config->get('stripchat.player.autoplay', 'all'));

        return in_array($value, ['all', 'notAtAll', 'playButton'], true) ? $value : 'all';
    }

    private function quality(): string
    {
        $value = trim((string) $this->config->get('stripchat.player.quality', 'optimal'));

        return in_array($value, ['original', 'optimal', '480p', '240p', '160p'], true) ? $value : 'optimal';
    }

    private function isApiEndpointAllowed(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'go.whitetrafsa.com'
            && rtrim((string) parse_url($url, PHP_URL_PATH), '/') === '/app/models-ext/models'
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /** @return list<string> */
    private function fetchDeletedUsernamesWhenDue(string $apiKey): array
    {
        if ($this->root === null || $this->root === '') {
            return [];
        }
        $statePath = $this->root . '/storage/cache/stripchat-deleted-check.json';
        $state = is_file($statePath) ? json_decode((string) @file_get_contents($statePath), true) : null;
        $lastCheck = is_array($state) ? (int) ($state['checked_at'] ?? 0) : 0;
        if ($lastCheck > time() - 86400) {
            return [];
        }

        // The API permits at most one request every five seconds. The main
        // online-model request has just completed, so wait before this daily poll.
        usleep(5_100_000);
        $endpoint = trim((string) $this->config->get(
            'stripchat.deleted_endpoint',
            'https://go.whitetrafsa.com/app/models-ext/models/deleted'
        ));
        if (!$this->isDeletedEndpointAllowed($endpoint)) {
            throw new RuntimeException('The configured Stripchat deleted-models endpoint is not allowed.');
        }
        $now = time();
        $since = $lastCheck > 0
            ? max($lastCheck - 60, $now - 89 * 86400)
            : $now - 7 * 86400;
        $deletedUrl = $endpoint . '?' . http_build_query([
            'deleted_since' => gmdate(DATE_RFC3339, $since),
            'deleted_until' => gmdate(DATE_RFC3339, $now),
        ], '', '&', PHP_QUERY_RFC3986);
        $context = stream_context_create(['http' => [
            'timeout' => max(5, min(60, (int) $this->config->get('stripchat.timeout_seconds', 30))),
            'user_agent' => 'LiveCamForge/' . (string) $this->config->get('version', 'unknown') . ' (+https://livecamforge.com)',
            'header' => "Accept: application/json\r\nAuthorization: Bearer {$apiKey}\r\n",
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($deletedUrl, false, $context);
        $decoded = is_string($body) ? json_decode($body, true) : null;
        if (!is_array($decoded) || !isset($decoded['models']) || !is_array($decoded['models'])) {
            throw new RuntimeException('Stripchat returned an invalid deleted-models response.');
        }

        $usernames = [];
        foreach ($decoded['models'] as $row) {
            $username = is_array($row) ? trim((string) ($row['username'] ?? '')) : '';
            if ($username !== '' && strlen($username) <= 190) {
                $usernames[strtolower($username)] = $username;
            }
        }
        $directory = dirname($statePath);
        if ((is_dir($directory) || @mkdir($directory, 0775, true)) && is_dir($directory)) {
            @file_put_contents($statePath, json_encode(['checked_at' => $now]), LOCK_EX);
        }

        return array_values($usernames);
    }

    private function isHttpsUrl(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function isDeletedEndpointAllowed(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'go.whitetrafsa.com'
            && rtrim((string) parse_url($url, PHP_URL_PATH), '/') === '/app/models-ext/models/deleted'
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
}
