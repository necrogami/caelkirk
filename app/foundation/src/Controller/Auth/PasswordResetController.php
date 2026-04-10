<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Auth;

use App\Foundation\Middleware\StrictRateLimitMiddleware;
use App\Foundation\Service\PasswordResetService;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Middleware;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Security\Contracts\CsrfTokenManagerInterface;
use Marko\Security\Middleware\CsrfMiddleware;
use Marko\Security\Middleware\SecurityHeadersMiddleware;
use Marko\Validation\Contracts\ValidatorInterface;
use Marko\View\ViewInterface;

#[Middleware([SecurityHeadersMiddleware::class, CsrfMiddleware::class, StrictRateLimitMiddleware::class])]
class PasswordResetController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly PasswordResetService $passwordResetService,
        private readonly ValidatorInterface $validator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Get('/forgot-password')]
    public function show(): Response
    {
        return $this->view->render('foundation::auth/forgot-password', [
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }

    #[Post('/forgot-password')]
    public function sendResetLink(Request $request): Response
    {
        $email = $request->post('email', '');

        $errors = $this->validator->validate($request->post(), [
            'email' => ['required', 'email'],
        ]);

        if ($errors->isNotEmpty()) {
            return $this->view->render('foundation::auth/forgot-password', [
                'errors' => $errors,
                'old' => ['email' => $email],
                'csrfToken' => $this->csrfTokenManager->get(),
            ]);
        }

        $this->passwordResetService->sendResetLink($email);

        return $this->view->render('foundation::auth/forgot-password', [
            'success' => 'If an account with that email exists, we sent a reset link.',
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }

    #[Get('/reset-password/{token}')]
    public function showResetForm(string $token): Response
    {
        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            return Response::redirect('/forgot-password');
        }

        $validToken = $this->passwordResetService->findValidToken($token);

        if ($validToken === null) {
            return $this->view->render('foundation::auth/reset-password', [
                'error' => 'This reset link is invalid or has expired.',
                'csrfToken' => $this->csrfTokenManager->get(),
            ]);
        }

        return $this->view->render('foundation::auth/reset-password', [
            'token' => $token,
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }

    #[Post('/reset-password')]
    public function resetPassword(Request $request): Response
    {
        $token = $request->post('token', '');
        $password = $request->post('password', '');
        $passwordConfirmation = $request->post('password_confirmation', '');

        $errors = $this->validator->validate($request->post(), [
            'token' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'password' => ['required', 'min:8', 'max:255'],
        ]);

        if ($password !== $passwordConfirmation) {
            $errors->add('password_confirmation', 'Passwords do not match');
        }

        if ($errors->isNotEmpty()) {
            return $this->view->render('foundation::auth/reset-password', [
                'errors' => $errors,
                'token' => $token,
                'csrfToken' => $this->csrfTokenManager->get(),
            ]);
        }

        $result = $this->passwordResetService->resetPassword($token, $password);

        if (!$result) {
            return $this->view->render('foundation::auth/reset-password', [
                'error' => 'This reset link is invalid or has expired.',
                'token' => $token,
                'csrfToken' => $this->csrfTokenManager->get(),
            ]);
        }

        return Response::redirect('/login');
    }
}
