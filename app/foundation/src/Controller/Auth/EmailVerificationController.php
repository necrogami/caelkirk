<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Auth;

use App\Foundation\Middleware\StrictRateLimitMiddleware;
use App\Foundation\Repository\UserRepository;
use App\Foundation\Service\EmailVerificationService;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Security\Middleware\SecurityHeadersMiddleware;
use Marko\View\ViewInterface;

#[Middleware([SecurityHeadersMiddleware::class, CsrfMiddleware::class, StrictRateLimitMiddleware::class])]
class EmailVerificationController
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly EmailVerificationService $verificationService,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly ViewInterface $view,
        private readonly GuardInterface $guard,
    ) {}

    #[Get('/verify-email')]
    public function verify(Request $request): Response
    {
        $id = (int) $request->query('id', '0');
        $expires = (int) $request->query('expires', '0');
        $signature = $request->query('signature', '');

        $user = $this->userRepository->find($id);

        if ($user === null) {
            return $this->renderError('Invalid verification link.');
        }

        if ($this->verificationService->isVerified($user)) {
            return Response::redirect('/game');
        }

        if (!$this->verificationService->verify($user, $expires, $signature)) {
            return $this->renderError('This link has expired. Request a new one.');
        }

        return Response::redirect('/game?verified=1');
    }

    #[Post('/verify-email/resend')]
    public function resend(Request $request): Response
    {
        if (!$this->guard->check()) {
            return Response::redirect('/login');
        }

        $user = $this->guard->user();

        if ($this->verificationService->isVerified($user)) {
            return Response::redirect('/game');
        }

        try {
            $this->verificationService->sendVerificationEmail($user);
        } catch (\Throwable) {
            // Log but don't block — email delivery failures shouldn't break UX
        }

        return Response::redirect('/game');
    }

    private function renderError(string $message): Response
    {
        return $this->view->render('foundation::auth/verify-email', [
            'error' => $message,
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }
}
