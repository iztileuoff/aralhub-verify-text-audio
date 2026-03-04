<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\TextRequest;
use App\Http\Resources\V1\Admin\TextCollection;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Text;
use Illuminate\Http\Request;

class TextController extends Controller
{
    public function index(Request $request)
    {
        $texts = Text::query()
            ->orderBy('id', 'desc')
            ->paginate($request->input('per_page', 10));

        return new TextCollection($texts);
    }

    public function store(TextRequest $request)
    {
        return new TextResource(Text::create($request->validated()));
    }

    public function show(Text $text)
    {
        return new TextResource($text);
    }

    public function update(TextRequest $request, Text $text)
    {
        $text->update($request->validated());

        return new TextResource($text);
    }

    public function destroy(Text $text)
    {
        $text->delete();

        return response()->json();
    }
}
