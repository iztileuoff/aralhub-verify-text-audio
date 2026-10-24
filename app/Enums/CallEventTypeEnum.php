<?php

namespace App\Enums;

/**
 * What happened to a call. Every step is logged, and the per-trimmer
 * statistics for a period are counted from this log rather than from the
 * call's current state, which only says where it ended up.
 */
enum CallEventTypeEnum: string
{
    /** Обрезчик сдал результат (в том числе повторно после возврата). */
    case SUBMITTED = 'submitted';

    /** Модератор принял. */
    case APPROVED = 'approved';

    /** Модератор вернул обрезчику. */
    case REJECTED = 'rejected';

    /** Обрезчик пометил как непригодный. */
    case SKIPPED = 'skipped';

    /** Обрезчик вернул в очередь, не сделав. */
    case RELEASED = 'released';

    /** Админ вернул непригодный в очередь. */
    case RESTORED = 'restored';
}
