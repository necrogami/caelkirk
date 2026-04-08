<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Game;

use App\Foundation\Entity\User;
use App\Foundation\Service\PlayerService;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;
use Marko\View\ViewInterface;

class DashboardController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly GuardInterface $guard,
        private readonly PlayerService $playerService,
    ) {}

    #[Get('/game')]
    #[Middleware(AuthMiddleware::class)]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->guard->user();
        $players = $this->playerService->getPlayersForUser($user->getAuthIdentifier());

        return $this->view->render('foundation::game/dashboard', [
            'user' => $user,
            'players' => $players,
        ]);
    }
}
