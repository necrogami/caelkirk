<?php

declare(strict_types=1);

use App\Foundation\Service\CommandRegistry;

beforeEach(function () {
    $this->registry = new CommandRegistry(registerDefaults: false);
});

it('registers and retrieves commands', function () {
    $this->registry->register(
        id: 'inventory',
        label: 'Open Inventory',
        action: '/game/inventory',
    );

    $commands = $this->registry->getAvailable(
        roomContexts: [],
        playerState: [],
        role: 'player',
    );

    expect($commands)->toHaveCount(1);
    expect($commands[0]['id'])->toBe('inventory');
});

it('filters by room context', function () {
    $this->registry->register(
        id: 'buy',
        label: 'Buy Items',
        action: '/game/shop/buy',
        context: 'shop',
    );

    $withShop = $this->registry->getAvailable(
        roomContexts: ['shop'],
        playerState: [],
        role: 'player',
    );

    $withoutShop = $this->registry->getAvailable(
        roomContexts: [],
        playerState: [],
        role: 'player',
    );

    expect($withShop)->toHaveCount(1);
    expect($withoutShop)->toHaveCount(0);
});

it('filters by player state', function () {
    $this->registry->register(
        id: 'flee',
        label: 'Flee Combat',
        action: '/game/combat/flee',
        requiredState: 'in_combat',
    );

    $inCombat = $this->registry->getAvailable(
        roomContexts: [],
        playerState: ['in_combat'],
        role: 'player',
    );

    $notInCombat = $this->registry->getAvailable(
        roomContexts: [],
        playerState: [],
        role: 'player',
    );

    expect($inCombat)->toHaveCount(1);
    expect($notInCombat)->toHaveCount(0);
});

it('filters by role', function () {
    $this->registry->register(
        id: 'goto',
        label: 'Go To Room',
        action: '/admin/goto',
        requiredRole: 'admin',
    );

    $admin = $this->registry->getAvailable(
        roomContexts: [],
        playerState: [],
        role: 'admin',
    );

    $player = $this->registry->getAvailable(
        roomContexts: [],
        playerState: [],
        role: 'player',
    );

    expect($admin)->toHaveCount(1);
    expect($player)->toHaveCount(0);
});

it('fuzzy searches by label', function () {
    $this->registry->register(id: 'inventory', label: 'Open Inventory', action: '/inventory');
    $this->registry->register(id: 'pocket', label: 'Enter Pocket Portal', action: '/pocket');
    $this->registry->register(id: 'settings', label: 'Settings', action: '/settings');

    $results = $this->registry->search(
        query: 'inv',
        roomContexts: [],
        playerState: [],
        role: 'player',
    );

    expect($results)->toHaveCount(1);
    expect($results[0]['id'])->toBe('inventory');
});

it('includes default commands when constructed normally', function () {
    $registry = new CommandRegistry();

    $commands = $registry->getAvailable(
        roomContexts: [],
        playerState: [],
        role: 'player',
    );

    expect($commands)->not->toBeEmpty();

    $ids = array_column($commands, 'id');
    expect($ids)->toContain('inventory');
    expect($ids)->toContain('pocket');
    expect($ids)->toContain('logout');
});
