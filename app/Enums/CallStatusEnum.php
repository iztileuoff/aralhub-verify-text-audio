<?php

namespace App\Enums;

enum CallStatusEnum: string
{
    /** В очереди, никто не взял. */
    case PENDING = 'pending';

    /** Взят обрезчиком, он работает. */
    case IN_PROGRESS = 'in_progress';

    /** Сдан, ждёт модерации. */
    case SUBMITTED = 'submitted';

    /** Принят модератором. */
    case APPROVED = 'approved';

    /** Возвращён тому же обрезчику с причиной. */
    case REJECTED = 'rejected';

    /** Обрезчик пометил как непригодный. */
    case SKIPPED = 'skipped';

    public function getLabelText(): string
    {
        return match ($this) {
            self::PENDING => 'В очереди',
            self::IN_PROGRESS => 'В работе',
            self::SUBMITTED => 'На модерации',
            self::APPROVED => 'Принят',
            self::REJECTED => 'Возвращён',
            self::SKIPPED => 'Непригодный',
        };
    }
}
