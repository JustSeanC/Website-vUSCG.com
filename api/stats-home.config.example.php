<?php

return [
    // Published CSV URL from your Google Sheet.
    // Example: https://docs.google.com/spreadsheets/d/<SHEET_ID>/export?format=csv&gid=<GID>
    'google_sheet_csv_url' => '',

    'phpvms' => [
        // 'http' (recommended) or 'mysql'
        'mode' => 'http',

        // HTTP mode: point this to your crew endpoint.
        'http_url' => 'https://crew.vuscg.com/api/stats-home.php',
        'http_headers' => [
            // Optional bearer token/header if your crew endpoint is protected.
            // 'Authorization: Bearer your-token-here',
        ],

        // MySQL mode: use only if this host can directly reach your phpVMS DB.
        'mysql' => [
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => '',
            'username' => '',
            'password' => '',
            'query' => "SELECT COUNT(*) AS pireps, COALESCE(SUM(flight_time),0) AS hours, COALESCE(SUM(distance),0) AS miles FROM pireps",
        ],
    ],

    'cache' => [
        'enabled' => true,
        'ttl_seconds' => 300,
        'file' => __DIR__ . '/../cache/stats-home.json',
    ],
];
