<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\CallResource;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;

#[Group(name: 'Calls - Moderation', weight: 61)]
class CallShowController extends Controller
{
    /**
     * Один звонок по id, в любом статусе (например, открыть по ссылке или найти #704).
     */
    public function __invoke(CallRecording $call): JsonResponse
    {
        return (new CallResource($call->load(['trimmer', 'moderator', 'landmark'])))->response();
    }
}
