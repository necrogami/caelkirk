<?php

declare(strict_types=1);

namespace App\Foundation\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Index;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('admin_user_roles')]
#[Index('pk_admin_user_roles', ['user_id', 'role_id'], unique: true)]
class AdminUserRole extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(name: 'user_id', references: 'admin_users.id', onDelete: 'cascade')]
    public int $userId;

    #[Column(name: 'role_id', references: 'roles.id', onDelete: 'cascade')]
    public int $roleId;
}
