<?php

namespace App\Enums;

enum GenderEnum: string
{
    case MALE = 'MALE';
    case FEMALE = 'FEMALE';

    public function getLabelText(): string
    {
        return match ($this) {
            self::MALE => 'Мужчина',
            self::FEMALE => 'Женщина',
        };
    }
}
