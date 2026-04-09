<?php

declare(strict_types=1);

namespace App\Foundation\Repository;

use App\Foundation\Entity\User;
use Marko\Database\Repository\Repository;

class UserRepository extends Repository
{
    protected const string ENTITY_CLASS = User::class;

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy(['email' => $email]);
    }

    public function findByUsername(string $username): ?User
    {
        return $this->findOneBy(['username' => $username]);
    }

    public function findByEmailOrUsername(string $identifier): ?User
    {
        return $this->findByEmail($identifier) ?? $this->findByUsername($identifier);
    }

    public function countAll(): int
    {
        return $this->count();
    }

    public function countSince(\DateTimeImmutable $since): int
    {
        return $this->query()
            ->where('created_at', '>=', $since->format('Y-m-d H:i:s'))
            ->count();
    }

    public function search(string $term, ?string $role = null, int $limit = 50, int $offset = 0): array
    {
        $query = $this->query();

        if ($term !== '') {
            $escaped = str_replace(['%', '_'], ['\\%', '\\_'], $term);
            $query->where('username', 'LIKE', "%{$escaped}%");
        }

        if ($role !== null) {
            $query->where('role', '=', $role);
        }

        return $query
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->offset($offset)
            ->getEntities();
    }
}
