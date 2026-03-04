<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\UploadFileRequest;
use App\Http\Resources\V1\Admin\FileCollection;
use App\Http\Resources\V1\Admin\FileResource;
use App\Jobs\ProcessTsvFileJob;
use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public function index(Request $request)
    {
        $files = File::query()
            ->with('user')
            ->paginate($request->input('per_page', 10));

        return new FileCollection($files);
    }

    public function store(UploadFileRequest $request)
    {
        $uploaded = $request->file('file');
        $userId = $request->input('user_id');

        // Store the raw TSV in a private disk
        $storedPath = Storage::disk('local')->putFileAs(
            "tsv_uploads/{$userId}",
            $uploaded,
            $uploaded->getClientOriginalName()
        );

        // Create a File record immediately so the user can track it
        $file = File::create([
            'filename' => $uploaded->getClientOriginalName(),
            'path' => $storedPath,
            'mime_type' => $uploaded->getMimeType() ?? 'text/tab-separated-values',
            'size' => $uploaded->getSize(),
            'user_id' => $userId,
        ]);

        // Dispatch background job to parse and import rows
        ProcessTsvFileJob::dispatchSync($file);

        return new FileResource($file->load('user'));
    }

    public function show(File $file)
    {
        return new FileResource($file->load('user'));
    }

    public function destroy(File $file)
    {
        $file->delete();

        return response()->json();
    }
}
