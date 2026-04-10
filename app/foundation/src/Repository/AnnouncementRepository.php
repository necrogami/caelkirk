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
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');

        // Get active announcements, then filter date range in PHP
        // since the query builder doesn't support grouped OR conditions.
        $announcements = $this->query()
            ->where('active', '=', true)
            ->orderBy('created_at', 'desc')
            ->getEntities();

        return array_values(array_filter($announcements, function ($a) use ($now) {
            if ($a->startsAt !== null && $a->startsAt->format('Y-m-d H:i:s') > $now) {
                return false;
            }
            if ($a->endsAt !== null && $a->endsAt->format('Y-m-d H:i:s') < $now) {
                return false;
            }
            return true;
        }));
    }

    public function findAllOrdered(): array
    {
        return $this->query()
            ->orderBy('created_at', 'desc')
            ->getEntities();
    }
}
