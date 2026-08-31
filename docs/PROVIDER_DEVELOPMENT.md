# Provider Development: Step-by-Step Integration Guide

## Purpose

This guide is the implementation walkthrough for adding a provider to LiveCamForge. It is intentionally code-oriented: file names, class names, interfaces, method signatures and integration points match the LiveCamForge 1.0.1 codebase.

The examples use a fictional provider named **ExampleCams**, but they are designed to be copied and adapted inside the current repository rather than translated from generic PHP pseudocode.

There are two different integration paths:

1. **Direct provider** — LiveCamForge calls the provider/API directly and owns the adapter.
2. **CrakRevenue-backed source** — the common `CrakRevenueAdapter` and `CrakRevenueClient` are reused and a new commercial source is registered inside the existing CrakRevenue integration.

Conversion tracking is optional. If the provider supports it, LiveCamForge currently supports two established patterns:

- server-to-server postbacks, following the existing `StripchatPostbackHandler` / `CrakRevenuePostbackHandler` pattern;
- reporting API polling, following `Cam4ConversionSync`.

Do not introduce provider-specific special cases into the public catalog or generic synchronization layer unless the provider contract genuinely requires them.

---

# Part I — Add a direct provider

## 1. Study the contracts before writing the adapter

Start with these files:

| File | Why it matters |
| --- | --- |
| `app/Providers/ProviderInterface.php` | Mandatory adapter contract. |
| `app/Providers/ProviderCapabilities.php` | Declares what the provider exposes. |
| `app/Models/Performer.php` | Exact normalized performer model. |
| `app/Providers/ProviderPlayer.php` | Supported player modes. |
| `app/Providers/AffiliateTrackingProviderInterface.php` | Optional click-ID injection into affiliate URLs. |
| `app/Providers/DeletedPerformersProviderInterface.php` | Optional explicit removal/tombstone support. |
| `app/Providers/ProviderFactory.php` | Provider registry and public routing groups. |
| `app/Services/SyncPerformers.php` | Generic synchronization lifecycle that consumes the adapter. |

A direct adapter must implement `ProviderInterface` exactly:

```php
interface ProviderInterface
{
    public function name(): string;
    public function displayName(): string;
    public function capabilities(): ProviderCapabilities;
    public function fetch(): array;
    public function player(array $performer, array $options = []): ?ProviderPlayer;
    public function resolvePlayer(ProviderPlayer $player): ?ProviderPlayer;
    public function isEmbedUrlAllowed(string $url): bool;
    public function isRoomUrlAllowed(string $url): bool;
    public function isMediaUrlAllowed(string $url): bool;
}
```

Do not change this interface just to accommodate one provider unless the capability is genuinely generic and reusable.

### Recommended reference adapters

Use an existing adapter that resembles the new provider:

- `app/Providers/Stripchat/StripchatAdapter.php` — API feed, tags, viewers, geo restrictions, click-ID tracking and postback support.
- `app/Providers/Chaturbate/ChaturbateAdapter.php` — paginated feed and affiliate tracking.
- `app/Providers/LiveJasmin/LiveJasminAdapter.php` — provider-specific player/embed modes.
- `app/Providers/Cam4/Cam4Adapter.php` — API feed plus conversion polling.
- `app/Providers/BongaCams/BongaCamsAdapter.php` — provider-specific player behavior and IP-sensitive feed requirements.

---

## 2. Create the adapter class

Create a provider directory and adapter, for example:

```text
app/Providers/ExampleCams/ExampleCamsAdapter.php
```

Use the real namespace and interfaces:

```php
<?php

declare(strict_types=1);

namespace LiveCamForge\Providers\ExampleCams;

use LiveCamForge\Core\Config;
use LiveCamForge\Core\PerformerCountry;
use LiveCamForge\Models\Performer;
use LiveCamForge\Providers\AffiliateTrackingProviderInterface;
use LiveCamForge\Providers\ProviderCapabilities;
use LiveCamForge\Providers\ProviderInterface;
use LiveCamForge\Providers\ProviderPlayer;
use RuntimeException;

final class ExampleCamsAdapter implements ProviderInterface, AffiliateTrackingProviderInterface
{
    public function __construct(private Config $config)
    {
    }

    public function name(): string
    {
        return 'examplecams';
    }

    public function displayName(): string
    {
        return 'ExampleCams';
    }

    public function capabilities(): ProviderCapabilities
    {
        return new ProviderCapabilities(
            embed: true,
            roomStatus: true,
            age: true,
            viewers: true,
            tags: true,
            mediaProxy: false,
            affiliateLinks: true,
            offlineFallback: false,
            geoRestrictions: false,
            postbackTracking: true,
            conversionPolling: false,
        );
    }

    // Remaining ProviderInterface methods are added in the next steps.
}
```

