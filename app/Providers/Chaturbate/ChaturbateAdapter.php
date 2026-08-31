<?php

declare(strict_types=1);

namespace LiveCamForge\Providers\Chaturbate;

use LiveCamForge\Core\PerformerCountry;
use LiveCamForge\Core\Config;
use LiveCamForge\Core\PerformerTypes;
use LiveCamForge\Models\Performer;
use LiveCamForge\Providers\ProviderInterface;
use LiveCamForge\Providers\AffiliateTrackingProviderInterface;
use LiveCamForge\Providers\ProviderCapabilities;
use LiveCamForge\Providers\ProviderPlayer;
use RuntimeException;

final class ChaturbateAdapter implements ProviderInterface, AffiliateTrackingProviderInterface
{
    public function __construct(private Config $config)
    {
    }

    public function name(): string
    {
        return 'chaturbate';
    }

    public function displayName(): string
    {
        return 'Chaturbate';
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
            postbackTracking: true,
        );
    }

    public function fetch(): array
    {
        $wm = trim((string) $this->config->get('chaturbate.wm'));
        if ($wm === '') {
            throw new RuntimeException('Enter the WM affiliate code in the configuration.');
        }

        $performers = [];
        $pageSize = max(1, min(500, (int) $this->config->get('chaturbate.page_size', 500)));
        $maxPages = max(1, (int) $this->config->get('chaturbate.max_pages', 100));
        $allowedTypes = PerformerTypes::fromConfig($this->config);
        // The provider accepts one normalized gender per request. Preserve the
        // historical single-feed path when every type is enabled; otherwise
        // request only the selected sections and merge them by provider ID.
        $requestedTypes = count($allowedTypes) === count(PerformerTypes::VALUES) ? [null] : $allowedTypes;

        foreach ($requestedTypes as $requestedType) {
            $seenPages = [];
            $offset = 0;
            for ($page = 0; $page < $maxPages; $page++) {
                $payload = $this->request($offset, $pageSize, $wm, $requestedType);
                $rows = $payload['results'] ?? $payload;
                if (!is_array($rows) || $rows === []) {
                    break;
                }

                $fingerprint = $this->pageFingerprint($rows);
                if (isset($seenPages[$fingerprint])) {
                    break;
                }
                $seenPages[$fingerprint] = true;

                foreach ($rows as $row) {
                    if (is_array($row)) {
                        $performer = $this->normalize($row);
                        if (PerformerTypes::accepts($performer->gender, $allowedTypes)) {
                            $performers[$performer->providerId] = $performer;
                        }
                    }
                }

                $received = count($rows);
                $offset += $received;
                $reportedCount = $payload['count'] ?? $payload['total_count'] ?? null;

                if (($reportedCount !== null && is_numeric($reportedCount) && $offset >= (int) $reportedCount)
                    || $received < $pageSize
                ) {
                    break;
                }
            }
        }

        return array_values($performers);
    }

    private function request(int $offset, int $pageSize, string $wm, ?string $gender = null): array
    {
        $parameters = [
            'wm' => $wm,
            'client_ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'format' => 'json',
            'limit' => $pageSize,
            'offset' => $offset,
        ];
        if ($gender !== null) {
            $parameters['gender'] = $gender;
        }
        $query = http_build_query($parameters);
        $endpoint = trim((string) $this->config->get('chaturbate.endpoint'));
        if (!$this->isApiEndpointAllowed($endpoint)) {
            throw new RuntimeException('The configured Chaturbate endpoint is not allowed.');
        }
        $url = rtrim($endpoint, '?') . '?' . $query;
        $context = stream_context_create(['http' => [
            'timeout' => (int) $this->config->get('chaturbate.timeout_seconds', 15),
            'user_agent' => 'LiveCamForge/' . (string) $this->config->get('version', 'unknown'),
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('Unable to contact the Chaturbate provider.');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('The provider returned an invalid response.');
        }

        return $decoded;
    }

    private function pageFingerprint(array $rows): string
    {
        $identifiers = [];
        foreach ($rows as $row) {
            if (is_array($row)) {
                $identifiers[] = (string) ($row['id'] ?? $row['username'] ?? '');
            }
        }

        return hash('sha256', json_encode($identifiers, JSON_UNESCAPED_SLASHES) ?: '');
    }

    private function normalize(array $row): Performer
    {
        $username = (string) ($row['username'] ?? $row['room_subject'] ?? 'unknown');
        $viewerValue = $row['num_users'] ?? $row['viewers'] ?? null;
        $tags = $row['tags'] ?? [];
        if (is_string($tags)) {
            $tags = preg_split('/[,\s]+/', $tags, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        }

        return new Performer(
            provider: $this->name(),
            providerId: (string) ($row['id'] ?? $username),
            username: $username,
            displayName: (string) ($row['display_name'] ?? $username),
            gender: isset($row['gender']) ? (string) $row['gender'] : null,
            age: $this->normalizeAge($row['age'] ?? null),
            imageUrl: $row['image_url'] ?? $row['image_url_360x270'] ?? null,
            previewUrl: $row['image_url_360x270'] ?? $row['image_url'] ?? null,
            embedUrl: $this->extractEmbedUrl($row),
            roomStatus: $this->normalizeRoomStatus($row),
            // The standard URL can resolve to a generic landing page. The
            // revshare field is the provider-generated, performer-specific
            // affiliate destination and already contains campaign and room.
            roomUrl: (string) ($row['chat_room_url_revshare'] ?? $row['chat_room_url'] ?? $row['room_url'] ?? '#'),
            viewers: is_numeric($viewerValue) ? max(0, (int) $viewerValue) : null,
            tags: array_values(array_filter(array_map('strval', is_array($tags) ? $tags : []))),
            online: true,
            providerNew: $this->newFlag($row),
            countryCode: PerformerCountry::normalize($row['country_code'] ?? $row['country'] ?? null),
        );
    }

    private function newFlag(array $row): ?bool
    {
        foreach (['is_new', 'is_new_model'] as $field) {
            if (!array_key_exists($field, $row)) {
                continue;
            }
            $value = filter_var($row[$field], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($value !== null) {
                return $value;
            }
        }

        return null;
    }

    private function normalizeRoomStatus(array $row): string
    {
        foreach (['is_private', 'private_show', 'in_private_show'] as $field) {
            if (array_key_exists($field, $row) && filter_var($row[$field], FILTER_VALIDATE_BOOLEAN)) {
                return 'private';
            }
        }
        foreach (['is_group', 'group_show', 'in_group_show'] as $field) {
            if (array_key_exists($field, $row) && filter_var($row[$field], FILTER_VALIDATE_BOOLEAN)) {
                return 'group';
            }
        }

        $status = '';
        foreach (['current_show', 'room_status', 'show_status', 'status'] as $field) {
            if (isset($row[$field]) && is_scalar($row[$field]) && trim((string) $row[$field]) !== '') {
                $status = strtolower(trim((string) $row[$field]));
                break;
            }
        }
        $status = str_replace(['_', '-'], ' ', $status);

        if (str_contains($status, 'private')) {
            return 'private';
        }
        if (str_contains($status, 'group')) {
            return 'group';
        }
        if (preg_match('/\\b(away|hidden|password|offline)\\b/', $status) === 1) {
            return 'away';
        }
        if ($status === '' || preg_match('/\\b(public|free|open|live|online)\\b/', $status) === 1) {
            return 'public';
        }

        // This endpoint lists online rooms. An unrecognized or missing status
        // must not disable the whole catalog; only explicit non-public signals do.
        return 'public';
    }

    private function normalizeAge(mixed $value): ?int
    {
        if (!is_numeric($value)) {
            return null;
        }
        $age = (int) $value;

        return $age >= 18 && $age <= 98 ? $age : null;
    }

    private function extractEmbedUrl(array $row): ?string
    {
        $embed = trim((string) ($row['iframe_embed_revshare'] ?? $row['iframe_embed'] ?? ''));
        if ($embed === '') {
            return null;
        }

        if (preg_match('~\\bsrc\\s*=\\s*(["\x27])(.*?)\\1~is', $embed, $match) === 1) {
            $embed = $match[2];
        }

        $embed = html_entity_decode(trim($embed), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        if (str_starts_with($embed, '//')) {
            $embed = 'https:' . $embed;
        }

        return $this->isEmbedUrlAllowed($embed) ? $embed : null;
    }

    public function isEmbedUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $isProviderHost = $host === 'chaturbate.com' || str_ends_with($host, '.chaturbate.com');

        return $scheme === 'https' && $isProviderHost && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function player(array $performer, array $options = []): ?ProviderPlayer
    {
        $url = trim((string) ($performer['embed_url'] ?? ''));
        if (!$this->isEmbedUrlAllowed($url)) {
            return null;
        }
        if ($this->playerMode() === 'stream_only') {
            $url = $this->withQueryParameter($url, 'embed_video_only', '1');
        }

        return $this->isEmbedUrlAllowed($url)
            ? new ProviderPlayer(ProviderPlayer::MODE_IFRAME, $url)
            : null;
    }

    private function playerMode(): string
    {
        return strtolower(trim((string) $this->config->get('chaturbate.player_mode', 'stream_only'))) === 'full_embed'
            ? 'full_embed'
            : 'stream_only';
    }

    private function withQueryParameter(string $url, string $name, string $value): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url . $separator . rawurlencode($name) . '=' . rawurlencode($value);
    }

    public function resolvePlayer(ProviderPlayer $player): ?ProviderPlayer
    {
        return $player->mode === ProviderPlayer::MODE_IFRAME && $this->isEmbedUrlAllowed($player->url)
            ? $player
            : null;
    }

    public function isRoomUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'https'
            && ($host === 'chaturbate.com' || str_ends_with($host, '.chaturbate.com'))
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function isMediaUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $isProviderHost = $host === 'chaturbate.com' || str_ends_with($host, '.chaturbate.com');
        $isMediaHost = $host === 'highwebmedia.com' || str_ends_with($host, '.highwebmedia.com');
        $isMmcdnHost = $host === 'live.mmcdn.com' || str_ends_with($host, '.live.mmcdn.com');

        return $scheme === 'https'
            && ($isProviderHost || $isMediaHost || $isMmcdnHost)
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function trackedRoomUrl(string $url, string $sid, string $track): string
    {
        if (!(bool) $this->config->get('chaturbate.postback.enabled', false)
            || !$this->isRoomUrlAllowed($url)
            || preg_match('/^[A-Za-z0-9_.-]{1,120}$/', $sid) !== 1
        ) {
            return $url;
        }

        $track = preg_replace('/[^a-z0-9_.-]/i', '', $track) ?? '';
        $parts = parse_url($url);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host'])) {
            return $url;
        }
        $query = [];
        parse_str((string) ($parts['query'] ?? ''), $query);
        $query['track'] = substr($track ?: 'livecamforge', 0, 100);
        $query['sid'] = $sid;

        $tracked = $parts['scheme'] . '://';
        if (isset($parts['user'])) {
            $tracked .= $parts['user'] . (isset($parts['pass']) ? ':' . $parts['pass'] : '') . '@';
        }
        $tracked .= $parts['host'];
        if (isset($parts['port'])) {
            $tracked .= ':' . $parts['port'];
        }
        $tracked .= (string) ($parts['path'] ?? '');
        $tracked .= '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        if (isset($parts['fragment'])) {
            $tracked .= '#' . $parts['fragment'];
        }

        return $this->isRoomUrlAllowed($tracked) ? $tracked : $url;
    }

    private function isApiEndpointAllowed(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'chaturbate.com'
            && rtrim((string) parse_url($url, PHP_URL_PATH), '/') === '/api/public/affiliates/onlinerooms'
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

}
