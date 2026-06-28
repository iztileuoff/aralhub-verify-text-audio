<?php

namespace App\Enums;

enum AudioSplitStatusEnum: string
{
    /** Обычное аудио короче лимита STT, обработка не нужна. */
    case NONE = 'NONE';

    /** Длиннее лимита STT, ожидает разрезания на 2 части. */
    case PENDING = 'PENDING';

    /** Успешно разрезано на 2 части. */
    case SPLIT = 'SPLIT';

    /** Разрезать нельзя, выведено из STT-потока. */
    case UNSPLITTABLE = 'UNSPLITTABLE';

    public function getLabelText(): string
    {
        return match ($this) {
            self::NONE => 'Обычное',
            self::PENDING => 'Ожидает разрезания',
            self::SPLIT => 'Разрезано',
            self::UNSPLITTABLE => 'Неразрезаемое',
        };
    }
}