Only implement `AffiliateTrackingProviderInterface` when the provider supports a sub-ID/click-ID parameter that can be added to an outbound affiliate URL. If it does not, remove that interface and the later `trackedRoomUrl()` method.

If the provider publishes explicit deletion/tombstone information, also implement `DeletedPerformersProviderInterface`, store the deleted usernames during `fetch()`, and return them from `deletedUsernames()` as Stripchat does.

---

## 3. Declare capabilities accurately

`ProviderCapabilities` is not decorative metadata. Admin and generic application behavior depend on it.

The constructor is:

```php
new ProviderCapabilities(
    embed: bool,
    roomStatus: bool,
    age: bool,
    viewers: bool,
    tags: bool,
    mediaProxy: bool,
    affiliateLinks: bool,
    offlineFallback: bool,
    geoRestrictions: bool,
    postbackTracking: bool,
    conversionPolling: bool,
);
```

Important examples:

- set `viewers: true` only if `Performer::$viewers` contains a meaningful audience count; `SyncPerformers` uses it to normalize popularity;
- set `roomStatus: true` only when public/private/group status is reliable;
- set `geoRestrictions: true` only if normalized geo blocks are actually populated;
- set `postbackTracking: true` only if LiveCamForge has a working postback path for that provider;
- set `conversionPolling: true` only if a reporting API polling service exists.

---

## 4. Add configuration defaults

Edit:

```text
config/app.php
```

Add a provider section with safe defaults. Never hard-code webmaster-specific credentials in the adapter.

Example:

```php
'examplecams' => [
    'affiliate_id' => '',
    'api_key' => '',
    'endpoint' => 'https://api.examplecams.invalid/v1/performers',
    'page_size' => 500,
    'max_pages' => 20,
    'timeout_seconds' => 20,
    'player_timeout_ms' => 12000,
    'postback' => [
        'enabled' => false,
        'secret' => '',
        'require_secret' => true,
        'currency' => 'USD',
    ],
],
```

Use `config/app.php` only for defaults. Machine-local credentials belong in `config/local.php`, normally managed through Admin.

### Secrets and Admin persistence

Provider credentials managed from Admin are saved through:

```text
app/Core/LocalConfigManager.php
```

For a new direct provider, normally update both:

- `LocalConfigManager::values()` — expose non-secret values and boolean `*_set` flags for secrets;
- `LocalConfigManager::saveProviderConfiguration()` — validate and atomically store credentials in `config/local.php`.

Follow the existing secret pattern instead of writing passwords/tokens directly:

```php
$config['examplecams'] = $this->section($config, 'examplecams');
$config['examplecams']['affiliate_id'] = $this->text(
    $input['examplecams_affiliate_id'] ?? '',
    120
);
$this->updateSecret(
    $config['examplecams'],
    'api_key',
    $input,
    'examplecams_api_key'
);
```

`updateSecret()` deliberately preserves the existing secret when the submitted password field is blank and handles explicit `clear_*` checkboxes. Reuse it.

If the provider has non-secret operational settings such as player mode, autoplay, postback enablement or currency, use the established `OperationalSettings` flow in:

```text
app/Core/OperationalSettings.php
```

Do not store secrets in the settings database.

---

## 5. Add the provider to Admin → Integrations

Edit:

```text
templates/admin-provider-configuration.php
```

Add a provider card using the existing direct-provider cards as the markup reference. The form submits with:

```text
action=save_provider_configuration
```

and `admin/index.php` delegates to:

```php
$localConfigManager->saveProviderConfiguration($_POST);
$operationalSettings->saveProviderIntegrations($_POST);
```

Also update the credential status map in:

```text
admin/index.php
```

Inside `$providerCredentialStatus`, add a condition that represents the minimum usable credentials. Example:

```php
'examplecams' => trim((string) $baseConfig->get('examplecams.affiliate_id', '')) !== ''
    && trim((string) $baseConfig->get('examplecams.api_key', '')) !== '',
```

Add translation strings for every new Admin label to:

```text
languages/en.json
languages/it.json
```

The application UI currently ships both language packs even though the canonical project documentation is English-only.

---

## 6. Register the provider in ProviderFactory

Edit:

```text
app/Providers/ProviderFactory.php
```

Import the class:

```php
use LiveCamForge\Providers\ExampleCams\ExampleCamsAdapter;
```

Add the internal name to `availableNames()`:

```php
return [
    'demo',
    'chaturbate',
    'livejasmin',
    'bongacams',
    'cam4',
    'stripchat',
    'examplecams',
    ...CrakRevenueAdapter::providerNames(),
];
```

Add it to `make()`:

```php
'examplecams' => new ExampleCamsAdapter($config),
```

### If the same commercial brand can be supplied by multiple affiliate routes

If ExampleCams can be selected either directly or through another integration, add a route group in `ProviderFactory::affiliateRouteGroups()`.

