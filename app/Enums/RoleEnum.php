<?php

namespace App\Enums;

enum RoleEnum: int
{
    /** Полный доступ ко всем разделам и пользователям. */
    case SUPER_ADMIN = 1;

    /** Управляет своими пользователями и контентом. */
    case ADMIN = 2;

    /** Редактирует тексты. */
    case EDITOR = 3;

    /** Озвучивает тексты (записывает аудио). */
    case SPEAKER = 4;

    /** Проверяет тексты и аудио. */
    case MODERATOR = 5;

    /** Обрезает телефонные звонки и пишет к ним текст; видит только этот раздел. */
    case TRIMMER = 6;

    public function getLabelText(): string
    {
        return match ($this) {
            self::SUPER_ADMIN => 'Super Admin',
            self::ADMIN => 'Admin',
            self::EDITOR => 'Editor',
            self::SPEAKER => 'Speaker',
            self::MODERATOR => 'Moderator',
            self::TRIMMER => 'Trimmer',
        };
    }

    /**
     * Roles this role may give to a user. Nobody grants the super admin;
     * only the super admin grants the admin role.
     *
     * @return array<int, self>
     */
    public function grantableRoles(): array
    {
        return match ($this) {
            self::SUPER_ADMIN => [self::ADMIN, self::EDITOR, self::SPEAKER, self::MODERATOR, self::TRIMMER],
            self::ADMIN => [self::EDITOR, self::SPEAKER, self::MODERATOR, self::TRIMMER],
            default => [],
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
            self::TRIMMER => ['trimmer'],
        };
    }
}
