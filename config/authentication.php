<?php

declare(strict_types=1);

return [
    'default' => [
        'guard' => 'session',
        'provider' => 'users',
    ],
    'guards' => [
        'session' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],
    'password' => [
        'driver' => 'argon2id',
    ],
    'remember' => [
        'expiration' => 43200, // 30 days in minutes
        'cookie' => 'remember_token',
    ],
];
