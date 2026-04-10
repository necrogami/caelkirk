<?php

declare(strict_types=1);

namespace App\Foundation\Repository;

use App\Foundation\Entity\PasswordResetToken;
use Marko\Database\Repository\Repository;

class PasswordResetTokenRepository extends Repository
{
    protected const string ENTITY_CLASS = PasswordResetToken::class;

    public function findByTokenHash(string $tokenHash): ?PasswordResetToken
    {
        return $this->findOneBy(['token_hash' => $tokenHash]);
    }

    public function deleteByUserId(int $userId): void
    {
        $this->connection->execute(
            'DELETE FROM password_reset_tokens WHERE user_id = ?',
            [$userId],
        );
    }

    public function deleteExpired(int $lifetimeMinutes = 60): int
    {
        $cutoff = (new \DateTimeImmutable())
            ->modify("-{$lifetimeMinutes} minutes")
            ->format('Y-m-d H:i:s');

        return $this->connection->execute(
            'DELETE FROM password_reset_tokens WHERE created_at < ?',
            [$cutoff],
        );
    }
}