The group enforces mutually exclusive sources and gives visitors the commercial brand name instead of exposing the technical affiliate route.

Example:

```php
'examplecams' => [
    'label' => 'ExampleCams',
    'options' => ['examplecams', 'crakrevenue_examplecams'],
],
```

If the provider is direct-only, do not add an affiliate route group just for symmetry.

---

## 7. Implement feed fetching

Implement `fetch(): array` in the adapter. It must return a `list<Performer>`.

Use the provider's documented pagination and enforce sensible local limits. Validate required credentials before making a remote request.

A simplified implementation using the same runtime style as existing adapters:

```php
/** @return list<Performer> */
public function fetch(): array
{
    $apiKey = trim((string) $this->config->get('examplecams.api_key', ''));
    if ($apiKey === '') {
        throw new RuntimeException(
            'Configure the ExampleCams API key in Admin > Integrations first.'
        );
    }

    $endpoint = trim((string) $this->config->get(
        'examplecams.endpoint',
        'https://api.examplecams.invalid/v1/performers'
    ));
    if (!$this->isApiEndpointAllowed($endpoint)) {
        throw new RuntimeException('The configured ExampleCams endpoint is not allowed.');
    }

    $context = stream_context_create(['http' => [
        'timeout' => max(5, min(60, (int) $this->config->get(
            'examplecams.timeout_seconds',
            20
        ))),
        'user_agent' => 'LiveCamForge/' . (string) $this->config->get('version', 'unknown') . ' (+https://livecamforge.com)',
        'header' => "Accept: application/json\r\nAuthorization: Bearer {$apiKey}\r\n",
        'ignore_errors' => true,
    ]]);

    $body = @file_get_contents($endpoint, false, $context);
    if (!is_string($body)) {
        throw new RuntimeException('Unable to contact the ExampleCams API.');
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded) || !is_array($decoded['performers'] ?? null)) {
        throw new RuntimeException('ExampleCams returned an invalid response.');
    }

    $performers = [];
    foreach ($decoded['performers'] as $row) {
        if (!is_array($row)) {
            continue;
        }
        $performer = $this->normalize($row);
        if ($performer !== null) {
            $performers[$performer->providerId] = $performer;
        }
    }

    return array_values($performers);
}
```

For a real provider, also preserve the existing sync profiler conventions when the feed is large. `StripchatAdapter` and `Cam4Adapter` show how `SyncPerformanceProfiler` records remote request count, bytes, pages and decode/normalize timings.

### Empty feeds are dangerous

`SyncPerformers` protects the existing catalog when an adapter unexpectedly returns no performers unless empty results were explicitly permitted. Do not bypass that safeguard in the adapter.

---

## 8. Normalize every upstream record into Performer

Use the exact constructor from:

```text
app/Models/Performer.php
```

Example:

```php
private function normalize(array $row): ?Performer
{
    $id = trim((string) ($row['id'] ?? ''));
    $username = trim((string) ($row['username'] ?? ''));
    if ($id === '' || $username === '') {
        return null;
    }

    $gender = $this->normalizeGender($row['gender'] ?? null);
    $imageUrl = $this->allowedMediaUrl($row['image_url'] ?? null);
    $previewUrl = $this->allowedMediaUrl($row['preview_url'] ?? null);
    $roomUrl = trim((string) ($row['room_url'] ?? ''));
    if (!$this->isRoomUrlAllowed($roomUrl)) {
        $roomUrl = '#';
    }

    return new Performer(
        provider: $this->name(),
        providerId: $id,
        username: $username,
        displayName: trim((string) ($row['display_name'] ?? $username)),
        gender: $gender,
        age: is_numeric($row['age'] ?? null) ? (int) $row['age'] : null,
        imageUrl: $imageUrl,
        previewUrl: $previewUrl ?: $imageUrl,
        embedUrl: null,
        roomStatus: $this->normalizeStatus($row['status'] ?? null),
        roomUrl: $roomUrl,
        viewers: is_numeric($row['viewers'] ?? null)
            ? max(0, (int) $row['viewers'])
            : null,
        tags: $this->normalizeTags($row['tags'] ?? []),
        online: true,
        providerNew: isset($row['is_new']) ? (bool) $row['is_new'] : null,
        geoBlocks: [],
        countryCode: PerformerCountry::normalize($row['country'] ?? null),
    );
}
```

### Normalization rules

#### Gender

LiveCamForge normalized codes are:

```text
f  female
m  male
t  trans
c  couple/group
```

Return `null` when the provider data cannot be mapped reliably. Do not infer gender from unrelated profile text.

#### Country

Use:

```php
PerformerCountry::normalize($providerCountryValue)
```

Only use structured provider-supplied country fields. Do not infer country from language, ethnicity, tags, free-text location or IP.

