<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TextStatusEnum;
use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Texts', weight: 50)]
class TextStatusesController extends Controller
{
    public function index(Request $request)
    {
        $textStatuses = collect(TextStatusEnum::cases())
            ->map(fn (TextStatusEnum $textStatus) => [
                'name' => $textStatus->value,
            ])
            ->values();

        return response()->json([
            'data' => $textStatuses,
        ]);
    }
}
