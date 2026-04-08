<?php

declare(strict_types=1);

namespace App\Foundation\Repository;

use App\Foundation\Entity\SocialAccount;
use Marko\Database\Repository\Repository;

class SocialAccountRepository extends Repository
{
    protected const string ENTITY_CLASS = SocialAccount::class;

    public function findByProvider(string $provider, string $providerId): ?SocialAccount
    {
        return $this->findOneBy([
            'provider' => $provider,
            'provider_id' => $providerId,
        ]);
    }

    public function findByUserId(int $userId): array
    {
        return $this->query()
            ->where('user_id', '=', $userId)
            ->getEntities();
    }

    public function countByUserId(int $userId): int
    {
        return $this->query()
            ->where('user_id', '=', $userId)
            ->count();
    }
}
