<?php

declare(strict_types=1);

return [
    'default' => 'argon2id',

    'hashers' => [
        'bcrypt' => [
            'cost' => 12,
        ],
        'argon2id' => [
            'memory' => 65536,
            'time' => 4,
            'threads' => 1,
        ],
    ],
];
