<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Http\Controllers\Controller;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;

#[ExcludeRouteFromDocs]
class CallUploadExistingController extends Controller
{
    /**
     * What this batch already holds, so a reopened upload link skips it:
     * audio files by their path inside the picked folder, archives by name.
     */
    public function __invoke(string $batch): JsonResponse
    {
        return response()->json([
            'files' => $this->sizes(CallRecording::sourcePrefix($batch)),
            'archives' => $this->sizes(CallRecording::archivePrefix($batch)),
        ]);
    }

    /**
     * @return array<string, int>|object relative path => size in bytes
     */
    private function sizes(string $prefix): array|object
    {
        $sizes = [];

        foreach (Storage::disk(CallRecording::disk())->getDriver()->listContents($prefix, true) as $item) {
            if ($item instanceof FileAttributes) {
                $sizes[substr($item->path(), strlen($prefix) + 1)] = (int) $item->fileSize();
            }
        }

        // An empty PHP array would reach the browser as [] instead of {}.
        return $sizes === [] ? (object) [] : $sizes;
    }
}
