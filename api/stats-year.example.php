<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

/**
 * Example endpoint for yearlystats.html
 *
 * Copy to crew.vuscg.com/public/api/stats-year.php and set DB credentials.
 */

const STATS_API_TOKEN = ''; // Optional bearer via query string: ?token=...
const STATS_DEBUG = false;

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

function clamp_int($value, int $min, int $max): int {
    $n = (int)$value;
    if ($n < $min) return $min;
    if ($n > $max) return $max;
    return $n;
}

function format_minutes(int $minutes): string {
    $days = intdiv($minutes, 1440);
    $remaining = $minutes % 1440;
    $hours = intdiv($remaining, 60);
    $mins = $remaining % 60;

    $parts = [];
    if ($days > 0) $parts[] = $days . 'd';
    if ($hours > 0 || $days > 0) $parts[] = $hours . 'h';
    $parts[] = $mins . 'm';
    return implode(' ', $parts);
}

try {
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)gmdate('Y'); // 0=lifetime
    $month = isset($_GET['month']) ? clamp_int($_GET['month'], 0, 12) : 0;

    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $hasAccepted = (bool)$pdo->query("SHOW COLUMNS FROM pireps LIKE 'accepted'")->fetch();
    $where = [];
    $params = [];

    if ($hasAccepted) {
        $where[] = 'accepted = 1';
    }

    if ($year > 0) {
        $where[] = 'YEAR(submitted_at) = :year';
        $params[':year'] = $year;
    }
    if ($month > 0) {
        $where[] = 'MONTH(submitted_at) = :month';
        $params[':month'] = $month;
    }

    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $summarySql = "
        SELECT
            COUNT(*) AS flights,
            COUNT(DISTINCT user_id) AS pilots,
            COALESCE(SUM(flight_time), 0) AS total_minutes,
            COALESCE(SUM(distance), 0) AS miles
        FROM pireps
        {$whereSql}
    ";
    $st = $pdo->prepare($summarySql);
    $st->execute($params);
    $summary = $st->fetch() ?: [];

    $active90Sql = "
        SELECT COUNT(DISTINCT user_id) AS active_pilots_90d
        FROM pireps
        WHERE submitted_at >= (UTC_TIMESTAMP() - INTERVAL 90 DAY)" . ($hasAccepted ? " AND accepted = 1" : "");
    $activePilots90 = (int)($pdo->query($active90Sql)->fetch()['active_pilots_90d'] ?? 0);

    $yearLabel = ($year === 0) ? 'Lifetime' : (string)$year;
    $monthLabel = ($month === 0) ? $yearLabel : gmdate('M', gmmktime(0, 0, 0, $month, 1, 2000));

    $monthly = [];
    if ($year > 0) {
        $mSql = "
            SELECT
              MONTH(submitted_at) AS m,
              COUNT(*) AS flights,
              COALESCE(SUM(flight_time),0) / 60.0 AS hours,
              COALESCE(SUM(distance),0) AS miles
            FROM pireps
            WHERE YEAR(submitted_at)=:year " . ($hasAccepted ? "AND accepted=1" : "") . "
            GROUP BY MONTH(submitted_at)
            ORDER BY MONTH(submitted_at)
        ";
        $mSt = $pdo->prepare($mSql);
        $mSt->execute([':year' => $year]);
        $byMonth = [];
        foreach ($mSt->fetchAll() as $r) {
            $byMonth[(int)$r['m']] = $r;
        }
        for ($m = 1; $m <= 12; $m++) {
            $row = $byMonth[$m] ?? null;
            $monthly[] = [
                'label' => gmdate('M', gmmktime(0,0,0,$m,1,2000)),
                'flights' => (int)($row['flights'] ?? 0),
                'hours' => round((float)($row['hours'] ?? 0), 1),
                'miles' => round((float)($row['miles'] ?? 0), 1),
            ];
        }
    }

    echo json_encode([
        'ok' => true,
        'updated_at' => gmdate('c'),
        'year' => $year,
        'month' => $month,
        'month_label' => $monthLabel,
        'monthly_mode' => ($year === 0 ? 'rolling' : 'calendar'),
        'pilots' => (int)($summary['pilots'] ?? 0),
        'active_pilots_90d' => $activePilots90,
        'flights' => (int)($summary['flights'] ?? 0),
        'hours' => round(((float)($summary['total_minutes'] ?? 0)) / 60, 1),
        'miles' => round((float)($summary['miles'] ?? 0), 1),
        'hours_display' => format_minutes((int)round((float)($summary['total_minutes'] ?? 0))),
        'currently_flying' => 0,
        'top_airframes' => [],
        'month_best_landings' => [],
        'month_worst_landings' => [],
        'month_top_flights' => [],
        'month_top_hours' => [],
        'monthly' => $monthly,
        'meta' => ['accepted_filter_applied' => $hasAccepted],
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    $payload = ['ok' => false, 'error' => 'stats-year query failed'];
    if (STATS_DEBUG || isset($_GET['debug'])) $payload['detail'] = $e->getMessage();
    echo json_encode($payload);
}
