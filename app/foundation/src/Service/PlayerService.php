<?php

declare(strict_types=1);

namespace App\Foundation\Service;

use App\Foundation\Entity\Player;
use App\Foundation\Repository\PlayerRepository;

class PlayerService
{
    public function __construct(
        private readonly PlayerRepository $playerRepository,
        private readonly ConfigService $configService,
    ) {}

    public function createPlayer(int $userId, string $name, ?int $userSlotOverride): Player
    {
        $limit = $this->configService->getCharacterSlotLimit($userSlotOverride);
        $count = $this->playerRepository->countByUserId($userId);

        if ($count >= $limit) {
            throw new \RuntimeException('Character slot limit reached');
        }

        $existing = $this->playerRepository->findByName($name);
        if ($existing !== null) {
            throw new \RuntimeException('Character name already taken');
        }

        $player = new Player();
        $player->userId = $userId;
        $player->name = $name;
        $player->slotNumber = $this->playerRepository->nextSlotNumber($userId);

        $this->playerRepository->save($player);

        return $player;
    }

    public function getPlayersForUser(int $userId): array
    {
        return $this->playerRepository->findByUserId($userId);
    }
}
