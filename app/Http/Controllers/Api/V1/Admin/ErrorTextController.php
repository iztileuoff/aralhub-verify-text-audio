<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Text;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Texts', weight: 50)]
class ErrorTextController extends Controller
{
    public function __invoke(Request $request)
    {
        $texts = Text::query()
            ->where('has_text_error', true)
            ->when($request->filled('file_id'), fn ($q) => $q->where('file_id', $request->input('file_id')))
            ->when($request->filled('text_error_reported_by'), fn ($q) => $q->where('text_error_reported_by', $request->input('text_error_reported_by')))
            ->with('textErrorReporter')
            ->orderByDesc('text_error_reported_at')
            ->paginate($request->input('per_page', 10));

        return TextResource::collection($texts);
    }
}
