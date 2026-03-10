<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\TextStatusEnum;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\TextRequest;
use App\Http\Resources\V1\Admin\TextCollection;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Text;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class TextController extends Controller
{
    public function index(Request $request)
    {
        $texts = Text::query()
            ->when($request->filled('file_id'), fn ($q) => $q->where('file_id', $request->input('file_id')))
            ->when($request->filled('edit_user_id'), fn ($q) => $q->where('edit_user_id', $request->input('edit_user_id')))
            ->when($request->filled('text_status'), function (Builder $query) use ($request) {
                match ($request->input('text_status')) {
                    TextStatusEnum::PENDING->value => $query->whereNull('edit_finished_at'),
                    TextStatusEnum::EDITED->value => $query->whereNotNull('edit_finished_at'),
                    TextStatusEnum::EDIT_CANCELLED->value => $query->whereNotNull('edit_cancelled_user_id')->whereNull('edit_finished_at'),
                    default => $query,
                };
            })
            ->with(['editUser', 'editCancelledUser'])
            ->latest('edit_finished_at')
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