#### Tags

Normalize to short strings, remove blanks/duplicates and enforce a sensible maximum. Stripchat is a useful example because its hierarchical tags are preserved and capped before constructing `Performer`.

Do not precompute LiveCamForge catalog sorting values. `SyncPerformers` applies normalized popularity and `withSortScores()` centrally.

#### Geo restrictions

When the provider exposes country/region/language bans, normalize them to the same format used by `StripchatAdapter::normalizeGeoBlocks()` and set `geoRestrictions: true` in capabilities.

---

## 9. Validate every external URL

This is mandatory. The adapter owns allowlists for its provider URLs.

Implement:

```php
public function isRoomUrlAllowed(string $url): bool
public function isEmbedUrlAllowed(string $url): bool
public function isMediaUrlAllowed(string $url): bool
```

A typical room validator should check HTTPS, exact/allowed host suffixes, credentials in the URL where relevant, and `FILTER_VALIDATE_URL`.

Example pattern:

```php
public function isRoomUrlAllowed(string $url): bool
{
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $allowed = $host === 'examplecams.invalid'
        || str_ends_with($host, '.examplecams.invalid');

    return strtolower((string) parse_url($url, PHP_URL_SCHEME)) === 'https'
        && $allowed
        && parse_url($url, PHP_URL_USER) === null
        && parse_url($url, PHP_URL_PASS) === null
        && filter_var($url, FILTER_VALIDATE_URL) !== false;
}
```

Do not accept arbitrary feed-provided URLs. These validators are part of LiveCamForge's SSRF/outbound redirect hardening.

---

## 10. Implement the player

The supported player modes are defined in `ProviderPlayer`:

```text
iframe
script
wrapped_iframe
hls
```

If the provider has no embeddable player, return `null`.

Example iframe player:

```php
public function player(array $performer, array $options = []): ?ProviderPlayer
{
    $url = trim((string) ($performer['embed_url'] ?? ''));
    if (!$this->isEmbedUrlAllowed($url)) {
        return null;
    }

    return new ProviderPlayer(
        ProviderPlayer::MODE_IFRAME,
        $url,
        max(5000, min(30000, (int) $this->config->get(
            'examplecams.player_timeout_ms',
            12000
        )))
    );
}
```

For provider scripts that must be isolated, follow `CrakRevenueAdapter`: return `MODE_SCRIPT` with `sandboxWrapper: true`, then convert it in `resolvePlayer()` to a wrapped iframe only after the URL passes the provider allowlist.

For HLS/video-only flows, follow `Cam4Adapter` or the relevant existing direct provider rather than inventing another frontend player mechanism.

---

## 11. Integrate affiliate click attribution

LiveCamForge already records outbound clicks in `public/index.php` through `ClickRepository`. Do **not** duplicate click storage inside the provider adapter.

When the provider accepts a sub-ID/click-ID, implement:

```text
app/Providers/AffiliateTrackingProviderInterface.php
```

The method receives the already validated destination, LiveCamForge SID and track value:

```php
public function trackedRoomUrl(string $url, string $sid, string $track): string
```

Example following the Stripchat pattern:

```php
public function trackedRoomUrl(string $url, string $sid, string $track): string
{
    if (!$this->isRoomUrlAllowed($url)
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
    $query['subid'] = $sid; // Replace only with the provider's documented parameter.

    $tracked = $parts['scheme'] . '://' . $parts['host']
        . (isset($parts['port']) ? ':' . $parts['port'] : '')
        . (string) ($parts['path'] ?? '')
        . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986)
        . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');

    return $this->isRoomUrlAllowed($tracked) ? $tracked : $url;
}
```

`public/index.php` calls this method only after creating the click record, so the SID can later be matched by a postback or conversion polling service.

Never invent a tracking parameter. Use the provider/network documentation and verify the final redirect on the real affiliate account.

---

## 12. Add optional server-to-server postback support

Skip this section if the provider has no postback capability or if conversion tracking is intentionally unsupported. Catalog, players and affiliate redirects do not require internal conversion tracking.

### Files involved

```text
app/Postbacks/PostbackHandlerInterface.php
app/Postbacks/PostbackHandlerFactory.php
app/Postbacks/StripchatPostbackHandler.php       # direct-provider reference
app/Repositories/ClickRepository.php
app/Repositories/ConversionRepository.php
postback.php
config/app.php
app/Core/LocalConfigManager.php
app/Core/OperationalSettings.php
templates/admin-provider-configuration.php
admin/index.php
```

### 12.1 Create a handler

Create:

```text
app/Postbacks/ExampleCamsPostbackHandler.php
```

Use the real handler contract:

```php
final class ExampleCamsPostbackHandler implements PostbackHandlerInterface
{
    public function __construct(
        private Config $config,
        private ClickRepository $clicks,
        private ConversionRepository $conversions,
    ) {
    }

    public function handle(array $payload): array
    {
        // Parse, authenticate, map and store the provider event.
    }
}
```

