<?php

declare(strict_types=1);

namespace App\Foundation\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('password_reset_tokens')]
class PasswordResetToken extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(name: 'user_id', references: 'users.id', onDelete: 'cascade')]
    public int $userId;

    #[Column(name: 'token_hash', length: 64)]
    public string $tokenHash;

    #[Column(name: 'created_at', default: 'CURRENT_TIMESTAMP')]
    public ?DateTimeImmutable $createdAt = null;

    public function isExpired(int $lifetimeMinutes = 60): bool
    {
        if ($this->createdAt === null) {
            return true;
        }
        $expiry = $this->createdAt->modify("+{$lifetimeMinutes} minutes");
        return new DateTimeImmutable() > $expiry;
    }
}
