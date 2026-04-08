<?php

declare(strict_types=1);

namespace App\Foundation\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('players')]
class Player extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public ?int $id = null;

    #[Column(name: 'user_id', references: 'users.id', onDelete: 'cascade')]
    public int $userId;

    #[Column(length: 50, unique: true)]
    public string $name;

    #[Column(name: 'slot_number')]
    public int $slotNumber;

    #[Column(name: 'created_at', default: 'CURRENT_TIMESTAMP')]
    public ?DateTimeImmutable $createdAt = null;

    #[Column(name: 'updated_at')]
    public ?DateTimeImmutable $updatedAt = null;
}
