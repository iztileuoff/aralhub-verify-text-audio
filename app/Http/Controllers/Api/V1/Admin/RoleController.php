<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = collect(RoleEnum::cases())
            ->filter(fn (RoleEnum $role) => in_array($role, [
                RoleEnum::ADMIN,
                RoleEnum::VOLUNTEER,
            ]))
            ->map(fn (RoleEnum $role) => [
                'id' => $role->value,
                'name' => $role->getLabelText(),
            ])
            ->values();

        return response()->json([
            'data' => $roles,
        ]);
    }
}
