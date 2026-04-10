<?php

declare(strict_types=1);

namespace App\Foundation\Repository;

use App\Foundation\Entity\SystemConfig;
use DateTimeImmutable;
use Marko\Database\Repository\Repository;

class SystemConfigRepository extends Repository
{
    protected const string ENTITY_CLASS = SystemConfig::class;

    public function getValue(string $key): ?string
    {
        $config = $this->findOneBy(['key' => $key]);

        return $config?->value;
    }

    public function setValue(string $key, string $value): void
    {
        $now = (new DateTimeImmutable())->format('Y-m-d H:i:s');
        $existing = $this->findOneBy(['key' => $key]);

        if ($existing !== null) {
            $this->connection->execute(
                'UPDATE system_config SET value = ?, updated_at = ? WHERE key = ?',
                [$value, $now, $key],
            );
        } else {
            $this->connection->execute(
                'INSERT INTO system_config (key, value, updated_at) VALUES (?, ?, ?)',
                [$key, $value, $now],
            );
        }
    }

    public function getAll(): array
    {
        return $this->findAll();
    }
}
