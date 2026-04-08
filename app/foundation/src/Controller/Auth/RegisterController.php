<?php

declare(strict_types=1);

namespace App\Foundation\Controller\Auth;

use App\Foundation\Entity\User;
use App\Foundation\Repository\UserRepository;
use Marko\Authentication\Contracts\GuardInterface;
use Marko\Hashing\Contracts\HasherInterface;
use Marko\Routing\Attributes\Get;
use Marko\Routing\Attributes\Post;
use Marko\Routing\Http\Request;
use Marko\Routing\Http\Response;
use Marko\Validation\Contracts\ValidatorInterface;
use Marko\View\ViewInterface;

class RegisterController
{
    public function __construct(
        private readonly ViewInterface $view,
        private readonly UserRepository $userRepository,
        private readonly GuardInterface $guard,
        private readonly HasherInterface $hasher,
        private readonly ValidatorInterface $validator,
    ) {}

    #[Get('/register')]
    public function show(): Response
    {
        return $this->view->render('foundation::auth/register');
    }

    #[Post('/register')]
    public function store(Request $request): Response
    {
        $data = $request->post();

        $errors = $this->validator->validate($data, [
            'username' => ['required', 'min:3', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'min:8', 'max:255'],
        ]);

        if ($errors->isNotEmpty()) {
            return $this->view->render('foundation::auth/register', [
                'errors' => $errors,
                'old' => $data,
            ]);
        }

        if ($this->userRepository->findByEmail($data['email']) !== null) {
            $errors->add('email', 'Email already taken');
            return $this->view->render('foundation::auth/register', [
                'errors' => $errors,
                'old' => $data,
            ]);
        }

        if ($this->userRepository->findByUsername($data['username']) !== null) {
            $errors->add('username', 'Username already taken');
            return $this->view->render('foundation::auth/register', [
                'errors' => $errors,
                'old' => $data,
            ]);
        }

        $user = new User();
        $user->username = $data['username'];
        $user->email = $data['email'];
        $user->password = $this->hasher->hash($data['password']);
        $this->userRepository->save($user);

        $this->guard->login($user);

        return Response::redirect('/game');
    }
}
