<?php

declare(strict_types=1);

/**
 * LiveCamForge - CrakRevenue CamModel API diagnostic.
 *
 * CLI only. The script reads credentials from config/local.php, checks the
 * requested brands and writes a shareable JSON report without raw URLs,
 * tokens, API keys or complete API responses.
 */

const DIAGNOSTIC_VERSION = '1.0.2';
const DEFAULT_ENDPOINT = 'https://performersext-api.pcvdaa.com/performers-ext';
const DEFAULT_USER_AGENT = 'LiveCamForge-CrakRevenue-Diagnostic/1.0 (+https://livecamforge.com)';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

/** @return never */
function fail(string $message, int $exitCode = 1): void
{
    fwrite(STDERR, "Errore: {$message}" . PHP_EOL);
    exit($exitCode);
}

function line(string $message = ''): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

/** @return array<string, mixed> */
function loadLocalConfiguration(array $arguments): array
{
    $explicitPath = null;
    foreach ($arguments as $argument) {
        if (str_starts_with($argument, '--config=')) {
            $explicitPath = substr($argument, strlen('--config='));
            break;
        }
    }

    $candidates = array_values(array_filter([
        $explicitPath,
        __DIR__ . '/config/local.php',
        dirname(__DIR__) . '/config/local.php',
    ], static fn (?string $path): bool => is_string($path) && $path !== ''));

    foreach (array_unique($candidates) as $candidate) {
        if (!is_file($candidate) || !is_readable($candidate)) {
            continue;
        }

        $configuration = require $candidate;
        if (!is_array($configuration)) {
            fail('Il file di configurazione non restituisce un array PHP valido.');
        }

        return [$configuration, $candidate];
    }

    fail(
        'config/local.php non trovato. Metti lo script nella cartella tests di LiveCamForge ' .
        'oppure usa --config=C:\\percorso\\livecamforge\\config\\local.php.'
    );
}

function redact(string $value, array $secrets): string
{
    foreach ($secrets as $secret) {
        if (is_string($secret) && $secret !== '') {
            $value = str_replace($secret, '[redacted]', $value);
        }
    }

    return $value;
}

/** @return list<string> */
function findSectionPaths(array $configuration, string $wantedKey, string $prefix = ''): array
{
    $paths = [];
    foreach ($configuration as $key => $value) {
        $key = (string) $key;
        $path = $prefix === '' ? $key : $prefix . '.' . $key;
        if ($key === $wantedKey) {
            $paths[] = $path;
        }
        if (is_array($value)) {
            $paths = array_merge($paths, findSectionPaths($value, $wantedKey, $path));
        }
    }

    return $paths;
}

function firstConfiguredString(array $configuration, array $keys): string
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $configuration)) {
            continue;
        }
        $value = trim((string) $configuration[$key]);
        if ($value !== '') {
            return $value;
        }
    }

    return '';
}

