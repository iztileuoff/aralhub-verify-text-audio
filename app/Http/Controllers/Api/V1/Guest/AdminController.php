<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Guest\UserCollection;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function __invoke(Request $request)
    {
        $admins = User::query()
            ->where('role', RoleEnum::ADMIN->value)
            ->orderByDesc('id')
            ->get();

        return new UserCollection($admins);
    }
}
