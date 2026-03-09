<?php

namespace App\Enums;

enum RoleEnum: int
{
    case SUPER_ADMIN = 1;
    case ADMIN = 2;
    case EDITOR = 3;
    case SPEAKER = 4;
    case MODERATOR = 5;

    public function getLabelText(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::EDITOR => 'Editor',
            self::SPEAKER => 'Speaker',
            self::MODERATOR => 'Moderator',
        };
    }

    public function getPermissionsArray(): array
    {
        return match ($this) {
            self::SUPER_ADMIN => ['super-admin', 'admin'],
            self::ADMIN => ['admin'],
            self::EDITOR => ['editor'],
            self::SPEAKER => ['speaker'],
            self::MODERATOR => ['moderator'],
        };
    }
}
