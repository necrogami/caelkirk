<?php

declare(strict_types=1);

namespace App\Foundation\Middleware;

use App\Foundation\Entity\User;
use App\Foundation\Enum\Role;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Routing\Middleware\MiddlewareInterface;

class RoleMiddleware implements MiddlewareInterface
{
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

        if (!Role::meetsRequirement($user->role, $this->requiredRole)) {
            return new Response('Forbidden', 403);
        }

        return $next($request);
    }
}
