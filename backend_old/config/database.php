<?php

// Parse DATABASE_URL se disponível
$databaseUrl = env('DATABASE_URL');
$dbConfig = [
    'driver' => 'pgsql',
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', 5432),
    'database' => env('DB_DATABASE', 'crm_exclusiva'),
    'username' => env('DB_USERNAME', 'postgres'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'schema' => 'public',
    'sslmode' => 'prefer',
    'migrations' => 'migrations',
];

if ($databaseUrl) {
    $parsedUrl = parse_url($databaseUrl);
    $dbConfig['host'] = $parsedUrl['host'] ?? $dbConfig['host'];
    $dbConfig['port'] = $parsedUrl['port'] ?? $dbConfig['port'];
    $dbConfig['database'] = isset($parsedUrl['path']) ? ltrim($parsedUrl['path'], '/') : $dbConfig['database'];
    $dbConfig['username'] = $parsedUrl['user'] ?? $dbConfig['username'];
    $dbConfig['password'] = $parsedUrl['pass'] ?? $dbConfig['password'];
}

return [
    'default' => env('DB_CONNECTION', 'pgsql'),
    'migrations' => 'migrations',
    'connections' => [
        'pgsql' => $dbConfig,
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', 3306),
            'database' => env('DB_DATABASE', 'crm_exclusiva'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => false,
            'engine' => null,
            'migrations' => 'migrations',
        ],
    ],
];
