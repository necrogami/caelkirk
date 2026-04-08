<?php

declare(strict_types=1);

namespace App\Foundation\Entity;

use DateTimeImmutable;
use Marko\Database\Attributes\Column;
use Marko\Database\Attributes\Table;
use Marko\Database\Entity\Entity;

#[Table('announcements')]
class Announcement extends Entity
{
    #[Column(primaryKey: true, autoIncrement: true)]
    public int $id;

    #[Column(length: 255)]
    public string $title;

    #[Column(type: 'text')]
    public string $body;

    #[Column(length: 20, default: 'info')]
    public string $type = 'info';

    #[Column(default: true)]
    public bool $active = true;

    #[Column(name: 'starts_at')]
    public ?DateTimeImmutable $startsAt = null;

    #[Column(name: 'ends_at')]
    public ?DateTimeImmutable $endsAt = null;

    #[Column(name: 'created_at', default: 'CURRENT_TIMESTAMP')]
    public DateTimeImmutable $createdAt;
}
