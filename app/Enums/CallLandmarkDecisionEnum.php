<?php

namespace App\Enums;

/**
 * What an admin decided about a call in the landmark queue. Any decision
 * takes the call out of the queue for good. The API reports it as the
 * call's "landmark_status", next to the undecided pending / in_progress.
 */
enum CallLandmarkDecisionEnum: string
{
    /** Новый ориентир создан этим звонком. */
    case LABELED = 'labeled';

    /** Ориентир уже был — звонок привязан к существующему. */
    case DUPLICATE = 'duplicate';

    /** Ориентира в звонке нет. */
    case EMPTY = 'empty';
}
