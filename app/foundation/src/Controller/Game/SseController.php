<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Game;

use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\PubSub\SubscriberInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Security\Middleware\SecurityHeadersMiddleware;
use Marko\Sse\SseStream;
use Marko\Sse\StreamingResponse;

#[Middleware(SecurityHeadersMiddleware::class)]
class SseController
{
    public function __construct(
        private readonly GuardInterface $guard,
        private readonly SubscriberInterface $subscriber,
    ) {}

    #[Get('/game/stream')]
    #[Middleware(AuthMiddleware::class)]
    public function stream(): StreamingResponse
    {
        $userId = $this->guard->id();

        $subscription = $this->subscriber->subscribe(
            "player.{$userId}",
            'global',
        );

        $stream = new SseStream(
            subscription: $subscription,
            timeout: 300,
        );

        return new StreamingResponse($stream);
    }
}
