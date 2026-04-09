<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Auth;

use App\Foundation\Entity\User;
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
use Marko\Validation\Contracts\ValidatorInterface;
use Marko\View\ViewInterface;

#[Middleware([SecurityHeadersMiddleware::class, CsrfMiddleware::class, RateLimitMiddleware::class])]
class RegisterController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly UserRepository $userRepository,
        private readonly GuardInterface $guard,
        private readonly HasherInterface $hasher,
        private readonly ValidatorInterface $validator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {}

    #[Get('/register')]
    public function show(): Response
    {
        return $this->view->render('foundation::auth/register', [
            'csrfToken' => $this->csrfTokenManager->get(),
        ]);
    }

    #[Post('/register')]
    public function store(Request $request): Response
    {
        $data = $request->post();
        $old = array_diff_key($data, ['password' => true, '_token' => true]);

        $errors = $this->validator->validate($data, [
            'username' => ['required', 'min:3', 'max:50', 'regex:/^[a-zA-Z0-9_]+$/'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'min:8', 'max:255'],
        ]);

        if ($errors->isNotEmpty()) {
            return $this->view->render('foundation::auth/register', [
                'errors' => $errors,
                'old' => $old,
                'csrfToken' => $this->csrfTokenManager->get(),
            ]);
        }

        if ($this->userRepository->findByEmail($data['email']) !== null) {
            $errors->add('email', 'Email already taken');
            return $this->view->render('foundation::auth/register', [
                'errors' => $errors,
                'old' => $old,
                'csrfToken' => $this->csrfTokenManager->get(),
            ]);
        }

        if ($this->userRepository->findByUsername($data['username']) !== null) {
            $errors->add('username', 'Username already taken');
            return $this->view->render('foundation::auth/register', [
                'errors' => $errors,
                'old' => $old,
                'csrfToken' => $this->csrfTokenManager->get(),
            ]);
        }

        $user = new User();
        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->password = $this->hasher->hash($data['password']);
        $user->createdAt = new \DateTimeImmutable();
        $this->userRepository->save($user);

        $this->guard->login($user);

        return Response::redirect('/game');
    }
}
