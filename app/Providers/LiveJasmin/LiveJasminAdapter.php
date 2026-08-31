<?php

declare(strict_types=1);

namespace LiveCamForge\Providers\LiveJasmin;

use LiveCamForge\Core\PerformerCountry;
use LiveCamForge\Core\VisitorGeo;
use LiveCamForge\Core\Config;
use LiveCamForge\Core\PerformerTypes;
use LiveCamForge\Models\Performer;
use LiveCamForge\Providers\ProviderCapabilities;
use LiveCamForge\Providers\AffiliateTrackingProviderInterface;
use LiveCamForge\Providers\ProviderInterface;
use LiveCamForge\Providers\ProviderPlayer;
use RuntimeException;

final class LiveJasminAdapter implements ProviderInterface, AffiliateTrackingProviderInterface
{
    public function __construct(private Config $config)
    {
    }

    public function name(): string
    {
        return 'livejasmin';
    }

    public function displayName(): string
    {
        return 'LiveJasmin';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            embed: true,
            roomStatus: true,
            age: true,
            viewers: false,
            tags: true,
            mediaProxy: true,
            affiliateLinks: true,
            offlineFallback: false,
            geoRestrictions: true,
            postbackTracking: true,
        );
    }

    public function fetch(): array
    {
        $this->assertConfigured();
        $performers = [];
        $allowedTypes = PerformerTypes::fromConfig($this->config);
        $categories = $this->categories($allowedTypes);
        if ($categories === []) {
            throw new RuntimeException('No LiveJasmin Model Feed category matches the enabled performer types.');
        }

        foreach ($categories as $category) {
            $payload = $this->request($category);
            $rows = $payload['data']['models'] ?? null;
            if (($payload['status'] ?? '') !== 'OK' || !is_array($rows)) {
                throw new RuntimeException('LiveJasmin returned an invalid response.');
            }

            $rowCount = count($rows);
            foreach (array_values($rows) as $index => $row) {
                if (!is_array($row)) {
                    continue;
                }
                $popularityScore = $rowCount === 1 ? 1.0 : 1.0 - ((int) $index / ($rowCount - 1));
                $performer = $this->normalize($row, $category, $popularityScore);
                if ($performer !== null && PerformerTypes::accepts($performer->gender, $allowedTypes)) {
                    $performers[$performer->providerId] = $performer;
                }
            }
        }

        return array_values($performers);
    }

    public function player(array $performer, array $options = []): ?ProviderPlayer
    {
        $username = trim((string) ($performer['username'] ?? ''));
        if ($username === '' || preg_match('/^[A-Za-z0-9_-]{1,100}$/', $username) !== 1) {
            return null;
        }

        $streamOnly = $this->playerMode() === 'stream_only';
        $queryParams = [
            'c' => 'object_container',
            'site' => (string) $this->config->get('livejasmin.site_id', 'jasmin'),
            'cobrandId' => (string) $this->config->get('livejasmin.cobrand_id', ''),
            'psid' => $this->psId(),
            'pstool' => (string) $this->config->get(
                $streamOnly ? 'livejasmin.stream_only_widget_tool' : 'livejasmin.widget_tool',
                $streamOnly ? '202_1' : '320_1'
            ),
            'psprogram' => (string) $this->config->get('livejasmin.program', 'revs'),
            'campaign_id' => (string) $this->config->get('livejasmin.campaign_id', ''),
            'category' => $this->widgetCategory((string) ($performer['gender'] ?? '')),
            'forcedPerformers' => [$username],
            'vp' => [
                'showChat' => $streamOnly ? '' : 'true',
                'chatAutoHide' => '',
                'showCallToAction' => $streamOnly ? '' : 'true',
                'showPerformerName' => $streamOnly ? '' : 'true',
                'showPerformerStatus' => $streamOnly ? '' : 'true',
            ],
            'ms_notrack' => '1',
            'subAffId' => $this->playerSubAffiliateId($options),
        ];
        if (!$streamOnly) {
            $queryParams['ctaLabelKey'] = (string) $this->config->get('livejasmin.cta_label_key', 'udmn');
            $queryParams['landingTarget'] = (string) $this->config->get('livejasmin.landing_target', 'freechat');
        }
        $query = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);
        $endpointKey = $streamOnly ? 'livejasmin.stream_only_widget_endpoint' : 'livejasmin.widget_endpoint';
        $endpointDefault = $streamOnly ? 'https://edwmcr.com/embed/lf' : 'https://edwmcr.com/embed/lfcht';
        $url = rtrim((string) $this->config->get($endpointKey, $endpointDefault), '?') . '?' . $query;
        if (!$this->isWidgetUrlAllowed($url)) {
            return null;
        }

        $timeout = max(5000, min(30000, (int) $this->config->get('livejasmin.player_timeout_ms', 12000)));

        return new ProviderPlayer(
            mode: ProviderPlayer::MODE_SCRIPT,
            url: $url,
            timeoutMs: $timeout,
            sandboxWrapper: true,
        );
    }

    public function resolvePlayer(ProviderPlayer $player): ?ProviderPlayer
    {
        return $player->mode === ProviderPlayer::MODE_SCRIPT && $this->isWidgetUrlAllowed($player->url)
            ? $player
            : null;
    }

    public function isEmbedUrlAllowed(string $url): bool
    {
        return $this->isWidgetUrlAllowed($url);
    }

    public function isRoomUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'https'
            && ($host === 'ctwmsg.com' || str_ends_with($host, '.ctwmsg.com'))
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function isMediaUrlAllowed(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return $scheme === 'https'
            && ($host === 'vcmdiawe.com' || str_ends_with($host, '.vcmdiawe.com'))
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    public function trackedRoomUrl(string $url, string $sid, string $track): string
    {
        if (!(bool) $this->config->get('livejasmin.postback.enabled', false)
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
        $query['subAffId'] = $sid;

        $tracked = $parts['scheme'] . '://' . $parts['host'];
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

    private function playerSubAffiliateId(array $options): string
    {
        if ((bool) $this->config->get('livejasmin.postback.enabled', false)) {
            $sid = trim((string) ($options['sub_aff_id'] ?? ''));
            if (preg_match('/^[A-Za-z0-9_.-]{1,120}$/', $sid) === 1) {
                return $sid;
            }
        }

        $fallback = trim((string) $this->config->get('livejasmin.sub_aff_id', ''));
        return preg_match('/^[A-Za-z0-9_.-]{1,120}$/', $fallback) === 1 ? $fallback : '';
    }

    private function request(string $category): array
    {
        $imageSize = trim((string) $this->config->get('livejasmin.image_size', '896x504'));
        if (preg_match('/^[1-9][0-9]{1,3}x[1-9][0-9]{1,3}$/', $imageSize) !== 1) {
            $imageSize = '896x504';
        }
        $query = http_build_query([
            'siteId' => (string) $this->config->get('livejasmin.site_id', 'jasmin'),
            'psId' => $this->psId(),
            'psTool' => (string) $this->config->get('livejasmin.feed_tool', '213_1'),
            'psProgram' => (string) $this->config->get('livejasmin.program', 'revs'),
            'campaignId' => (string) $this->config->get('livejasmin.campaign_id', ''),
            'category' => $category,
            'limit' => max(1, min(500, (int) $this->config->get('livejasmin.limit', 500))),
            'imageSizes' => $imageSize,
            'imageType' => (string) $this->config->get('livejasmin.image_type', 'ex'),
            'showOffline' => 0,
            'onlyFreeStatus' => (bool) $this->config->get('livejasmin.only_free_status', true) ? 1 : 0,
            'extendedDetails' => 1,
            'responseFormat' => 'json',
            'performerId' => '',
            'subAffId' => (string) $this->config->get('livejasmin.sub_aff_id', ''),
            'accessKey' => $this->accessKey(),
            'legacyRedirect' => 1,
            'customOrder' => (string) $this->config->get('livejasmin.order', 'most_popular'),
        ], '', '&', PHP_QUERY_RFC3986);
        $endpoint = trim((string) $this->config->get('livejasmin.feed_endpoint', 'https://atwmcd.com/api/model/feed'));
        if (!$this->isFeedEndpointAllowed($endpoint)) {
            throw new RuntimeException('The configured LiveJasmin feed endpoint is not allowed.');
        }
        $url = rtrim($endpoint, '?') . '?' . $query;
        $context = stream_context_create(['http' => [
            'timeout' => max(5, min(60, (int) $this->config->get('livejasmin.timeout_seconds', 20))),
            'user_agent' => 'LiveCamForge/' . (string) $this->config->get('version', 'unknown'),
            'header' => "Accept: application/json\r\n",
            'ignore_errors' => true,
        ]]);
        $body = @file_get_contents($url, false, $context);
        if ($body === false) {
            throw new RuntimeException('Unable to contact the LiveJasmin provider.');
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('LiveJasmin returned an invalid JSON response.');
        }

        return $decoded;
    }

    private function normalize(array $row, string $requestedCategory, ?float $popularityScore = null): ?Performer
    {
        $username = trim((string) ($row['performerId'] ?? ''));
        $providerId = trim((string) ($row['uniqueModelId'] ?? $username));
        if ($username === '' || $providerId === '') {
            return null;
        }
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        $roomStatus = $this->normalizeRoomStatus($status, $row);
        $online = !in_array($status, ['offline', 'unavailable'], true);
        $persons = is_array($row['persons'] ?? null) ? array_values($row['persons']) : [];
        $person = is_array($persons[0] ?? null) ? $persons[0] : [];
        $body = is_array($person['body'] ?? null) ? $person['body'] : [];
        $details = is_array($row['details'] ?? null) ? $row['details'] : [];
        $pictureUrls = is_array($row['profilePictureUrl'] ?? null) ? $row['profilePictureUrl'] : [];
        $imageSize = trim((string) $this->config->get('livejasmin.image_size', '896x504'));
        $imageKey = 'size' . preg_replace('/[^0-9x]/', '', $imageSize);
        $imageUrl = trim((string) ($pictureUrls[$imageKey] ?? reset($pictureUrls) ?: ''));
        if (!$this->isMediaUrlAllowed($imageUrl)) {
            $imageUrl = null;
        }
        $roomUrl = trim((string) ($row['chatRoomUrl'] ?? ''));
        if (!$this->isRoomUrlAllowed($roomUrl)) {
            $roomUrl = '#';
        }

        $rawTags = [];
        foreach (['appearances', 'willingnesses', 'languages'] as $field) {
            if (is_array($details[$field] ?? null)) {
                array_push($rawTags, ...$details[$field]);
            }
        }
        foreach ([$row['ethnicity'] ?? null, $body['build'] ?? null, $body['hairColor'] ?? null] as $value) {
            if (is_scalar($value) && trim((string) $value) !== '') {
                $rawTags[] = $value;
            }
        }
        $tags = [];
        foreach ($rawTags as $tag) {
            $slug = strtolower(trim((string) $tag));
            $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
            $slug = trim($slug, '-');
            if ($slug !== '' && strlen($slug) <= 80) {
                $tags[$slug] = true;
            }
        }

        if (!array_key_exists('bannedCountries', $row) || !is_array($row['bannedCountries'])) {
            throw new RuntimeException('LiveJasmin omitted the required bannedCountries field.');
        }
        $geoBlocks = [];
        foreach ($row['bannedCountries'] as $blockedCountry) {
            $code = VisitorGeo::normalizeBlock($blockedCountry);
            if ($code === null) {
                throw new RuntimeException('LiveJasmin returned an invalid bannedCountries value.');
            }
            $geoBlocks[$code] = true;
        }

        return new Performer(
            provider: $this->name(),
            providerId: $providerId,
            username: $username,
            displayName: trim((string) ($row['displayName'] ?? '')) ?: $username,
            gender: $this->normalizeGender($row['category'] ?? $requestedCategory, $persons),
            age: $this->normalizeAge($person['age'] ?? null),
            imageUrl: $imageUrl,
            previewUrl: $imageUrl,
            embedUrl: null,
            roomStatus: $roomStatus,
            roomUrl: $roomUrl,
            viewers: null,
            tags: array_slice(array_keys($tags), 0, 80),
            online: $online,
            providerNew: $this->newFlag($row),
            geoBlocks: array_keys($geoBlocks),
            countryCode: PerformerCountry::normalize(
                $person['countryCode'] ?? $person['country'] ?? $details['countryCode'] ?? $details['country']
                    ?? $row['countryCode'] ?? $row['country'] ?? null
            ),
            popularityScore: $popularityScore,
        );
    }

    private function newFlag(array $row): ?bool
    {
        if (!array_key_exists('isNewbie', $row)) {
            return null;
        }
        $value = filter_var($row['isNewbie'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $value;
    }

    private function normalizeRoomStatus(string $status, array $row): string
    {
        if ((int) ($row['inExclusivePrivate'] ?? 0) === 1 || str_contains($status, 'private')) {
            return 'private';
        }
        if (str_contains($status, 'group')) {
            return 'group';
        }
        if (in_array($status, ['offline', 'away', 'unavailable'], true)) {
            return 'away';
        }
        if ($status === 'free_chat' || str_contains($status, 'public') || $status === 'free') {
            return 'public';
        }

        return 'unknown';
    }

    private function normalizeGender(mixed $category, array $persons): ?string
    {
        if (count($persons) > 1) {
            return 'c';
        }
        $value = strtolower(trim((string) $category));
        if (str_contains($value, 'couple')) {
            return 'c';
        }
        if (in_array($value, ['girl', 'female', 'women', 'lesbian'], true)) {
            return 'f';
        }
        if (in_array($value, ['boy', 'guy', 'gay', 'male', 'men'], true)) {
            return 'm';
        }
        if (str_contains($value, 'trans')) {
            return 't';
        }
        $sex = strtolower(trim((string) (($persons[0]['sex'] ?? ''))));

        return match ($sex) {
            'female' => 'f',
            'male' => 'm',
            'transsexual', 'trans' => 't',
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

    private function playerMode(): string
    {
        return strtolower(trim((string) $this->config->get('livejasmin.player_mode', 'stream_only'))) === 'full_embed'
            ? 'full_embed'
            : 'stream_only';
    }

    private function isWidgetUrlAllowed(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'edwmcr.com'
            && in_array((string) parse_url($url, PHP_URL_PATH), ['/embed/lfcht', '/embed/lf'], true)
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    private function categories(array $allowedTypes): array
    {
        $configured = $this->config->get('livejasmin.categories', ['girl', 'gay', 'transgender', 'lesbian', 'couple']);
        $configured = is_array($configured) ? $configured : [$configured];
        $aliases = ['boy' => 'gay', 'guy' => 'gay', 'trans' => 'transgender', 'transgendered' => 'transgender'];
        $allowed = ['girl', 'gay', 'transgender', 'lesbian', 'couple'];
        $categories = [];
        foreach ($configured as $category) {
            $category = strtolower(trim((string) $category));
            $category = $aliases[$category] ?? $category;
            if (in_array($category, $allowed, true)) {
                $categories[$category] = true;
            }
        }

        $mapping = [
            'girl' => ['f'],
            'gay' => ['m'],
            'transgender' => ['t'],
            // LiveJasmin may return either one woman or multiple people from
            // this category, so keep it for both relevant normalized scopes.
            'lesbian' => ['f', 'c'],
            'couple' => ['c'],
        ];

        return array_values(array_filter(array_keys($categories), static function (string $category) use ($allowedTypes, $mapping): bool {
            return array_intersect($mapping[$category] ?? [], $allowedTypes) !== [];
        }));
    }

    private function widgetCategory(string $gender): string
    {
        $mapping = $this->config->get('livejasmin.widget_categories', []);
        if (is_array($mapping) && isset($mapping[$gender]) && preg_match('/^[a-z0-9_-]{1,40}$/', (string) $mapping[$gender]) === 1) {
            return (string) $mapping[$gender];
        }

        return match ($gender) {
            'm' => 'boy',
            't' => 'trans',
            'c' => 'couple',
            default => 'girl',
        };
    }

    private function assertConfigured(): void
    {
        $this->psId();
        $this->accessKey();
    }

    private function psId(): string
    {
        $psId = trim((string) $this->config->get('livejasmin.ps_id', ''));
        if ($psId === '' || preg_match('/^[A-Za-z0-9_-]{1,100}$/', $psId) !== 1) {
            throw new RuntimeException('Enter the LiveJasmin ps_id in config/local.php.');
        }

        return $psId;
    }

    private function accessKey(): string
    {
        $accessKey = trim((string) $this->config->get('livejasmin.access_key', ''));
        if ($accessKey === '' || preg_match('/^[A-Za-z0-9_-]{16,200}$/', $accessKey) !== 1) {
            throw new RuntimeException('Enter the LiveJasmin access_key in config/local.php.');
        }

        return $accessKey;
    }

    private function isFeedEndpointAllowed(string $url): bool
    {
        return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === 'atwmcd.com'
            && rtrim((string) parse_url($url, PHP_URL_PATH), '/') === '/api/model/feed'
            && parse_url($url, PHP_URL_USER) === null
            && parse_url($url, PHP_URL_PASS) === null
            && filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

}
