<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Game;

use App\Foundation\Entity\User;
use App\Foundation\Service\ConfigService;
use App\Foundation\Service\PlayerService;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Authentication\Middleware\AuthMiddleware;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Http\Response;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Security\Middleware\SecurityHeadersMiddleware;
use Marko\View\ViewInterface;

#[Middleware([SecurityHeadersMiddleware::class, CsrfMiddleware::class])]
class DashboardController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly GuardInterface $guard,
        private readonly PlayerService $playerService,
        private readonly ConfigService $configService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Get('/game')]
    #[Middleware(AuthMiddleware::class)]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->guard->user();
        $players = $this->playerService->getPlayersForUser($user->id);
        $slotLimit = $this->configService->getCharacterSlotLimit($user->characterSlotLimit);

        return $this->view->render('foundation::game/dashboard', [
            'user' => $user,
            'players' => $players,
            'slotLimit' => $slotLimit,
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }
}
