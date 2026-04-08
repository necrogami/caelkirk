<?php

declare(strict_types=1);

namespace App\Foundation\Service;

class CommandRegistry
{
    private const array ROLE_HIERARCHY = [
        'player' => 0,
        'builder' => 1,
        'admin' => 2,
    ];

    /** @var array<string, array{id: string, label: string, action: string, context: string, requiredState: ?string, requiredRole: ?string}> */
    private array $commands = [];

    public function __construct(bool $registerDefaults = true)
    {
        if ($registerDefaults) {
            $this->registerDefaults();
        }
    }

    public function register(
        string $id,
        string $label,
        string $action,
        string $context = 'always',
        ?string $requiredState = null,
        ?string $requiredRole = null,
    ): void {
        $this->commands[$id] = [
            'id' => $id,
            'label' => $label,
            'action' => $action,
            'context' => $context,
            'requiredState' => $requiredState,
            'requiredRole' => $requiredRole,
        ];
    }

    public function getAvailable(array $roomContexts, array $playerState, string $role): array
    {
        return array_values(array_filter(
            $this->commands,
            fn (array $cmd) => $this->isAvailable($cmd, $roomContexts, $playerState, $role),
        ));
    }

    public function search(string $query, array $roomContexts, array $playerState, string $role): array
    {
        $available = $this->getAvailable($roomContexts, $playerState, $role);
        $query = strtolower($query);

        return array_values(array_filter(
            $available,
            fn (array $cmd) => str_contains(strtolower($cmd['label']), $query),
        ));
    }

    private function isAvailable(array $command, array $roomContexts, array $playerState, string $role): bool
    {
        if ($command['context'] !== 'always' && !in_array($command['context'], $roomContexts, true)) {
            return false;
        }

        if ($command['requiredState'] !== null && !in_array($command['requiredState'], $playerState, true)) {
            return false;
        }

        if ($command['requiredRole'] !== null) {
            $requiredLevel = self::ROLE_HIERARCHY[$command['requiredRole']] ?? 0;
            $userLevel = self::ROLE_HIERARCHY[$role] ?? 0;
            if ($userLevel < $requiredLevel) {
                return false;
            }
        }

        return true;
    }

    private function registerDefaults(): void
    {
        $this->register('inventory', 'Open Inventory', '/game/panel/inventory');
        $this->register('equipment', 'Open Equipment', '/game/panel/equipment');
        $this->register('skills', 'Open Skills', '/game/panel/skills');
        $this->register('quests', 'Open Quests', '/game/panel/quests');
        $this->register('pocket', 'Enter Pocket Portal', '/game/pocket/enter');
        $this->register('settings', 'Settings', '/game/settings');
        $this->register('logout', 'Log Out', '/logout');
    }
}
