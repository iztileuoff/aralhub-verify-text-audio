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
