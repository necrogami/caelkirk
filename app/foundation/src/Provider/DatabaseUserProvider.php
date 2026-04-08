<?php

declare(strict_types=1);

namespace App\Foundation\Provider;

use App\Foundation\Entity\User;
use App\Foundation\Repository\UserRepository;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authentication\Contracts\PasswordHasherInterface;
use Marko\Authentication\Contracts\UserProviderInterface;

readonly class DatabaseUserProvider implements UserProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
        private PasswordHasherInterface $hasher,
    ) {}

    public function retrieveById(int|string $identifier): ?AuthenticatableInterface
    {
        return $this->userRepository->find((int) $identifier);
    }

    public function retrieveByCredentials(array $credentials): ?AuthenticatableInterface
    {
        $email = $credentials['email'] ?? null;

        if ($email === null) {
            return null;
        }

        return $this->userRepository->findByEmail($email);
    }

    public function validateCredentials(AuthenticatableInterface $user, array $credentials): bool
    {
        $password = $credentials['password'] ?? '';

        return $this->hasher->verify($password, $user->getAuthPassword());
    }

    public function retrieveByRememberToken(int|string $identifier, string $token): ?AuthenticatableInterface
    {
        $user = $this->userRepository->find((int) $identifier);

        if ($user === null) {
            return null;
        }

        /** @var User $user */
        if ($user->getRememberToken() !== $token) {
            return null;
        }

        return $user;
    }

    public function updateRememberToken(AuthenticatableInterface $user, ?string $token): void
    {
        if (!$user instanceof User) {
            return;
        }

        $user->rememberToken = $token;
        $user->updatedAt = new \DateTimeImmutable();
        $this->userRepository->save($user);
    }
}
