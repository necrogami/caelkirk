<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Game;

use App\Foundation\Entity\User;
use App\Foundation\Service\CommandRegistry;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;

class CommandController
{
    public function __construct(
        private readonly GuardInterface $guard,
        private readonly CommandRegistry $commandRegistry,
    ) {}

    #[Get('/game/commands')]
    #[Middleware(AuthMiddleware::class)]
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $this->guard->user();
        $query = $request->query('q', '');

        // Room contexts and player state will come from future sub-projects
        // Foundation provides the endpoint structure
        $roomContexts = [];
        $playerState = [];

        $commands = $query !== ''
            ? $this->commandRegistry->search($query, $roomContexts, $playerState, $user->role)
            : $this->commandRegistry->getAvailable($roomContexts, $playerState, $user->role);

        return Response::json($commands);
    }
}
