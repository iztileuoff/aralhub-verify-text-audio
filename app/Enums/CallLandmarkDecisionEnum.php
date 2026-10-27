<?php

namespace App\Enums;

/**
 * What an admin decided about a call in the landmark queue. Any decision
 * takes the call out of the queue for good.
 */
enum CallLandmarkDecisionEnum: string
{
    /** Новый ориентир; этот звонок — его главное аудио. */
    case PRIMARY = 'primary';

    /** Ещё один пример уже известного ориентира. */
    case ALSO = 'also';

    /** Ориентир уже есть — звонок пропущен (запомнено, какой это был ориентир). */
    case DUPLICATE = 'duplicate';

    /** В разговоре ориентира нет. */
    case NONE = 'none';
}
