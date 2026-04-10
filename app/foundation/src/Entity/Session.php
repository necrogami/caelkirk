<?php

declare(strict_types=1);

namespace App\Foundation\Entity;

use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('sessions')]
class Session extends Entity
{
    #[Column(length: 128, primaryKey: true)]
    public string $id;

    #[Column(type: 'text')]
    public string $payload;

    #[Column(name: 'last_activity')]
    public int $lastActivity;
}
