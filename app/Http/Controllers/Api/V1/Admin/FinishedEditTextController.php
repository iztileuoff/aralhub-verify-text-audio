<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Concerns\ScopesDatasetFile;
use App\Http\Controllers\Controller;
use App\Http\Resources\V1\Admin\TextResource;
use App\Models\Text;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Request;

#[Group(name: 'Texts', weight: 50)]
class FinishedEditTextController extends Controller
{
    use ScopesDatasetFile;

    public function __invoke(Request $request)
    {
        $texts = Text::query()
            ->mainFile($this->requestedFileId($request))
            ->whereNotNull('edit_finished_at')
            ->when($request->filled('edit_user_id'), fn ($q) => $q->where('edit_user_id', $request->input('edit_user_id')))
            ->with('editUser')
            ->orderByDesc('edit_finished_at')
            ->paginate($request->input('per_page', 10));

        return TextResource::collection($texts);
    }
}