/** @return array<string, mixed> */
function requestApi(
    string $endpoint,
    string $apiKey,
    string $token,
    string $userAgent,
    array $parameters
): array {
    $parameters = ['token' => $token] + $parameters;
    $url = rtrim($endpoint, '?') . '?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);

    $handle = curl_init($url);
    if ($handle === false) {
        return ['transport_error' => 'Impossibile inizializzare cURL.'];
    }

    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => [
            'Accept: application/json',
            'x-api-key: ' . $apiKey,
        ],
        CURLOPT_USERAGENT => $userAgent,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $body = curl_exec($handle);
    $curlError = curl_error($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $contentType = (string) curl_getinfo($handle, CURLINFO_CONTENT_TYPE);
    curl_close($handle);

    if ($body === false) {
        return [
            'http_status' => $status,
            'transport_error' => redact($curlError, [$apiKey, $token]),
        ];
    }

    $decoded = json_decode($body, true);
    if (!is_array($decoded)) {
        return [
            'http_status' => $status,
            'content_type' => $contentType,
            'invalid_json' => true,
            'body_excerpt' => redact(substr(trim(strip_tags($body)), 0, 400), [$apiKey, $token]),
        ];
    }

    return [
        'http_status' => $status,
        'content_type' => $contentType,
        'data' => $decoded,
    ];
}

function isListArray(array $value): bool
{
    return function_exists('array_is_list')
        ? array_is_list($value)
        : array_keys($value) === range(0, count($value) - 1);
}

/** @return mixed */
function describeShape(mixed $value, int $depth = 0): mixed
{
    if ($depth >= 6) {
        return get_debug_type($value);
    }

    if (!is_array($value)) {
        return get_debug_type($value);
    }

    if ($value === []) {
        return 'array<empty>';
    }

    if (isListArray($value)) {
        return ['list_item' => describeShape($value[0], $depth + 1)];
    }

    $shape = [];
    foreach ($value as $key => $item) {
        $shape[(string) $key] = describeShape($item, $depth + 1);
    }

    return $shape;
}

/** @return array<string, mixed> */
function describeUrl(mixed $value): array
{
    if (!is_string($value) || trim($value) === '') {
        return ['present' => false];
    }

    $parts = parse_url($value);
    if (!is_array($parts)) {
        return ['present' => true, 'valid_url' => false];
    }

    $queryKeys = [];
    if (isset($parts['query'])) {
        parse_str((string) $parts['query'], $query);
        $queryKeys = array_values(array_map('strval', array_keys($query)));
        sort($queryKeys);
    }

    $path = (string) ($parts['path'] ?? '');
    $path = preg_replace('~/[0-9]+(?=/|$)~', '/{number}', $path) ?? $path;

    return [
        'present' => true,
        'scheme' => (string) ($parts['scheme'] ?? ''),
        'host' => (string) ($parts['host'] ?? ''),
        'path_template' => $path,
        'query_parameters' => $queryKeys,
    ];
}

/** @return array<string, mixed> */
function safeSample(array $performer): array
{
    $characteristic = isset($performer['characteristic']) && is_array($performer['characteristic'])
        ? $performer['characteristic']
        : [];

    $selected = [];
    foreach ([
        'itemId', 'systemSource', 'name', 'nameClean', 'live', 'stars',
        'createdDate', 'updatedDate', 'lastConnection',
    ] as $key) {
        if (array_key_exists($key, $performer)) {
            $selected[$key] = $performer[$key];
        }
    }

    $selectedCharacteristic = [];
    foreach ([
        'gender', 'genderCode', 'age', 'country', 'languages', 'ethnicities',
        'bodyTypes', 'bustSize', 'hairColor', 'eyeColor',
    ] as $key) {
        if (array_key_exists($key, $characteristic)) {
            $selectedCharacteristic[$key] = $characteristic[$key];
        }
    }

    $urls = [];
    foreach ([
        'thumbnailUrl', 'roomUrl', 'iframeFeedURL', 'iframeFeedUrl',
        'liveSnapshotURL', 'liveSnapshotUrl', 'streamFeedUrl', 'streamFeedURL',
    ] as $key) {
        if (array_key_exists($key, $performer)) {
            $urls[$key] = describeUrl($performer[$key]);
        }
    }

    return [
        'selected_values' => $selected,
        'selected_characteristic_values' => $selectedCharacteristic,
        'response_shape' => describeShape($performer),
        'urls' => $urls,
    ];
}

/** @return array<string, mixed> */
function summarizedFailure(array $response, array $secrets): array
{
    $result = [
        'http_status' => (int) ($response['http_status'] ?? 0),
    ];

    foreach (['transport_error', 'content_type', 'invalid_json', 'body_excerpt'] as $key) {
        if (array_key_exists($key, $response)) {
            $value = $response[$key];
            $result[$key] = is_string($value) ? redact($value, $secrets) : $value;
        }
    }

    $data = $response['data'] ?? null;
    if (is_array($data)) {
        foreach (['message', 'error'] as $key) {
            if (isset($data[$key]) && is_scalar($data[$key])) {
                $result[$key] = redact((string) $data[$key], $secrets);
            }
        }
    }

    return $result;
}

[$localConfiguration, $configurationPath] = loadLocalConfiguration(array_slice($argv, 1));

if (!extension_loaded('curl')) {
    fail("L'estensione PHP cURL non è attiva.");
}

$crakRevenue = $localConfiguration['crakrevenue'] ?? [];
if (!is_array($crakRevenue)) {
    fail("La sezione 'crakrevenue' di config/local.php non è valida.");
}

$apiKey = firstConfiguredString($crakRevenue, ['api_key', 'key', 'apiKey']);
$token = firstConfiguredString($crakRevenue, ['token', 'api_token', 'apiToken']);
$endpoint = trim((string) ($crakRevenue['endpoint'] ?? DEFAULT_ENDPOINT));
$userAgent = trim((string) ($crakRevenue['user_agent'] ?? DEFAULT_USER_AGENT));

if ($apiKey === '' || $token === '') {
    line('Configurazione caricata da: ' . $configurationPath);
    line("Sezione top-level 'crakrevenue': " . (array_key_exists('crakrevenue', $localConfiguration) ? 'presente' : 'assente'));
    line(
        'Campi trovati nella sezione: ' .
        ($crakRevenue === [] ? '(nessuno)' : implode(', ', array_map('strval', array_keys($crakRevenue))))
    );
    $sectionPaths = findSectionPaths($localConfiguration, 'crakrevenue');
    line('Percorsi con nome crakrevenue: ' . ($sectionPaths === [] ? '(nessuno)' : implode(', ', $sectionPaths)));
    fail(
        "Inserisci 'api_key' (oppure 'key') e 'token' nella sezione top-level " .
        "'crakrevenue' di config/local.php. I valori non sono stati mostrati."
    );
}
if (!filter_var($endpoint, FILTER_VALIDATE_URL) || !str_starts_with(strtolower($endpoint), 'https://')) {
    fail("L'endpoint CrakRevenue deve essere un URL HTTPS valido.");
}
if ($userAgent === '') {
    fail("Lo User-Agent non può essere vuoto.");
}

$rootDirectory = dirname(dirname($configurationPath));
$outputDirectory = $rootDirectory . '/storage/logs';
$outputPath = $outputDirectory . '/crakrevenue-diagnostic.json';

if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0775, true) && !is_dir($outputDirectory)) {
    fail('Impossibile creare la cartella storage/logs.');
}