Use `StripchatPostbackHandler` as the normal direct-provider reference.

### 12.2 Validate the shared secret before processing data

Follow the existing `require_secret` pattern:

```php
if ((bool) $this->config->get('examplecams.postback.require_secret', true)) {
    $expected = trim((string) $this->config->get(
        'examplecams.postback.secret',
        ''
    ));
    $received = trim((string) ($payload['secret'] ?? ''));

    if ($expected === '' || !hash_equals($expected, $received)) {
        return [
            'status' => 403,
            'body' => ['ok' => false, 'message' => 'Invalid postback secret'],
        ];
    }
}
```

Do not log or echo configured secrets.

### 12.3 Recover the LiveCamForge click SID

Use the parameter that was injected by `trackedRoomUrl()`:

```php
$sid = $this->token($payload['subid'] ?? null, 120);
$click = $sid !== ''
    ? $this->clicks->findBySid('examplecams', $sid)
    : null;
```

The click can be `null`; the conversion can still be stored as unattributed if the provider event is otherwise valid.

### 12.4 Build a stable dedupe key

Never assume postbacks are delivered exactly once.

Prefer the provider's immutable transaction/event ID:

```php
$transactionId = $this->token($payload['transaction_id'] ?? null, 190);
if ($transactionId === '') {
    return [
        'status' => 400,
        'body' => ['ok' => false, 'message' => 'Missing transaction ID'],
    ];
}

$dedupeKey = 'transaction:' . $transactionId;
```

If different goals may legitimately share the same transaction ID, include the stable goal/event identifier as `CrakRevenuePostbackHandler` does.

### 12.5 Store through ConversionRepository

Do not insert directly into the conversions table. Reuse:

```text
app/Repositories/ConversionRepository.php
```

The established call shape is:

```php
$stored = $this->conversions->insert([
    'provider' => 'examplecams',
    'dedupe_key' => $dedupeKey,
    'external_event_id' => $transactionId,
    'affiliate_click_id' => $click['id'] ?? null,
    'event_type' => $eventType,
    'sid' => $sid ?: null,
    'track' => 'postback',
    'transaction_id' => $transactionId,
    'provider_click_id' => null,
    'payout' => $payout,
    'amount' => $amount,
    'currency' => $currency,
    'token_amount' => 0,
    'is_test' => 0,
    'event_timestamp' => $eventTimestamp,
    'details_json' => json_encode(
        ['raw_event_type' => $payload['event'] ?? null],
        JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_INVALID_UTF8_SUBSTITUTE
    ),
]);
```

`ConversionRepository::insert()` returns duplicate information. Preserve that behavior in the response rather than implementing a second dedupe system.

### 12.6 Register the handler

Edit:

```text
app/Postbacks/PostbackHandlerFactory.php
```

Add the provider to `supports()` and `make()`:

```php
public static function supports(string $provider): bool
{
    $provider = strtolower(trim($provider));
    return in_array(
        $provider,
        ['chaturbate', 'livejasmin', 'stripchat', 'examplecams', 'crakrevenue'],
        true
    ) || str_starts_with($provider, 'crakrevenue_');
}
```

and:

```php
'examplecams' => new ExampleCamsPostbackHandler(
    $config,
    $clicks,
    $conversions
),
```

`postback.php` already dispatches through this factory. Do not create a second public postback entry point for a normal provider integration.

### 12.7 Add Admin configuration

Add secret storage in `LocalConfigManager`, operational enablement/currency where needed in `OperationalSettings`, and fields in `templates/admin-provider-configuration.php`.

Expose the generated endpoint in the same format used by existing providers:

```text
https://your-site.example/postback.php?provider=examplecams
```

The provider/network-specific query/body macro names belong in the provider guide, not in generic LiveCamForge code.

### 12.8 Add a test payload if practical

Existing postback handlers expose `testPayload()` helpers used by Admin conversion test tools. Follow the closest existing handler and add smoke coverage for normal and duplicate delivery.

At minimum verify:

- wrong secret → rejected;
- first valid event → inserted;
- same event again → duplicate, not a second conversion;
- known SID → attributed;
- unknown/missing SID → stored as unattributed when allowed;
- malformed required identifiers → 400-class response.

---

## 13. Alternative: add conversion polling instead of a postback

If the provider exposes a reporting API but no reliable server-to-server postback, follow:

```text
app/Services/Cam4ConversionSync.php
bin/sync-conversions.php
```

The important existing pattern is:

1. request a bounded date window;
2. page through conversion records;
3. obtain the LiveCamForge SID from the provider/network custom tracking field;
4. call `ClickRepository::findBySid()`;
5. store through `ConversionRepository::insert()` with a stable external event ID/dedupe key;
6. track received/inserted/duplicates/attributed counts.

