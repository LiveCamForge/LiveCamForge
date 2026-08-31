<?php

declare(strict_types=1);

namespace LiveCamForge\Providers\BongaCams;

use LiveCamForge\Core\PerformerCountry;
use LiveCamForge\Core\Config;
use LiveCamForge\Models\Performer;
use LiveCamForge\Providers\ProviderCapabilities;
use LiveCamForge\Providers\ProviderInterface;
use LiveCamForge\Providers\ProviderPlayer;
use RuntimeException;

final class BongaCamsAdapter implements ProviderInterface
{
    public function __construct(private Config $config)
    {
    }

    public function name(): string
    {
        return 'bongacams';
    }

    public function displayName(): string
    {
        return 'BongaCams';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            embed: true,
            roomStatus: false,
            age: true,
            viewers: true,
            tags: true,
            mediaProxy: true,
            affiliateLinks: true,
            offlineFallback: true,
        );
    }

    public function fetch(): array
    {
        $campaignId = $this->campaignId();
        $clientIp = $this->resolveClientIp();

        $performers = [];
        $offset = 0;
        $pageSize = max(1, min(500, (int) $this->config->get('bongacams.page_size', 500)));
        $maxPages = max(1, min(100, (int) $this->config->get('bongacams.max_pages', 20)));

        for ($page = 0; $page < $maxPages; $page++) {
            $payload = $this->request($campaignId, $clientIp, $offset, $pageSize);
            $rows = $payload['models'] ?? [];
            if (!is_array($rows) || $rows === []) {
                break;
            }

            foreach ($rows as $row) {
                if (!is_array($row) || trim((string) ($row['username'] ?? '')) === '') {
                    continue;
                }
                $performer = $this->normalize($row);
                $performers[$performer->providerId] = $performer;
            }

            $received = count($rows);
            $offset += $received;
            $reportedCount = is_numeric($payload['online_models'] ?? null)
                ? (int) $payload['online_models']
                : null;
            if (($reportedCount !== null && $offset >= $reportedCount) || $received < $pageSize) {
                break;
            }
        }

        return array_values($performers);
    }

    public function player(array $performer, array $options = []): ?ProviderPlayer
    {
        $username = trim((string) ($performer['username'] ?? ''));
        $streamUrl = trim((string) ($performer['embed_url'] ?? ''));
        if ($username === '' || !$this->isStreamUrlAllowed($streamUrl)) {
            return null;
        }

        $offlineFallback = strtolower(trim((string) ($options['offline_fallback'] ?? 'profile')));
        if (!in_array($offlineFallback, ['homepage', 'profile'], true)) {
            $offlineFallback = 'profile';
        }
        $fallbackValues = $this->config->get('bongacams.offline_fallback_values', []);
        $providerFallback = is_array($fallbackValues)
            ? strtolower(trim((string) ($fallbackValues[$offlineFallback] ?? $offlineFallback)))
            : $offlineFallback;
        if (preg_match('/^[a-z0-9_-]{1,40}$/', $providerFallback) !== 1) {
            $providerFallback = $offlineFallback;
        }
        $queryParams = [
            'c' => $this->campaignId(),
            'type' => 'embed_chat',
            'models' => [$username],
            'model_offline' => $providerFallback,
        ];
        if ($this->playerMode() === 'stream_only') {
            $queryParams['top_model'] = 1;
            $queryParams['stream_only_size'] = 'full';
        }
        $query = http_build_query($queryParams);
        $widgetUrl = rtrim((string) $this->config->get('bongacams.widget_endpoint', 'https://bngprm.com/promo.php'), '?')
            . '?' . $query;
        if (!$this->isWidgetUrlAllowed($widgetUrl)) {
            return null;
        }

        $timeout = max(5000, min(30000, (int) $this->config->get('bongacams.player_timeout_ms', 12000)));
        return new ProviderPlayer(
            ProviderPlayer::MODE_SCRIPT,
            $widgetUrl,
            $timeout,
            ProviderPlayer::MODE_HLS,
            $streamUrl,
            $timeout,
        );
    }

    private function playerMode(): string
    {
        return strtolower(trim((string) $this->config->get('bongacams.player_mode', 'stream_only'))) === 'full_embed'
            ? 'full_embed'
            : 'stream_only';
    }

    public function resolvePlayer(ProviderPlayer $player): ?ProviderPlayer
    {
        if ($player->mode === ProviderPlayer::MODE_HLS) {
            return $this->isStreamUrlAllowed($player->url) ? $player : null;
        }

        if ($player->mode !== ProviderPlayer::MODE_SCRIPT || !$this->isWidgetUrlAllowed($player->url)) {
            return null;
        }

        $context = stream_context_create(['http' => [
            'timeout' => max(5, (int) $this->config->get('bongacams.timeout_seconds', 20)),
            'user_agent' => 'LiveCamForge/' . (string) $this->config->get('version', 'unknown'),
            'header' => "Accept: application/javascript, text/javascript;q=0.9, */*;q=0.1\r\n",
            'ignore_errors' => true,
        ]]);
        $script = @file_get_contents($player->url, false, $context);
        if (!is_string($script)
            || preg_match('~\bsrc=["\x27]([^"\x27]+)["\x27]~i', $script, $match) !== 1
        ) {
            return null;
        }

        $iframeUrl = html_entity_decode(str_replace('\\/', '/', trim($match[1])), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return $this->isPlayerFrameUrlAllowed($iframeUrl)
            ? new ProviderPlayer(ProviderPlayer::MODE_WRAPPED_IFRAME, $iframeUrl)
            : null;
    }

    public function isEmbedUrlAllowed(string $url): bool
    {
        return $this->isWidgetUrlAllowed($url)
            || $this->isStreamUrlAllowed($url)
            || $this->isPlayerFrameUrlAllowed($url);
    }

    private function isWidgetUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $path = (string) parse_url($url, PHP_URL_PATH);

        return $scheme === 'https'
            && $host === 'bngprm.com'
            && $path === '/promo.php'
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function isStreamUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'https'
            && ($host === 'bcvcdn.com' || str_ends_with($host, '.bcvcdn.com'))
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function isPlayerFrameUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'https'
            && preg_match('/^(?:[a-z0-9-]+\.)?bongacams[0-9]*\.com$/', $host) === 1
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function isRoomUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'https'
            && ($host === 'bongacams.com' || str_ends_with($host, '.bongacams.com'))
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function isMediaUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'https'
            && ($host === 'bgicdn.com' || str_ends_with($host, '.bgicdn.com'))
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function request(int $campaignId, string $clientIp, int $offset, int $pageSize): array
    {
        $query = http_build_query([
            'c' => $campaignId,
            'client_ip' => $clientIp,
            'sort' => 'rank',
            'section' => 'all',
            'limit' => $pageSize,
            'offset' => $offset,
        ]);
        $endpoint = trim((string) $this->config->get('bongacams.endpoint', 'https://bngprm.com/api/v2/models-online'));
        if (!$this->isApiEndpointAllowed($endpoint)) {
            throw new RuntimeException('The configured BongaCams endpoint is not allowed.');
        }
        $url = rtrim($endpoint, '?') . '?' . $query;
        $context = stream_context_create(['http' => [
            'timeout' => max(5, (int) $this->config->get('bongacams.timeout_seconds', 20)),
            'user_agent' => 'LiveCamForge/' . (string) $this->config->get('version', 'unknown'),
            'header' => "Accept: application/json\r\n",
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('Unable to contact the BongaCams provider.');
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded) || !isset($decoded['models']) || !is_array($decoded['models'])) {
            throw new RuntimeException('BongaCams returned an invalid response.');
        }

        return $decoded;
    }

    private function normalize(array $row): Performer
    {
        $username = trim((string) ($row['username'] ?? ''));
        if ($username === '') {
            throw new RuntimeException('BongaCams returned a performer without a username.');
        }
        $profileImages = is_array($row['profile_images'] ?? null) ? $row['profile_images'] : [];
        $liveImages = is_array($row['live_images'] ?? null) ? $row['live_images'] : [];
        $streamUrl = trim((string) ($row['stream_feed_url'] ?? ''));
        $roomUrl = '#';
        foreach (['chat_url', 'profile_page_url', 'chat_url_on_home_page'] as $roomField) {
            $candidate = trim((string) ($row[$roomField] ?? ''));
            if ($this->isRoomUrlAllowed($candidate)) {
                $roomUrl = $candidate;
                break;
            }
        }
        $imageUrl = null;
        foreach (['thumbnail_image_big', 'profile_image', 'thumbnail_image_medium'] as $imageField) {
            $candidate = trim((string) ($profileImages[$imageField] ?? ''));
            if ($this->isMediaUrlAllowed($candidate)) {
                $imageUrl = $candidate;
                break;
            }
        }
        $previewUrl = null;
        foreach (['thumbnail_image_big', 'thumbnail_image_medium'] as $imageField) {
            $candidate = trim((string) ($liveImages[$imageField] ?? ''));
            if ($this->isMediaUrlAllowed($candidate)) {
                $previewUrl = $candidate;
                break;
            }
        }
        $tags = is_array($row['tags'] ?? null) ? array_values($row['tags']) : [];
        $tags = array_values(array_unique(array_filter(array_map(
            static fn (mixed $tag): string => strtolower(trim((string) $tag)),
            $tags
        ), static fn (string $tag): bool => preg_match('/^[a-z0-9_-]{1,80}$/', $tag) === 1)));
        $viewerValue = $row['members_count'] ?? null;

        return new Performer(
            provider: $this->name(),
            providerId: $username,
            username: $username,
            displayName: trim((string) ($row['display_name'] ?? '')) ?: $username,
            gender: $this->normalizeGender($row['gender'] ?? null),
            age: $this->normalizeAge($row['display_age'] ?? null),
            imageUrl: $imageUrl,
            previewUrl: $previewUrl,
            embedUrl: $this->isEmbedUrlAllowed($streamUrl) ? $streamUrl : null,
            roomStatus: 'unknown',
            roomUrl: $roomUrl,
            viewers: is_numeric($viewerValue) ? max(0, (int) $viewerValue) : null,
            tags: array_slice($tags, 0, 80),
            online: true,
            countryCode: PerformerCountry::normalize(
                $row['country_code'] ?? $row['countryCode'] ?? $row['country'] ?? $row['homecountry'] ?? null
            ),
        );
    }

    private function normalizeGender(mixed $value): ?string
    {
        return match (strtolower(trim((string) $value))) {
            'female' => 'f',
            'male' => 'm',
            'transsexual' => 't',
            'couple_f_m', 'couple_f_f', 'couple_m_m', 'couple_t_t' => 'c',
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

    private function campaignId(): int
    {
        $campaignId = (int) $this->config->get('bongacams.campaign_id', 0);
        if ($campaignId <= 0) {
            throw new RuntimeException('Enter the BongaCams campaign_id in config/local.php.');
        }

        return $campaignId;
    }

    private function resolveClientIp(): string
    {
        $configuredIp = trim((string) $this->config->get('bongacams.client_ip', ''));
        if ($configuredIp !== '') {
            if (!$this->isPublicIpv4($configuredIp)) {
                throw new RuntimeException('BongaCams client_ip must be a public IPv4 address or left empty for automatic detection.');
            }

            return $configuredIp;
        }

        $requestIp = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        if ($this->isPublicIpv4($requestIp)) {
            return $requestIp;
        }

        if ((bool) $this->config->get('bongacams.detect_public_ip', true)) {
            $detectedIp = $this->detectOutboundIp();
            if ($detectedIp !== null) {
                return $detectedIp;
            }
        }

        throw new RuntimeException(
            'Unable to determine the public IPv4 required by BongaCams. Set the BongaCams manual public IPv4 in Admin > Integrations.'
        );
    }

    private function detectOutboundIp(): ?string
    {
        $endpoint = trim((string) $this->config->get(
            'bongacams.ip_resolver_endpoint',
            'https://api4.ipify.org'
        ));
        $host = strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));
        if ($scheme !== 'https' || $host !== 'api4.ipify.org' || filter_var($endpoint, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $context = stream_context_create(['http' => [
            'timeout' => max(2, min(10, (int) $this->config->get('bongacams.ip_resolver_timeout_seconds', 5))),
            'user_agent' => 'LiveCamForge/' . (string) $this->config->get('version', 'unknown'),
            'header' => "Accept: text/plain\r\n",
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($endpoint, false, $context);
        $detectedIp = is_string($body) ? trim($body) : '';

        return $this->isPublicIpv4($detectedIp) ? $detectedIp : null;
    }

    private function isPublicIpv4(string $value): bool
    {
        return filter_var(
            $value,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    private function isApiEndpointAllowed(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'bngprm.com'
            && rtrim((string) parse_url($url, PHP_URL_PATH), '/') === '/api/v2/models-online'
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

}