$secrets = [$apiKey, $token];
$commonParameters = [
    'page' => 1,
    'size' => 1,
    'sorting' => 'score',
    'live' => 'true',
    'lang' => 'en',
];

$targets = [
    'chaturbate' => [
        'label' => 'Chaturbate',
        'candidate_codes' => ['chaturbate'],
    ],
    'livejasmin' => [
        'label' => 'LiveJasmin',
        'candidate_codes' => ['awempire'],
        'note' => 'La CamModel API identifica il feed LiveJasmin con il codice documentato awempire.',
    ],
    'mfc' => [
        'label' => 'MyFreeCams',
        'candidate_codes' => ['mfc'],
    ],
    'jerkmate' => [
        'label' => 'Jerkmate',
        'candidate_codes' => ['jerkmate', 'streamate'],
        'note' => 'Jerkmate non è elencato nella documentazione API 1.6; potrebbe essere esposto tramite streamate.',
    ],
    'stripchat' => [
        'label' => 'Stripchat',
        'candidate_codes' => ['stripchat'],
    ],
    'imlive' => [
        'label' => 'ImLive',
        'candidate_codes' => ['imlive'],
    ],
    'royalcams' => [
        'label' => 'RoyalCams',
        'candidate_codes' => ['royalcams', 'bongacash'],
        'note' => 'RoyalCams potrebbe essere esposto soltanto tramite il codice documentato bongacash.',
    ],
];

line('LiveCamForge - Diagnostica CrakRevenue CamModel API');
line('Credenziali caricate: sì (non saranno mostrate o salvate)');
line('Verifica autenticazione...');

$probe = requestApi($endpoint, $apiKey, $token, $userAgent, $commonParameters + ['gender' => 'f']);
$probeStatus = (int) ($probe['http_status'] ?? 0);
if ($probeStatus !== 200) {
    $failure = summarizedFailure($probe, $secrets);
    fail('Autenticazione o endpoint non validi: ' . json_encode($failure, JSON_UNESCAPED_SLASHES), 2);
}