Example adapted from the actual CAM4/TUNE flow:

```php
$externalId = $this->token($row['id'] ?? null, 190);
$sid = $this->token($row['subid'] ?? null, 120);
$click = $sid !== ''
    ? $this->clicks->findBySid('examplecams', $sid)
    : null;

$stored = $this->conversions->insert([
    'provider' => 'examplecams',
    'dedupe_key' => 'report:' . $externalId,
    'external_event_id' => $externalId,
    'affiliate_click_id' => $click['id'] ?? null,
    'event_type' => 'conversion',
    'sid' => $sid ?: null,
    'track' => 'reporting_api',
    'transaction_id' => null,
    'provider_click_id' => null,
    'payout' => $payout,
    'amount' => $amount,
    'currency' => $currency,
    'token_amount' => 0,
    'is_test' => 0,
    'event_timestamp' => $timestamp,
    'details_json' => $detailsJson,
]);
```

If you add a second polling provider, extend `bin/sync-conversions.php` deliberately rather than embedding reporting API calls inside normal performer synchronization.

---

## 14. Test the direct provider end to end

### Standard shared-hosting workflow

1. Configure credentials in **Admin → Integrations**.
2. Select the provider in **Admin → Public Catalog**.
3. Run a provider sync from **Admin → Operations**.
4. Confirm performer counts and successful sync history.
5. Open multiple performer cards and detail pages.
6. Confirm image/preview URLs render only from allowed hosts.
7. Confirm the player works or falls back cleanly.
8. Click the outbound CTA and verify the intended performer/affiliate destination.
9. If conversion tracking is implemented, run the Admin test tools and then an external real-network test before production use.

### CLI developer validation

When shell access is available in a development environment:

```bash
php tests/smoke.php
php bin/sync.php examplecams
php bin/sync.php examplecams --profile
```

Run at least two consecutive syncs. The second sync exercises existing-row persistence, structural fingerprints, volatile updates, stale/offline handling and geo change detection.

---

# Part II — Add a CrakRevenue-backed source

## 15. Understand what is reused

A new CrakRevenue source normally does **not** require a new provider class.

Reuse:

```text
app/Providers/CrakRevenue/CrakRevenueAdapter.php
app/Providers/CrakRevenue/CrakRevenueClient.php
app/Postbacks/CrakRevenuePostbackHandler.php
app/Services/CrakRevenueAuthorization.php
```

The source-specific pieces are primarily registration metadata, public routing, Admin authorization labels and optional offer/goal mappings.

Suppose CrakRevenue adds a fictional brand code `examplecams` that should appear publicly as **ExampleCams** and internally as:

```text
crakrevenue_examplecams
```

---

## 16. Register the CrakRevenue source

Edit:

```text
app/Providers/CrakRevenue/CrakRevenueAdapter.php
```

Add the provider to `SOURCES`:

```php
private const SOURCES = [
    // existing sources...
    'crakrevenue_examplecams' => [
        'brand' => 'examplecams',
        'label' => 'ExampleCams via CrakRevenue',
    ],
];
```

`providerNames()` automatically exposes every key from `SOURCES`, so you do not maintain a second list inside `CrakRevenueAdapter`.

The `brand` value must match the identifier expected by the CrakRevenue feed API, not the public marketing label.

---

## 17. Add offer and goal mappings when conversion tracking is supported

The existing CrakRevenue postback resolves a source from the offer ID and maps CrakRevenue goal IDs through `CrakRevenueAdapter`.

If the new source has a known offer and goal mapping, add it to `OFFERS`:

```php
private const OFFERS = [
    // existing offers...
    'crakrevenue_examplecams' => [
        'offer_id' => 12345,
        'goals' => [
            0 => 'spending',
            67890 => 'lead',
        ],
    ],
];
```

Use the actual values shown by the affiliate network. Never copy IDs from another source just to make tests pass.

The shared methods already provide:

```php
CrakRevenueAdapter::providerForOfferId($offerId);
CrakRevenueAdapter::goalName($providerName, $goalId);
CrakRevenueAdapter::offerId($providerName);
```

`CrakRevenuePostbackHandler` then:

- verifies the shared CrakRevenue postback secret;
- resolves the internal `crakrevenue_*` source from `offer_id`;
- maps `goal_id` to an event name;
- looks up `aff_sub`/SID attribution;
- builds a stable transaction+goal dedupe key;
- stores through `ConversionRepository`.

Do not write a separate postback handler for each CrakRevenue brand unless the network contract genuinely differs.

If conversion tracking is not configured for the source, the catalog integration can still be used. Postbacks are optional.

---

## 18. Add the commercial route to ProviderFactory

`ProviderFactory::availableNames()` already appends:

