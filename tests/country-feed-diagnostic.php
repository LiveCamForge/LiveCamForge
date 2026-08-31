<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

use LiveCamForge\Core\PerformerTypes;
use LiveCamForge\Providers\BongaCams\BongaCamsAdapter;
use LiveCamForge\Providers\CrakRevenue\CrakRevenueClient;
use LiveCamForge\Providers\LiveJasmin\LiveJasminAdapter;

$root = dirname(__DIR__);
$config = require $root . '/app/bootstrap.php';

/** @return list<array<string,mixed>> */
function countryCandidates(array $row, string $path = '', int $depth = 0): array
{
    if ($depth > 4) {
        return [];
    }
    $found = [];
    foreach ($row as $key => $value) {
        $key = (string) $key;
        $current = $path === '' ? $key : $path . '.' . $key;
        $isCountry = preg_match('/(?:country|nation)/i', $key) === 1;
        if ($isCountry) {
            $candidate = ['path' => $current, 'type' => get_debug_type($value)];
            if (is_scalar($value)) {
                $text = trim((string) $value);
                if ($text !== '' && strlen($text) <= 80
                    && filter_var($text, FILTER_VALIDATE_URL) === false
                    && !str_contains($text, '@')) {
                    $candidate['sample'] = $text;
                }
            } elseif (is_array($value)) {
                $candidate['items'] = count($value);
            }
            $found[] = $candidate;
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                $value = is_array($value[0] ?? null) ? $value[0] : [];
            }
            if ($value !== []) {
                array_push($found, ...countryCandidates($value, $current, $depth + 1));
            }
        }
    }

    return $found;
}

/** @return list<string> */
function structuralPaths(array $row, string $path = '', int $depth = 0): array
{
    if ($depth > 3) {
        return [];
    }
    $paths = [];
    foreach ($row as $key => $value) {
        $key = (string) $key;
        $current = $path === '' ? $key : $path . '.' . $key;
        $paths[] = $current;
        if (is_array($value)) {
            if (array_is_list($value)) {
                $value = is_array($value[0] ?? null) ? $value[0] : [];
            }
            if ($value !== []) {
                array_push($paths, ...structuralPaths($value, $current, $depth + 1));
            }
        }
    }

    sort($paths);
    return array_values(array_unique($paths));
}

function safeError(Throwable $exception): string
{
    $message = preg_replace('/https?:\/\/\S+/i', '[url removed]', $exception->getMessage()) ?? 'Request failed.';
    return substr($message, 0, 200);
}

/** @return array<string,mixed> */
function summarizeRows(array $rows): array
{
    $rows = array_values(array_filter($rows, 'is_array'));
    $paths = [];
    $candidates = [];
    foreach (array_slice($rows, 0, 10) as $row) {
        array_push($paths, ...structuralPaths($row));
        foreach (countryCandidates($row) as $candidate) {
            $key = (string) ($candidate['path'] ?? '') . '|' . (string) ($candidate['sample'] ?? '');
            $candidates[$key] = $candidate;
        }
    }
    sort($paths);
    return [
        'sample_count' => count($rows),
        'structure' => array_values(array_unique($paths)),
        'country_candidates' => array_values($candidates),
    ];
}

$report = [
    'diagnostic_version' => '1.0',
    'livecamforge_version' => (string) $config->get('version'),
    'generated_at' => gmdate(DATE_ATOM),
    'privacy' => 'No credentials, usernames, URLs or complete provider responses are stored.',
    'providers' => [],
];

try {
    $adapter = new BongaCamsAdapter($config);
    $campaign = new ReflectionMethod($adapter, 'campaignId');
    $clientIp = new ReflectionMethod($adapter, 'resolveClientIp');
    $request = new ReflectionMethod($adapter, 'request');
    $payload = $request->invoke($adapter, $campaign->invoke($adapter), $clientIp->invoke($adapter), 0, 10);
    $report['providers']['bongacams'] = ['status' => 'ok']
        + summarizeRows(is_array($payload['models'] ?? null) ? $payload['models'] : []);
} catch (Throwable $exception) {
    $report['providers']['bongacams'] = ['status' => 'failed', 'error' => safeError($exception)];
}

try {
    $adapter = new LiveJasminAdapter($config);
    $categories = new ReflectionMethod($adapter, 'categories');
    $request = new ReflectionMethod($adapter, 'request');
    $availableCategories = $categories->invoke($adapter, PerformerTypes::fromConfig($config));
    $category = (string) ($availableCategories[0] ?? 'girl');
    $payload = $request->invoke($adapter, $category);
    $rows = $payload['data']['models'] ?? [];
    $report['providers']['livejasmin'] = ['status' => 'ok', 'category' => $category]
        + summarizeRows(is_array($rows) ? $rows : []);
} catch (Throwable $exception) {
    $report['providers']['livejasmin'] = ['status' => 'failed', 'error' => safeError($exception)];
}

try {
    $client = new CrakRevenueClient($config);
    $rows = [];
    foreach (['f', 'm', 't', 'c'] as $gender) {
        $payload = $client->fetchPage('imlive', $gender, 1, 10, 10);
        $rows = is_array($payload['performers'] ?? null) ? $payload['performers'] : [];
        if ($rows !== []) {
            break;
        }
    }
    $report['providers']['crakrevenue_imlive'] = ['status' => 'ok'] + summarizeRows($rows);
} catch (Throwable $exception) {
    $report['providers']['crakrevenue_imlive'] = ['status' => 'failed', 'error' => safeError($exception)];
}

$path = $root . '/storage/logs/country-feed-diagnostic.json';
$encoded = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
if (file_put_contents($path, $encoded . PHP_EOL, LOCK_EX) === false) {
    throw new RuntimeException('Unable to write the diagnostic report.');
}

echo "Country feed diagnostic completed.\n";
foreach ($report['providers'] as $provider => $result) {
    echo $provider . ': ' . $result['status'];
    if ($result['status'] === 'ok') {
        echo ', samples=' . (int) ($result['sample_count'] ?? 0)
            . ', country_candidates=' . count($result['country_candidates'] ?? []);
    }
    echo PHP_EOL;
}
echo 'Safe report: ' . $path . PHP_EOL;
