<?php

declare(strict_types=1);

return [
    'driver' => env('MAIL_DRIVER', 'smtp'),

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'noreply@shilla.org'),
        'name' => env('MAIL_FROM_NAME', 'Shilla'),
    ],

    'smtp' => [
        'host' => env('MAIL_HOST', 'localhost'),
        'port' => (int) env('MAIL_PORT', 1025),
        'encryption' => env('MAIL_ENCRYPTION'),
        'username' => env('MAIL_USERNAME'),
        'password' => env('MAIL_PASSWORD'),
        'timeout' => 30,
    ],
];
