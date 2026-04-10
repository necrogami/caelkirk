<?php

declare(strict_types=1);

namespace App\Foundation\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('system_config')]
class SystemConfig extends Entity
{
    #[Column(length: 100, primaryKey: true)]
    public string $key;

    #[Column(type: 'text')]
    public string $value;

    #[Column(name: 'updated_at')]
    public ?DateTimeImmutable $updatedAt = null;
}