```php
...CrakRevenueAdapter::providerNames()
```

so the new `SOURCES` entry becomes an available technical provider automatically.

You must still decide how it should appear in `affiliateRouteGroups()`.

### CrakRevenue-only commercial brand

```php
'examplecams' => [
    'label' => 'ExampleCams',
    'options' => ['crakrevenue_examplecams'],
],
```

### Direct + CrakRevenue alternative

If a direct `examplecams` adapter also exists:

```php
'examplecams' => [
    'label' => 'ExampleCams',
    'options' => ['examplecams', 'crakrevenue_examplecams'],
],
```

This is important because:

- Admin can expose the selected technical route;
- the public catalog still shows **ExampleCams**;
- `enabledNames()` prevents two mutually exclusive routes for the same commercial brand from being active simultaneously.

---

## 19. Add Admin authorization status for the CrakRevenue brand

Edit:

```text
admin/index.php
```

Add the feed brand to `$crakRevenueBrandLabels`:

```php
$crakRevenueBrandLabels = [
    // existing brands...
    'examplecams' => 'ExampleCams',
];
```

The existing loop then calls:

```php
$crakRevenueAuthorization->statusForBrand($brand);
```

and the CrakRevenue card in `templates/admin-provider-configuration.php` displays its authorization state.

Also extend any explicit CrakRevenue source lists in Admin credential/status matching. Search the codebase for the existing `crakrevenue_mfc`, `crakrevenue_streamate`, etc. list before considering the source complete.

This is one of the few places where the current codebase still contains explicit source lists rather than deriving every UI decision from `SOURCES`.

---

## 20. Verify normalization compatibility

`CrakRevenueAdapter::normalize()` is shared by all CrakRevenue sources. Before adding only metadata to `SOURCES`, test a real response for the new brand and confirm it fits the common fields used by that method.

At minimum verify:

- stable `itemId`;
- performer name/clean name;
- gender fields used by the common mapping;
- structured country data;
- tags/characteristics;
- media URL hosts accepted by `isMediaUrlAllowed()`;
- embed host accepted by `isEmbedUrlAllowed()`;
- room/affiliate tracking host accepted by `isRoomUrlAllowed()`.

If the new source introduces a legitimate new CDN/embed/tracking hostname, extend the appropriate CrakRevenue allowlist narrowly. Do not broadly allow arbitrary hosts.

If its payload is structurally different from the common CrakRevenue feed contract, stop and decide whether the shared adapter should be extended generically or whether the brand actually needs a dedicated adapter.

---

## 21. Verify the affiliate redirect, not only API authorization

A CrakRevenue source is not production-ready merely because the API reports it as authorized.

For at least several real performers:

1. synchronize the source;
2. inspect that the card/detail refers to the expected commercial brand;
3. click the affiliate destination through LiveCamForge;
4. follow the complete redirect chain;
5. verify that the final site and performer match the selected source/model;
6. verify the LiveCamForge SID is preserved through the network-supported parameter when postback attribution is enabled.

This check is mandatory because an authorized feed and a technically valid tracking URL can still route to the wrong offer, brand or generic landing page.

Do not “fix” a network-side redirect mismatch by replacing a feed-provided tracking URL with an undocumented URL pattern. Confirm the correct offer/deep-link behavior with the affiliate network first.

---

## 22. Test CrakRevenue postback mapping

When the new offer is included in `OFFERS`, the shared postback endpoint is:

```text
postback.php?provider=crakrevenue_examplecams
```

Internally, `PostbackHandlerFactory` routes every `crakrevenue_*` provider to `CrakRevenuePostbackHandler`.

The handler also validates the incoming `offer_id`, so verify that the provider-specific test payload resolves to the new source and correct goal name.

Test at least:

- configured secret accepted;
- wrong secret rejected;
- known offer ID maps to `crakrevenue_examplecams`;
- unknown offer rejected;
- expected goal IDs map to documented event names;
- unknown goal is handled according to the current `goal_<id>` fallback policy;
- repeated transaction/goal is deduplicated;
- `aff_sub` from a LiveCamForge click attributes the conversion.

---

# Part III — Files normally touched

## 23. Direct provider checklist by file

