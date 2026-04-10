<?php

declare(strict_types=1);

use App\Foundation\Repository\SystemConfigRepository;
use App\Foundation\Service\ConfigService;

beforeEach(function () {
    $this->repo = Mockery::mock(SystemConfigRepository::class);
    $this->service = new ConfigService($this->repo);
});

it('returns default value when key not in database', function () {
    $this->repo->shouldReceive('getValue')
        ->with('character_slot_default')
        ->andReturn(null);

    $result = $this->service->get('character_slot_default', 50);

    expect($result)->toBe(50);
});

it('returns database value when key exists', function () {
    $this->repo->shouldReceive('getValue')
        ->with('character_slot_default')
        ->andReturn(json_encode(75));

    $result = $this->service->get('character_slot_default', 50);

    expect($result)->toBe(75);
});

it('sets a value', function () {
    $this->repo->shouldReceive('setValue')
        ->with('character_slot_default', json_encode(75))
        ->once();

    $this->service->set('character_slot_default', 75);
});

it('returns character slot limit for user with override', function () {
    $this->repo->shouldReceive('getValue')
        ->with('character_slot_default')
        ->andReturn(json_encode(50));

    $result = $this->service->getCharacterSlotLimit(userOverride: 25);

    expect($result)->toBe(25);
});

it('returns global default when user has no override', function () {
    $this->repo->shouldReceive('getValue')
        ->with('character_slot_default')
        ->andReturn(json_encode(50));

    $result = $this->service->getCharacterSlotLimit(userOverride: null);

    expect($result)->toBe(50);
});
