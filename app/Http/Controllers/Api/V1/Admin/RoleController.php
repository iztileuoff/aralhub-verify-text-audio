<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\RoleEnum;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Users & Roles', weight: 40)]
class RoleController extends Controller
{
    public function index(Request $request)
    {
        $roles = collect(RoleEnum::cases())
            ->filter(fn (RoleEnum $role) => in_array($role, [
                RoleEnum::ADMIN,
                RoleEnum::EDITOR,
                RoleEnum::SPEAKER,
                RoleEnum::MODERATOR,
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
