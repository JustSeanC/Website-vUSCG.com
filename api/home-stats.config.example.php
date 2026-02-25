<?php
/**
 * Copy this file to api/home-stats.config.php and fill in values.
 * Do NOT commit real credentials.
 */
return [
    // Publish your sheet to CSV and paste URL here.
    // Example: https://docs.google.com/spreadsheets/d/<ID>/export?format=csv&gid=0
    'google_sheet_csv_url' => '',

    // phpVMS source: 'http' (preferred if API available) or 'mysql'.
    'phpvms' => [
        'mode' => 'http',

        // HTTP mode: endpoint should return JSON with pireps/hours/miles
        // e.g. {"pireps":123,"hours":456.7,"miles":89012}
        'http_url' => '',
        'http_headers' => [],

        // MySQL mode: set credentials and an aggregate query that returns:
        // pireps, hours, miles
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
        'file' => __DIR__ . '/../cache/home-stats.json',
    ],
];