$report = [
    'diagnostic_version' => DIAGNOSTIC_VERSION,
    'generated_at' => gmdate('c'),
    'php_version' => PHP_VERSION,
    'configuration' => [
        'endpoint_host' => (string) (parse_url($endpoint, PHP_URL_HOST) ?: ''),
        'user_agent_present' => true,
        'api_key_present' => true,
        'token_present' => true,
        'credentials_included_in_report' => false,
    ],
    'authentication_probe' => [
        'status' => 'ok',
        'http_status' => 200,
        'response_keys' => is_array($probe['data'] ?? null) ? array_keys($probe['data']) : [],
    ],
    'targets' => [],
    'notes' => [
        'No raw API response is stored.',
        'URL query values and numeric tracking path segments are omitted.',
        'A successful streamate test does not by itself prove that Jerkmate is enabled separately.',
        'A successful bongacash test does not by itself prove that RoyalCams is enabled separately.',
    ],
];

$genders = ['f', 'm', 't', 'c'];

foreach ($targets as $targetKey => $target) {
    line('Controllo ' . $target['label'] . '...');
    $targetResult = [
        'label' => $target['label'],
        'status' => 'not_tested',
        'attempts' => [],
    ];
    if (isset($target['note'])) {
        $targetResult['note'] = $target['note'];
    }

    foreach ($target['candidate_codes'] as $brandCode) {
        $candidateResult = [
            'brand_code' => $brandCode,
            'status' => 'unknown',
            'genders_tested' => [],
        ];
        $authorized = false;
        $unauthorized = false;

        foreach ($genders as $gender) {
            usleep(300000);
            $response = requestApi(
                $endpoint,
                $apiKey,
                $token,
                $userAgent,
                $commonParameters + ['brands' => $brandCode, 'gender' => $gender]
            );
            $status = (int) ($response['http_status'] ?? 0);
            $data = $response['data'] ?? null;

            if ($status === 401) {
                $candidateResult['status'] = 'not_authorized_or_unknown_brand';
                $candidateResult['failure'] = summarizedFailure($response, $secrets);
                $unauthorized = true;
                break;
            }

            if ($status !== 200 || !is_array($data)) {
                $candidateResult['status'] = 'request_failed';
                $candidateResult['failure'] = summarizedFailure($response, $secrets);
                break;
            }

            $authorized = true;
            $count = isset($data['count']) && is_numeric($data['count']) ? (int) $data['count'] : null;
            $performers = isset($data['performers']) && is_array($data['performers'])
                ? $data['performers']
                : [];
            $candidateResult['genders_tested'][$gender] = [
                'http_status' => 200,
                'count' => $count,
                'sample_received' => isset($performers[0]) && is_array($performers[0]),
            ];

            if (isset($performers[0]) && is_array($performers[0])) {
                $candidateResult['status'] = 'authorized_with_live_sample';
                $candidateResult['sample_gender'] = $gender;
                $candidateResult['reported_count'] = $count;
                $candidateResult['sample'] = safeSample($performers[0]);
                break;
            }
        }

        if ($authorized && $candidateResult['status'] === 'unknown') {
            $candidateResult['status'] = 'authorized_but_no_live_sample';
        }

        $targetResult['attempts'][] = $candidateResult;

        if (str_starts_with($candidateResult['status'], 'authorized_')) {
            if ($brandCode === 'streamate' && $targetKey === 'jerkmate') {
                $targetResult['status'] = 'streamate_authorized_jerkmate_unconfirmed';
            } elseif ($brandCode === 'bongacash' && $targetKey === 'royalcams') {
                $targetResult['status'] = 'bongacash_authorized_royalcams_unconfirmed';
            } else {
                $targetResult['status'] = $candidateResult['status'];
            }
            $targetResult['working_brand_code'] = $brandCode;
            break;
        }

        if (!$unauthorized && $candidateResult['status'] === 'request_failed') {
            $targetResult['status'] = 'request_failed';
            break;
        }
    }

    if ($targetResult['status'] === 'not_tested') {
        $targetResult['status'] = 'not_authorized_or_unknown_brand';
    }

    $report['targets'][$targetKey] = $targetResult;
}

$encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
if (!is_string($encoded)) {
    fail('Impossibile generare il report JSON.');
}
if (file_put_contents($outputPath, $encoded . PHP_EOL, LOCK_EX) === false) {
    fail('Impossibile scrivere il report in storage/logs.');
}

line();
line('Diagnostica completata.');
foreach ($report['targets'] as $target) {
    line('- ' . $target['label'] . ': ' . $target['status']);
}
line();
line('Report condivisibile creato in:');
line($outputPath);
line('Il report non contiene API key, token, URL completi o risposte grezze.');
