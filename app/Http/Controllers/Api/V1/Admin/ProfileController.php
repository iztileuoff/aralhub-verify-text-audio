<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UpdateProfileRequest;
use App\Http\Resources\V1\Admin\ProfileResource;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function show(Request $request)
    {
        $date = $request->input('date', now()->format('Y-m-d'));

        return new ProfileResource(auth()->user()->loadCount(['finishedEditTexts', 'todayFinishedEditTexts']));
    }

    public function update(UpdateProfileRequest $request)
    {
        return new ProfileResource(tap(auth()->user())->update($request->validated()));
    }
}
