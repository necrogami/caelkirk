<?php

declare(strict_types=1);

namespace App\Foundation\Repository;

use App\Foundation\Entity\Announcement;
use Marko\Database\Repository\Repository;

class AnnouncementRepository extends Repository
{
    protected const string ENTITY_CLASS = Announcement::class;

    public function findActive(): array
    {
        return $this->query()
            ->where('active', '=', true)
            ->orderBy('created_at', 'desc')
            ->getEntities();
    }

    public function findAllOrdered(): array
    {
        return $this->query()
            ->orderBy('created_at', 'desc')
            ->getEntities();
    }
}
