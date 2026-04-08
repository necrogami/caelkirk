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
        $existing = $this->findOneBy(['key' => $key]);

        if ($existing !== null) {
            $existing->value = $value;
            $existing->updatedAt = new DateTimeImmutable();
            $this->save($existing);
        } else {
            $config = new SystemConfig();
            $config->key = $key;
            $config->value = $value;
            $config->updatedAt = new DateTimeImmutable();
            $this->save($config);
        }
    }

    public function getAll(): array
    {
        return $this->findAll();
    }
}
