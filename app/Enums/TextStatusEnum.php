<?php

namespace App\Enums;

enum TextStatusEnum: string
{
    case PENDING = 'pending';
    case EDITED = 'edited';
    case EDIT_CANCELLED = 'edit_cancelled';
}
