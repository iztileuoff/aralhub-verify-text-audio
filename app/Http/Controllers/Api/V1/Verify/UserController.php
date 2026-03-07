<?php

namespace App\Http\Controllers\Api\V1\Verify;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Verify\UserCollection;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));

        $users = User::query()
            ->where('role', '!=', RoleEnum::SUPER_ADMIN->value)
            ->when($request->filled('search'), fn ($q) => $q->search($request->input('search')))
            ->when($request->filled('specialization_id'), fn ($q) => $q->search($request->input('specialization_id')))
            ->withCount(['finishedEditTexts' => function ($query) use ($date) {
                $query->whereDate('edit_finished_at', '=', $date);
            }])
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 10));

        return new UserCollection($users);
    }
}
