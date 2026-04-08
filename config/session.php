<?php

declare(strict_types=1);

return [
    'driver' => env('SESSION_DRIVER', 'database'),
    'lifetime' => 1440, // 24 hours in minutes
    'expire_on_close' => false,
    'path' => '',
    'cookie' => [
        'name' => 'shilla_session',
        'path' => '/',
        'domain' => '',
        'secure' => false,
        'httponly' => true,
        'samesite' => 'lax',
    ],
    'gc_probability' => 2,
    'gc_divisor' => 100,
];
