<?php

declare(strict_types=1);

namespace App\Foundation\Middleware;

use App\Foundation\Entity\User;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

class RoleMiddleware implements MiddlewareInterface
{
    private const array ROLE_HIERARCHY = [
        'player' => 0,
        'builder' => 1,
        'admin' => 2,
    ];

    public function __construct(
        private readonly GuardInterface $guard,
        private readonly string $requiredRole = 'player',
    ) {}

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->guard->check()) {
            return Response::redirect('/login');
        }

        $user = $this->guard->user();

        if (!$user instanceof User) {
            return Response::redirect('/login');
        }

        $requiredLevel = self::ROLE_HIERARCHY[$this->requiredRole] ?? 0;
        $userLevel = self::ROLE_HIERARCHY[$user->role] ?? 0;

        if ($userLevel < $requiredLevel) {
            return new Response('Forbidden', 403);
        }

        return $next($request);
    }
}
