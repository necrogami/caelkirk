<?php

declare(strict_types=1);

namespace App\Foundation\Entity;

use DateTimeImmutable;
use Marko\Authentication\AuthenticatableInterface;
use Marko\Authorization\AuthorizableInterface;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('users')]
class User extends Entity implements AuthenticatableInterface, AuthorizableInterface
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 50, unique: true)]
    public string $username;

    #[Column(length: 255, unique: true)]
    public string $email;

    #[Column(name: 'email_verified_at')]
    public ?DateTimeImmutable $emailVerifiedAt = null;

    #[Column(length: 255)]
    public ?string $password = null;

    #[Column(name: 'remember_token', length: 100)]
    public ?string $rememberToken = null;

    #[Column(length: 20, default: 'player')]
    public string $role = 'player';

    #[Column(name: 'character_slot_limit')]
    public ?int $characterSlotLimit = null;

    #[Column(name: 'banned_at')]
    public ?DateTimeImmutable $bannedAt = null;

    #[Column(name: 'created_at', default: 'CURRENT_TIMESTAMP')]
    public DateTimeImmutable $createdAt;

    #[Column(name: 'updated_at')]
    public ?DateTimeImmutable $updatedAt = null;

    public function getAuthIdentifier(): int|string
    {
        return $this->id;
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthPassword(): string
    {
        return $this->password ?? '';
    }

    public function getRememberToken(): ?string
    {
        return $this->rememberToken;
    }

    public function setRememberToken(?string $token): void
    {
        $this->rememberToken = $token;
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function can(string $ability, mixed ...$arguments): bool
    {
        return false;
    }

    public function isBanned(): bool
    {
        return $this->bannedAt !== null;
    }

    public function hasPassword(): bool
    {
        return $this->password !== null;
    }
}
