<?php

namespace App\Enums;

enum CallSkipReasonEnum: string
{
    /** Нет разговора. */
    case NO_SPEECH = 'no_speech';

    /** Битый или не открывается. */
    case BROKEN = 'broken';

    /** Разговор не на каракалпакском. */
    case NOT_KARAKALPAK = 'not_karakalpak';

    /** Ничего не разобрать. */
    case UNINTELLIGIBLE = 'unintelligible';

    /** Другое — с комментарием. */
    case OTHER = 'other';

    public function getLabelText(): string
    {
        return match ($this) {
            self::NO_SPEECH => 'Нет разговора',
            self::BROKEN => 'Битый файл',
            self::NOT_KARAKALPAK => 'Не на каракалпакском',
            self::UNINTELLIGIBLE => 'Неразборчиво',
            self::OTHER => 'Другое',
        };
    }
}
