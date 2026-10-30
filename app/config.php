<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Application configuration
|--------------------------------------------------------------------------
| MySQL is the primary engine. Provide DB_HOST / DB_NAME / DB_USER / DB_PASS
| to run against MySQL. When no host is supplied the app falls back to a
| local SQLite file so it can boot anywhere for demo purposes.
*/

return [
    'name'     => getenv('APP_NAME') ?: 'Spin City Laundry',
    'product'  => 'Laundry Control',
    'tagline'  => 'Wash. Fold. Dominate.',
    'currency' => getenv('APP_CURRENCY') ?: 'KSh',
    'timezone' => getenv('APP_TIMEZONE') ?: 'Africa/Nairobi',
    'db' => [
        'driver' => getenv('DB_DRIVER') ?: (getenv('DB_HOST') ? 'mysql' : 'sqlite'),
        'host'   => getenv('DB_HOST') ?: '127.0.0.1',
        'port'   => (int) (getenv('DB_PORT') ?: 3306),
        'name'   => getenv('DB_NAME') ?: 'laundry',
        'user'   => getenv('DB_USER') ?: 'root',
        'pass'   => getenv('DB_PASS') ?: '',
        'sqlite' => getenv('DB_SQLITE_PATH') ?: dirname(__DIR__) . '/storage/laundry.sqlite',
    ],
];
