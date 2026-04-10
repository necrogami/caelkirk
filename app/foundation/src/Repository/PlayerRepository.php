<?php

declare(strict_types=1);

namespace App\Foundation\Repository;

use App\Foundation\Entity\Player;
use Marko\Database\Repository\Repository;

class PlayerRepository extends Repository
{
    protected const string ENTITY_CLASS = Player::class;

    public function findByUserId(int $userId): array
    {
        return $this->query()
            ->where('user_id', '=', $userId)
            ->orderBy('slot_number', 'asc')
            ->getEntities();
    }

    public function countByUserId(int $userId): int
    {
        return $this->query()
            ->where('user_id', '=', $userId)
            ->count();
    }

    public function findByName(string $name): ?Player
    {
        return $this->findOneBy(['name' => $name]);
    }

    public function nextSlotNumber(int $userId): int
    {
        $players = $this->findByUserId($userId);
        $usedSlots = array_map(fn (Player $p) => $p->slotNumber, $players);

        for ($i = 1; $i <= 100; $i++) {
            if (!in_array($i, $usedSlots, true)) {
                return $i;
            }
        }

        return count($usedSlots) + 1;
    }
}
