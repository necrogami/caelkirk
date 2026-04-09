<?php

declare(strict_types=1);

use App\Foundation\Entity\Player;
use App\Foundation\Repository\PlayerRepository;
use App\Foundation\Service\ConfigService;
use App\Foundation\Service\PlayerService;

beforeEach(function () {
    $this->playerRepo = Mockery::mock(PlayerRepository::class);
    $this->configService = Mockery::mock(ConfigService::class);
    $this->service = new PlayerService($this->playerRepo, $this->configService);
});

it('creates a player in the next available slot', function () {
    $this->playerRepo->shouldReceive('countByUserId')
        ->with(1)
        ->andReturn(0);

    $this->configService->shouldReceive('getCharacterSlotLimit')
        ->with(null)
        ->andReturn(50);

    $this->playerRepo->shouldReceive('findByName')
        ->with('Aldric')
        ->andReturn(null);

    $this->playerRepo->shouldReceive('nextSlotNumber')
        ->with(1)
        ->andReturn(1);

    $this->playerRepo->shouldReceive('save')
        ->once()
        ->withArgs(function (Player $player) {
            return $player->userId === 1
                && $player->name === 'Aldric'
                && $player->slotNumber === 1;
        });

    $player = $this->service->createPlayer(
        userId: 1,
        name: 'Aldric',
        userSlotOverride: null,
    );

    expect($player)->toBeInstanceOf(Player::class);
    expect($player->name)->toBe('Aldric');
});

it('rejects creation when slot limit reached', function () {
    $this->playerRepo->shouldReceive('countByUserId')
        ->with(1)
        ->andReturn(50);

    $this->configService->shouldReceive('getCharacterSlotLimit')
        ->with(null)
        ->andReturn(50);

    $this->service->createPlayer(
        userId: 1,
        name: 'Aldric',
        userSlotOverride: null,
    );
})->throws(\RuntimeException::class, 'Character slot limit reached');

it('rejects duplicate character name', function () {
    $this->playerRepo->shouldReceive('countByUserId')
        ->with(1)
        ->andReturn(0);

    $this->configService->shouldReceive('getCharacterSlotLimit')
        ->with(null)
        ->andReturn(50);

    $existing = new Player();
    $existing->name = 'Aldric';

    $this->playerRepo->shouldReceive('findByName')
        ->with('Aldric')
        ->andReturn($existing);

    $this->service->createPlayer(
        userId: 1,
        name: 'Aldric',
        userSlotOverride: null,
    );
})->throws(\RuntimeException::class, 'Character name already taken');

it('rejects invalid character name with special characters', function () {
    expect(fn () => $this->service->createPlayer(1, '<script>alert(1)</script>', null))
        ->toThrow(\RuntimeException::class, 'Invalid character name');
});

it('rejects character name that is too short', function () {
    expect(fn () => $this->service->createPlayer(1, 'ab', null))
        ->toThrow(\RuntimeException::class, 'Invalid character name');
});

it('lists players for a user', function () {
    $player = new Player();
    $player->name = 'Aldric';
    $player->slotNumber = 1;

    $this->playerRepo->shouldReceive('findByUserId')
        ->with(1)
        ->andReturn([$player]);

    $players = $this->service->getPlayersForUser(1);

    expect($players)->toHaveCount(1);
    expect($players[0]->name)->toBe('Aldric');
});
