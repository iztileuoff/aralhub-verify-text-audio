<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\LoginRequest;
use App\Http\Resources\V1\Admin\ProfileResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

#[Group(name: 'Authentication', weight: 10)]
class LoginController extends Controller
{
    public function __invoke(LoginRequest $request)
    {
        $user = User::where('phone', $request->input('phone'))->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'login' => 'User not found.',
            ]);
        }

        if (! Hash::check($request->input('password'), $user->password)) {
            throw ValidationException::withMessages([
                'login' => 'Failed.',
            ]);
        }

        $device = substr($request->userAgent() ?? '', 0, 255);
        $permissions = $user->role?->getPermissionsArray();

        $accessToken = $user->createToken($device, $permissions)->plainTextToken;

        return response()->json([
            'data' => [
                'user' => new ProfileResource($user),
                'access_token' => $accessToken,
                'token_type' => 'Bearer',
            ],
        ]);
    }
}
