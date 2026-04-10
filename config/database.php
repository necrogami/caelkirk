<?php

declare(strict_types=1);

return [
    'driver' => env('DB_DRIVER', 'pgsql'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => (int) env('DB_PORT', 5432),
    'database' => env('DB_DATABASE', 'shilla'),
    'username' => env('DB_USERNAME', 'shilla'),
    'password' => env('DB_PASSWORD', 'shilla'),
];
