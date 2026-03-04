<?php

namespace App\Enums;

enum RoleEnum: int
{
    case SUPER_ADMIN = 1;
    case ADMIN = 2;
    case VOLUNTEER = 3;

    public function getLabelText(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::VOLUNTEER => 'Volunteer',
        };
    }

    public function getPermissionsArray(): array
    {
        return match ($this) {
            self::SUPER_ADMIN => ['super-admin', 'admin'],
            self::ADMIN => ['admin'],
            self::VOLUNTEER => ['volunteer'],
        };
    }
}
