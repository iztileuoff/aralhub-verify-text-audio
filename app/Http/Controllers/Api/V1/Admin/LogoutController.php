<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Authentication', weight: 10)]
class LogoutController extends Controller
{
    public function __invoke(Request $request)
    {
        auth()->user()->currentAccessToken()->delete();

        return response()->json();
    }
}
