<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\ActionResource;
use App\Models\Action;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Reports & Exports', weight: 80)]
class ActionController extends Controller
{
    public function index(Request $request)
    {
        $actions = Action::query()
            ->when($request->filled('user_id'), fn ($q) => $q->where('user_id', $request->input('user_id')))
            ->when($request->filled('text_id'), fn ($q) => $q->where('text_id', $request->input('text')))
            ->with(['user', 'text'])
            ->paginate($request->input('per_page', 10));

        return ActionResource::collection($actions);
    }
}
