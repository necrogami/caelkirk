<?php

declare(strict_types=1);

return [
    'key' => env('ENCRYPTION_KEY', ''),
    'cipher' => env('ENCRYPTION_CIPHER', 'aes-256-gcm'),
];
