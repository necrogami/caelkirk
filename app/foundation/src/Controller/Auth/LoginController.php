<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Auth;

use App\Foundation\Repository\UserRepository;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Hashing\Contracts\HasherInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\RateLimiting\Middleware\RateLimitMiddleware;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Security\Middleware\SecurityHeadersMiddleware;
use Marko\View\ViewInterface;

#[Middleware([SecurityHeadersMiddleware::class, CsrfMiddleware::class, RateLimitMiddleware::class])]
class LoginController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly UserRepository $userRepository,
        private readonly GuardInterface $guard,
        private readonly HasherInterface $hasher,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Get('/login')]
    public function show(): Response
    {
        return $this->view->render('foundation::auth/login', [
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }

    #[Post('/login')]
    public function login(Request $request): Response
    {
        $identifier = $request->post('identifier', '');
        $password = $request->post('password', '');

        $user = $this->userRepository->findByEmailOrUsername($identifier);

        if ($user === null || !$user->hasPassword() || !$this->hasher->verify($password, $user->getAuthPassword())) {
            return $this->view->render('foundation::auth/login', [
                'error' => 'Invalid credentials',
                'old' => ['identifier' => $identifier],
                'csrfToken' => $this->csrfTokenManager->get(),
            ]);
        }

        if ($user->isBanned()) {
            return $this->view->render('foundation::auth/login', [
                'error' => 'This account has been banned',
                'old' => ['identifier' => $identifier],
                'csrfToken' => $this->csrfTokenManager->get(),
            ]);
        }

        $this->guard->login($user);

        return Response::redirect('/game');
    }

    #[Post('/logout')]
    public function logout(): Response
    {
        $this->guard->logout();

        return Response::redirect('/');
    }
}
