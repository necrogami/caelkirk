<?php

declare(strict_types=1);

namespace App\Foundation\AdminSection;

use Marko\Admin\Attributes\AdminPermission;
use Marko\Admin\Attributes\AdminSection;
use Marko\Admin\Contracts\AdminSectionInterface;
use Marko\Admin\MenuItem;

#[AdminSection(id: 'users', label: 'Users', icon: 'users', sortOrder: 10)]
#[AdminPermission(id: 'users.view', label: 'View Users')]
#[AdminPermission(id: 'users.edit', label: 'Edit Users')]
#[AdminPermission(id: 'users.ban', label: 'Ban Users')]
class UserSection implements AdminSectionInterface
{
    public function getId(): string
    {
        return 'users';
    }

    public function getLabel(): string
    {
        return 'Users';
    }

    public function getIcon(): string
    {
        return 'users';
    }

    public function getSortOrder(): int
    {
        return 10;
    }

    public function getMenuItems(): array
    {
        return [
            new MenuItem(
                id: 'all-users',
                label: 'All Users',
                url: '/admin/users',
                sortOrder: 10,
                permission: 'users.view',
            ),
            new MenuItem(
                id: 'roles',
                label: 'Roles & Permissions',
                url: '/admin/roles',
                sortOrder: 20,
                permission: 'users.edit',
            ),
        ];
    }
}
