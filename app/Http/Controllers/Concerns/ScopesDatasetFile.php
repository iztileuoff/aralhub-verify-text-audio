<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait ScopesDatasetFile
{
    /**
     * The dataset a list should be scoped to: the file_id asked for, or null
     * meaning the active dataset. Passing an explicit id is how the admin
     * reaches an older dataset, exactly like --file-id in the export commands.
     */
    protected function requestedFileId(Request $request): ?int
    {
        return $request->filled('file_id') ? (int) $request->input('file_id') : null;
    }
}
