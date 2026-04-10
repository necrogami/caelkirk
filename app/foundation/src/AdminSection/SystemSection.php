<?php

declare(strict_types=1);

namespace App\Foundation\AdminSection;

use Marko\Admin\Attributes\AdminPermission;
use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\MenuItem;

#[AdminSection(id: 'system', label: 'System', icon: 'settings', sortOrder: 90)]
#[AdminPermission(id: 'system.config', label: 'Manage Configuration')]
#[AdminPermission(id: 'system.announcements', label: 'Manage Announcements')]
class SystemSection implements AdminSectionInterface
{
    public function getId(): string
    {
        return 'system';
    }

    public function getLabel(): string
    {
        return 'System';
    }

    public function getIcon(): string
    {
        return 'settings';
    }

    public function getSortOrder(): int
    {
        return 90;
    }

    public function getMenuItems(): array
    {
        return [
            new MenuItem(
                id: 'config',
                label: 'Configuration',
                url: '/admin/config',
                sortOrder: 10,
                permission: 'system.config',
            ),
            new MenuItem(
                id: 'announcements',
                label: 'Announcements',
                url: '/admin/announcements',
                sortOrder: 20,
                permission: 'system.announcements',
            ),
        ];
    }
}
