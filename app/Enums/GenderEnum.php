<?php

namespace App\Enums;

enum GenderEnum: string
{
    /** Мужчина. */
    case MALE = 'MALE';

    /** Женщина. */
    case FEMALE = 'FEMALE';

    public function getLabelText(): string
    {
        return match ($this) {
            self::MALE => 'Мужчина',
            self::FEMALE => 'Женщина',
        };
    }
}
