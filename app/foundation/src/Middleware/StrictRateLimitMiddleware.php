<?php

declare(strict_types=1);

namespace App\Foundation\Middleware;

use Marko\RateLimiting\Contracts\RateLimiterInterface;
use Marko\RateLimiting\Middleware\RateLimitMiddleware;

class StrictRateLimitMiddleware extends RateLimitMiddleware
{
    public function __construct(
        RateLimiterInterface $limiter,
    ) {
        parent::__construct(
            limiter: $limiter,
            maxAttempts: 5,
            decaySeconds: 600,
        );
    }
}
