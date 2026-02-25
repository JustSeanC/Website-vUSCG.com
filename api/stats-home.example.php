<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/**
 * Copy this file to crew.vuscg.com/public/api/stats-home.php
 * Then update DB_* values.
 */

const STATS_API_TOKEN = ''; // Optional: long random token. Leave empty to disable.
const STATS_DEBUG = false;  // true = include DB error details in JSON.

const DB_HOST = '127.0.0.1';
const DB_PORT = 3306;
const DB_NAME = 'vuscgco1_finalphp';
const DB_USER = 'YOUR_DB_USER';
const DB_PASS = 'YOUR_DB_PASSWORD';

if (STATS_API_TOKEN !== '') {
    $token = (string)($_GET['token'] ?? '');
    if (!hash_equals(STATS_API_TOKEN, $token)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'forbidden']);
        exit;
    }
}

function format_minutes(int $minutes): string
{
    $days = intdiv($minutes, 1440);
    $remaining = $minutes % 1440;
    $hours = intdiv($remaining, 60);
    $mins = $remaining % 60;

    $parts = [];
    if ($days > 0) {
        $parts[] = $days . 'd';
    }
    if ($hours > 0 || $days > 0) {
        $parts[] = $hours . 'h';
    }
    $parts[] = $mins . 'm';

    return implode(' ', $parts);
}

try {
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Base query from provided schema: table=pireps, columns=flight_time (minutes), distance.
    $sql = "
        SELECT
            COUNT(*) AS pireps,
            COALESCE(SUM(flight_time), 0) AS total_minutes,
            COALESCE(SUM(distance), 0) AS miles
        FROM pireps
    ";

    // Optional accepted filter: use state=2 when that column exists in this DB.
    $hasAccepted = (bool)$pdo->query("SHOW COLUMNS FROM pireps LIKE 'state'")->fetch();
    if ($hasAccepted) {
        $sql .= ' WHERE state = 2';
    }

    $row = $pdo->query($sql)->fetch() ?: [];

    $pireps = (int)($row['pireps'] ?? 0);
    $totalMinutes = (int)round((float)($row['total_minutes'] ?? 0));
    $miles = (float)($row['miles'] ?? 0);

    echo json_encode([
        'ok' => true,
        'updated_at' => gmdate('c'),
        'pireps' => $pireps,
        'hours' => round($totalMinutes / 60, 1),
        'miles' => round($miles, 1),
        'minutes' => $totalMinutes,
        'hours_display' => format_minutes($totalMinutes),
        'meta' => [
            'accepted_filter_applied' => $hasAccepted,
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    $errorPayload = [
        'ok' => false,
        'error' => 'stats query failed',
    ];

    if (STATS_DEBUG || isset($_GET['debug'])) {
        $errorPayload['detail'] = $e->getMessage();
    }

    echo json_encode($errorPayload);
}
