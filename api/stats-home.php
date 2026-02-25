<?php
header('Content-Type: application/json; charset=utf-8');

$defaultStats = [
    'total_pilots' => null,
    'active_pilots' => null,
    'mission_rated_pilots' => null,
    'currently_in_training' => null,
    'currently_flying' => null,
    'special_units' => null,
    'ip_rated' => null,
    'hitron_rated' => null,
    'black_jack_rated' => null,
    'mq9_rated' => null,
    'pireps' => null,
    'hours' => null,
    'miles' => null,
];

$config = [
    'google_sheet_csv_url' => 'https://docs.google.com/spreadsheets/d/e/2PACX-1vQ9CMiFN7xl8kew3lQ-qzd1_DkpNM5P7CGxaJCtIIE6DPftzNHhWgDDDThyEbmjUJSbgl4jbY0aQF4M/pub?gid=278263233&single=true&output=csv',
    'phpvms' => [
        'mode' => 'http',
        'http_url' => 'https://crew.vuscg.com/api/stats-home.php',
        'http_headers' => [],
        'mysql' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => 'vuscgco1_finalphp',
            'username' => 'vuscgco1',
            'password' => 't,s#t@QQ9k+T',
            'query' => "SELECT COUNT(*) AS pireps, COALESCE(SUM(flight_time),0) AS hours, COALESCE(SUM(distance),0) AS miles FROM pireps",
        ],
    ],
    'cache' => [
        'enabled' => true,
        'ttl_seconds' => 300,
        'file' => __DIR__ . '/../cache/stats-home.json',
    ],
];

$configPath = __DIR__ . '/stats-home.config.php';
if (is_file($configPath)) {
    $userConfig = include $configPath;
    if (is_array($userConfig)) {
        $config = array_replace_recursive($config, $userConfig);
    }
}

function normalize_key($value)
{
    $value = strtoupper((string)$value);
    return preg_replace('/[^A-Z0-9]/', '', $value);
}

function numeric_or_null($value)
{
    if ($value === null) {
        return null;
    }

    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }

    $raw = str_replace([',', ' '], '', $raw);
    return is_numeric($raw) ? $raw + 0 : null;
}

function http_get_text($url, $headers = [])
{
    if (!$url) {
        return null;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $body = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($body !== false && $status >= 200 && $status < 300) {
            return $body;
        }
        return null;
    }

    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 12,
            'header' => implode("\r\n", $headers),
        ],
    ]);

    $body = @file_get_contents($url, false, $ctx);
    return $body !== false ? $body : null;
}

function load_google_sheet_stats($url)
{
    $body = http_get_text($url);
    if ($body === null) {
        return [];
    }

    $rows = preg_split('/\r\n|\n|\r/', trim($body));
    $labelMap = [
        'TOTALPILOTS' => 'total_pilots',
        'ACTIVEPILOTS' => 'active_pilots',
        'MISSIONRATEDPILOTS' => 'mission_rated_pilots',
        'CURRENTLYINTRAINING' => 'currently_in_training',
        'CURRENTLYFLYING' => 'currently_flying',
        'SPECIALUNITS' => 'special_units',
        'IP' => 'ip_rated',
        'HITRON' => 'hitron_rated',
        'BLACKJACK' => 'black_jack_rated',
        'MQ9' => 'mq9_rated',
        'UAS' => 'mq9_rated',
        'MQ9UAS' => 'mq9_rated',
    ];

    $out = [];

    foreach ($rows as $line) {
        if ($line === '') {
            continue;
        }

        $cols = str_getcsv($line);
        if (!$cols) {
            continue;
        }

        $labels = [];
        $numbers = [];
        foreach ($cols as $c) {
            $n = numeric_or_null($c);
            if ($n === null) {
                $labels[] = normalize_key($c);
            } else {
                $numbers[] = $n;
            }
        }

        if (!$numbers) {
            continue;
        }

        foreach ($labels as $label) {
            if (isset($labelMap[$label])) {
                $out[$labelMap[$label]] = $numbers[0];
                break;
            }
        }
    }

    return $out;
}

function load_phpvms_stats($cfg)
{
    $mode = $cfg['mode'] ?? 'http';

    if ($mode === 'http') {
        $body = http_get_text($cfg['http_url'] ?? '', $cfg['http_headers'] ?? []);
        if ($body === null) {
            return [];
        }

        $json = json_decode($body, true);
        if (!is_array($json)) {
            return [];
        }

        $data = $json['data'] ?? $json;
        return [
            'pireps' => numeric_or_null($data['pireps'] ?? null),
            'hours' => numeric_or_null($data['hours'] ?? null),
            'miles' => numeric_or_null($data['miles'] ?? null),
            'currently_flying' => numeric_or_null($data['currently_flying'] ?? null),
        ];
    }

    if ($mode === 'mysql') {
        if (!class_exists('PDO')) {
            return [];
        }

        $db = $cfg['mysql'] ?? [];
        if (empty($db['database']) || empty($db['username'])) {
            return [];
        }

        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'] ?? '127.0.0.1', (int)($db['port'] ?? 3306), $db['database']);

        try {
            $pdo = new PDO($dsn, $db['username'], $db['password'] ?? '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $stmt = $pdo->query($db['query']);
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
            if (!$row) {
                return [];
            }

            return [
                'pireps' => numeric_or_null($row['pireps'] ?? null),
                'hours' => numeric_or_null($row['hours'] ?? null),
                'miles' => numeric_or_null($row['miles'] ?? null),
            ];
        } catch (Throwable $e) {
            return [];
        }
    }

    return [];
}

function read_cache($cacheCfg)
{
    if (empty($cacheCfg['enabled'])) {
        return null;
    }

    $file = $cacheCfg['file'] ?? '';
    $ttl = (int)($cacheCfg['ttl_seconds'] ?? 0);
    if (!$file || !$ttl || !is_file($file)) {
        return null;
    }

    if (time() - filemtime($file) > $ttl) {
        return null;
    }

    $json = json_decode((string)file_get_contents($file), true);
    return is_array($json) ? $json : null;
}

function write_cache($cacheCfg, $payload)
{
    if (empty($cacheCfg['enabled'])) {
        return;
    }

    $file = $cacheCfg['file'] ?? '';
    if (!$file) {
        return;
    }

    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }

    @file_put_contents($file, json_encode($payload));
}

$cached = read_cache($config['cache']);
if ($cached) {
    echo json_encode($cached);
    exit;
}

$stats = $defaultStats;
$sheetStats = load_google_sheet_stats($config['google_sheet_csv_url']);
$phpvmsStats = load_phpvms_stats($config['phpvms']);

foreach ([$sheetStats, $phpvmsStats] as $source) {
    foreach ($source as $k => $v) {
        if (array_key_exists($k, $stats) && $v !== null) {
            $stats[$k] = $v;
        }
    }
}

$payload = [
    'ok' => true,
    'updated_at' => gmdate('c'),
    'stats' => $stats,
    'meta' => [
        'sources' => [
            'google_sheet' => !empty($sheetStats),
            'phpvms' => !empty($phpvmsStats),
        ],
    ],
];

write_cache($config['cache'], $payload);

echo json_encode($payload);