| File | Usually required? | Change |
| --- | --- | --- |
| `app/Providers/ExampleCams/ExampleCamsAdapter.php` | Yes | New adapter. |
| `app/Providers/ProviderFactory.php` | Yes | Register adapter; route group if applicable. |
| `config/app.php` | Yes | Safe defaults. |
| `app/Core/LocalConfigManager.php` | Usually | Admin-managed credentials/secrets. |
| `app/Core/OperationalSettings.php` | When needed | Non-secret player/postback operational settings. |
| `templates/admin-provider-configuration.php` | Usually | Admin Integrations fields. |
| `admin/index.php` | Usually | Credential readiness and optional postback/admin status. |
| `languages/en.json`, `languages/it.json` | Usually | UI labels. |
| `app/Postbacks/ExampleCamsPostbackHandler.php` | Optional | Server-to-server conversion tracking. |
| `app/Postbacks/PostbackHandlerFactory.php` | With postback | Register handler. |
| `app/Services/*ConversionSync.php` | Optional alternative | Reporting API polling. |
| `bin/sync-conversions.php` | With new polling provider | CLI polling dispatch. |
| `tests/smoke.php` | Yes | Registration/config/security regression checks. |
| `docs/PROVIDERS.md` or provider-specific docs | Yes | Webmaster configuration and limitations. |

## 24. CrakRevenue source checklist by file

| File | Usually required? | Change |
| --- | --- | --- |
| `app/Providers/CrakRevenue/CrakRevenueAdapter.php` | Yes | Add `SOURCES`; add `OFFERS` when postback mapping exists; update narrow host allowlists if genuinely required. |
| `app/Providers/ProviderFactory.php` | Yes | Add/extend commercial route group. |
| `admin/index.php` | Yes | Add brand authorization label and explicit source lists where present. |
| `tests/smoke.php` | Yes | Source registration, route and offer/goal mapping checks. |
| provider documentation | Yes | Authorization, feed identity, redirect/deep-link and known limitations. |

Shared CrakRevenue API credentials, secret fields and postback UI normally do **not** need to be duplicated for each source.

---

# Part IV — Logic you should reuse, not reimplement

## 25. Existing generic services

Do not duplicate these responsibilities inside a provider adapter:

| Responsibility | Existing code |
| --- | --- |
| Persist performers / structural vs volatile updates | `PerformerRepository` via `SyncPerformers` |
| Apply global performer type policy | `SyncPerformers` / `PerformerTypes` |
| Normalize cross-provider popularity/sort scores | `SyncPerformers` / `Performer::withPopularityScore()` / `withSortScores()` |
| Record outbound clicks and SIDs | `ClickRepository`, called by `public/index.php` |
| Conversion deduplication/storage | `ConversionRepository` |
| Generic postback HTTP dispatch | `postback.php` + `PostbackHandlerFactory` |
| Admin/manual provider synchronization | `SyncProviders` / Admin Operations |
| CLI synchronization | `bin/sync.php` |
| Sync overlap lock | `SyncPerformers::acquireLock()` |
| Catalog filtering/pagination/cache | Generic repositories/public controller; not the provider adapter |
| Geo enforcement | Generic catalog policy; adapter only normalizes provider bans |

If a proposed integration requires modifying one of these generic areas, document why the behavior cannot be represented through the current provider contracts.

---

# Part V — Definition of done

## 26. Direct provider completion checklist

A direct integration is ready for review only when all applicable items are true:

- provider class implements the current `ProviderInterface` exactly;
- provider is registered in `ProviderFactory`;
- credentials are configurable without source-code edits;
- secrets are stored only in local configuration;
- capabilities match actual provider behavior;
- realistic feed data normalizes to valid `Performer` objects;
- gender/country/tags are normalized conservatively;
- all room/embed/media URLs use narrow allowlists;
- sync succeeds with production-sized data;
- a second consecutive sync behaves correctly for existing rows;
- unavailable/empty feed failures preserve valid catalog data;
- player and fallback behavior are tested in browser;
- affiliate redirects lead to the intended performer/brand;
- supported SID/sub-ID attribution is verified;
- postback or polling support, when implemented, deduplicates and attributes correctly;
- Admin Operations and Public Catalog expose the provider correctly;
- smoke tests cover provider registration and critical configuration/security behavior;
- provider configuration and known limitations are documented;
- no webmaster credential, affiliate ID, API key, secret or account-specific value is hard-coded.

## 27. CrakRevenue source completion checklist

A CrakRevenue-backed source is ready only when:

- its real feed brand is registered in `CrakRevenueAdapter::SOURCES`;
- the commercial route is registered in `ProviderFactory`;
- Admin authorization status includes the brand;
- real feed rows are compatible with common CrakRevenue normalization;
- all new legitimate hosts are narrowly allowlisted;
- API authorization has been tested on a real account;
- several performer-specific redirects land on the correct brand/model;
- any network-side deep-link mismatch is resolved with the affiliate network rather than hidden in code;
- offer/goal mapping is added only from verified network data;
- postback duplicate and SID attribution tests pass when conversion tracking is enabled;
- public pages show the commercial brand, not the technical network route;
- smoke/documentation are updated.

---

# Final rule

A new provider should feel like another data source plugged into LiveCamForge, not like a fork of the application hidden inside an adapter. Keep provider-specific behavior at the edge, reuse the common persistence/tracking/security layers, and verify the real affiliate journey end to end before declaring the integration complete.
