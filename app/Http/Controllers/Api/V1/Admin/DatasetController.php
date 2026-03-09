<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDatasetFile;
use Illuminate\Http\Request;

class DatasetController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:txt',
        ]);

        $path = $request->file('file')->store('datasets');

        ProcessDatasetFile::dispatch($path);

        return response()->json([
            'message' => 'Dataset processing started',
        ]);
    }
}
