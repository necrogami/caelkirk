<?php

declare(strict_types=1);

namespace App\Foundation\Enum;

enum Role: string
{
    case Player = 'player';
    case Builder = 'builder';
    case Admin = 'admin';

    public function level(): int
    {
        return match ($this) {
            self::Player => 0,
            self::Builder => 1,
            self::Admin => 2,
        };
    }

    public static function meetsRequirement(string $userRole, string $requiredRole): bool
    {
        $user = self::tryFrom($userRole);
        $required = self::tryFrom($requiredRole);

        if ($user === null || $required === null) {
            return false;
        }

        return $user->level() >= $required->level();
    }
}
