<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\RegistrationRequest;
use App\Http\Resources\V1\Admin\ProfileResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;

#[Group(name: 'Authentication', weight: 10)]
class RegistrationController extends Controller
{
    public function __invoke(RegistrationRequest $request)
    {
        $user = User::create($request->validated());

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
