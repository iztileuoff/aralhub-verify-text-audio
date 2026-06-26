<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreUserRequest;
use App\Http\Requests\Api\V1\Admin\UpdateUserRequest;
use App\Http\Resources\V1\Admin\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Users & Roles', weight: 40)]
class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->where('role', '!=', RoleEnum::SUPER_ADMIN->value)
            ->when($request->filled('search'), fn ($q) => $q->search($request->input('search')))
            ->when($request->filled('specialization_id'), fn ($q) => $q->where('specialization_id', $request->input('specialization_id')))
            ->when(auth()->user()->role === RoleEnum::ADMIN, fn ($q) => $q->where('admin_id', auth()->user()->id))
            ->withCount(['finishedSpeakAudio', 'isCorrectTrueAudio', 'isCorrectFalseAudio'])
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 10));

        return UserResource::collection($users);
    }

    public function store(StoreUserRequest $request)
    {
        $user = User::create($request->validated());

        return new UserResource($user);
    }

    public function show(User $user)
    {
        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $user->update($request->validated());

        return new UserResource($user);
    }

    public function destroy(User $user)
    {
        $user->delete();

        return response()->json();
    }
}
