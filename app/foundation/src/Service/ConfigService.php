<?php

declare(strict_types=1);

namespace App\Foundation\Service;

use App\Foundation\Repository\SystemConfigRepository;

class ConfigService
{
    public function __construct(
        private readonly SystemConfigRepository $configRepository,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->configRepository->getValue($key);

        if ($value === null) {
            return $default;
        }

        return json_decode($value, true);
    }

    public function set(string $key, mixed $value): void
    {
        $this->configRepository->setValue($key, json_encode($value));
    }

    public function getCharacterSlotLimit(?int $userOverride): int
    {
        if ($userOverride !== null) {
            return $userOverride;
        }

        return (int) $this->get('character_slot_default', 50);
    }
}
