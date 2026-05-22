<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

$cfg = [
    'api_token' => '',
    'debug' => false,
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'database' => 'vuscgco1_finalphp',
        'username' => '',
        'password' => '',
    ],
];

$configPath = __DIR__ . '/stats-year.config.php';
if (is_file($configPath)) {
    $userCfg = include $configPath;
    if (is_array($userCfg)) {
        $cfg = array_replace_recursive($cfg, $userCfg);
    }
}

if (($cfg['api_token'] ?? '') !== '') {
    $token = (string)($_GET['token'] ?? '');
    if (!hash_equals((string)$cfg['api_token'], $token)) {
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

function table_columns(PDO $pdo, string $table): array {
    $st = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    $cols = [];
    foreach ($st->fetchAll() as $r) {
        $cols[] = strtolower((string)($r['Field'] ?? ''));
    }
    return $cols;
}

function has_col(array $cols, string $name): bool {
    return in_array(strtolower($name), $cols, true);
}

try {
    $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)gmdate('Y');
    $month = isset($_GET['month']) ? clamp_int($_GET['month'], 0, 12) : 0;

    $db = $cfg['db'] ?? [];
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', (string)($db['host'] ?? '127.0.0.1'), (int)($db['port'] ?? 3306), (string)($db['database'] ?? ''));
    $pdo = new PDO($dsn, (string)($db['username'] ?? ''), (string)($db['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    $pirepCols = table_columns($pdo, 'pireps');
    $userCols = table_columns($pdo, 'users');

    $airframeCol = has_col($pirepCols, 'airframe') ? 'airframe' : (has_col($pirepCols, 'aircraft_name') ? 'aircraft_name' : (has_col($pirepCols, 'aircraft') ? 'aircraft' : null));
    $landingRateCol = has_col($pirepCols, 'landing_rate') ? 'landing_rate' : (has_col($pirepCols, 'landingrate') ? 'landingrate' : null);
    $pilotIdCol = has_col($userCols, 'pilot_id') ? 'pilot_id' : 'id';
    $nameExpr = has_col($userCols, 'name') ? 'u.name' : ((has_col($userCols, 'first_name') && has_col($userCols, 'last_name')) ? "CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,''))" : 'CAST(u.id AS CHAR)');

    $where = [];
    $params = [];
    if ($year > 0) { $where[] = 'YEAR(p.submitted_at) = :year'; $params[':year'] = $year; }
    if ($month > 0) { $where[] = 'MONTH(p.submitted_at) = :month'; $params[':month'] = $month; }
    $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

    $summarySql = "SELECT COUNT(*) AS flights, COUNT(DISTINCT p.user_id) AS pilots, COALESCE(SUM(p.flight_time), 0) AS total_minutes, COALESCE(SUM(p.distance), 0) AS miles FROM pireps p {$whereSql}";
    $st = $pdo->prepare($summarySql);
    $st->execute($params);
    $summary = $st->fetch() ?: [];

    $active90Sql = "SELECT COUNT(DISTINCT user_id) AS active_pilots_90d FROM pireps WHERE submitted_at >= (UTC_TIMESTAMP() - INTERVAL 90 DAY)";
    $activePilots90 = (int)($pdo->query($active90Sql)->fetch()['active_pilots_90d'] ?? 0);

    $yearLabel = ($year === 0) ? 'Lifetime' : (string)$year;
    $monthLabel = ($month === 0) ? $yearLabel : gmdate('M', gmmktime(0, 0, 0, $month, 1, 2000));

    $monthly = [];
    if ($year > 0) {
        $mSql = "SELECT MONTH(submitted_at) AS m, COUNT(*) AS flights, COALESCE(SUM(flight_time),0)/60.0 AS hours, COALESCE(SUM(distance),0) AS miles FROM pireps WHERE YEAR(submitted_at)=:year GROUP BY MONTH(submitted_at) ORDER BY MONTH(submitted_at)";
        $mSt = $pdo->prepare($mSql);
        $mSt->execute([':year' => $year]);
        $byMonth = [];
        foreach ($mSt->fetchAll() as $r) $byMonth[(int)$r['m']] = $r;
        for ($m = 1; $m <= 12; $m++) {
            $row = $byMonth[$m] ?? null;
            $monthly[] = ['label' => gmdate('M', gmmktime(0,0,0,$m,1,2000)), 'flights' => (int)($row['flights'] ?? 0), 'hours' => round((float)($row['hours'] ?? 0), 1), 'miles' => round((float)($row['miles'] ?? 0), 1)];
        }
    } else {
        $ySql = "SELECT YEAR(submitted_at) AS y, COUNT(*) AS flights, COALESCE(SUM(flight_time),0)/60.0 AS hours, COALESCE(SUM(distance),0) AS miles FROM pireps WHERE submitted_at IS NOT NULL GROUP BY YEAR(submitted_at) ORDER BY YEAR(submitted_at)";
        $yRows = $pdo->query($ySql)->fetchAll() ?: [];
        foreach ($yRows as $r) {
            $yy = (int)($r['y'] ?? 0);
            if ($yy <= 0) continue;
            $monthly[] = ['label' => (string)$yy, 'flights' => (int)($r['flights'] ?? 0), 'hours' => round((float)($r['hours'] ?? 0), 1), 'miles' => round((float)($r['miles'] ?? 0), 1)];
        }
    }

    $topAirframes = [];
    if ($airframeCol !== null) {
        $afSql = "SELECT p.{$airframeCol} AS airframe, COALESCE(SUM(p.flight_time),0)/60.0 AS hours FROM pireps p {$whereSql} GROUP BY p.{$airframeCol} ORDER BY hours DESC LIMIT 12";
        $afSt = $pdo->prepare($afSql); $afSt->execute($params);
        foreach ($afSt->fetchAll() as $r) $topAirframes[] = ['airframe' => (string)($r['airframe'] ?? '—'), 'hours' => round((float)($r['hours'] ?? 0), 1)];
    }

    $topFlights = [];
    $tfSql = "SELECT u.{$pilotIdCol} AS pilot_id, {$nameExpr} AS pilot_name, COUNT(*) AS flights FROM pireps p JOIN users u ON u.id = p.user_id {$whereSql} GROUP BY u.id, u.{$pilotIdCol}, pilot_name ORDER BY flights DESC LIMIT 10";
    $tfSt = $pdo->prepare($tfSql); $tfSt->execute($params); $topFlights = $tfSt->fetchAll() ?: [];

    $topHours = [];
    $thSql = "SELECT u.{$pilotIdCol} AS pilot_id, {$nameExpr} AS pilot_name, COALESCE(SUM(p.flight_time),0)/60.0 AS hours FROM pireps p JOIN users u ON u.id = p.user_id {$whereSql} GROUP BY u.id, u.{$pilotIdCol}, pilot_name ORDER BY hours DESC LIMIT 10";
    $thSt = $pdo->prepare($thSql); $thSt->execute($params); $topHours = $thSt->fetchAll() ?: [];

    $bestLandings = []; $worstLandings = [];
    if ($landingRateCol !== null) {
        $landingWhereSql = $whereSql ? ($whereSql . " AND p.{$landingRateCol} IS NOT NULL") : " WHERE p.{$landingRateCol} IS NOT NULL";
        $blSql = "SELECT u.{$pilotIdCol} AS pilot_id, {$nameExpr} AS pilot_name, p.{$landingRateCol} AS landing_rate FROM pireps p JOIN users u ON u.id = p.user_id {$landingWhereSql} ORDER BY ABS(p.{$landingRateCol}) ASC LIMIT 10";
        $wlSql = "SELECT u.{$pilotIdCol} AS pilot_id, {$nameExpr} AS pilot_name, p.{$landingRateCol} AS landing_rate FROM pireps p JOIN users u ON u.id = p.user_id {$landingWhereSql} ORDER BY ABS(p.{$landingRateCol}) DESC LIMIT 10";
        $blSt = $pdo->prepare($blSql); $blSt->execute($params); $bestLandings = $blSt->fetchAll() ?: [];
        $wlSt = $pdo->prepare($wlSql); $wlSt->execute($params); $worstLandings = $wlSt->fetchAll() ?: [];
    }

    echo json_encode([
        'ok' => true,
        'updated_at' => gmdate('c'),
        'year' => $year,
        'month' => $month,
        'month_label' => $monthLabel,
        'monthly_mode' => ($year === 0 ? 'yearly' : 'calendar'),
        'pilots' => (int)($summary['pilots'] ?? 0),
        'active_pilots_90d' => $activePilots90,
        'flights' => (int)($summary['flights'] ?? 0),
        'hours' => round(((float)($summary['total_minutes'] ?? 0)) / 60, 1),
        'miles' => round((float)($summary['miles'] ?? 0), 1),
        'hours_display' => format_minutes((int)round((float)($summary['total_minutes'] ?? 0))),
        'currently_flying' => 0,
        'top_airframes' => $topAirframes,
        'month_best_landings' => $bestLandings,
        'month_worst_landings' => $worstLandings,
        'month_top_flights' => $topFlights,
        'month_top_hours' => $topHours,
        'monthly' => $monthly,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    $payload = ['ok' => false, 'error' => 'stats-year query failed'];
    if (($cfg['debug'] ?? false) || isset($_GET['debug'])) $payload['detail'] = $e->getMessage();
    echo json_encode($payload);
}
