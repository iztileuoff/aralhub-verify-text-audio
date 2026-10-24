<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\SignCallUploadFilesRequest;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;

#[ExcludeRouteFromDocs]
class CallUploadFilesController extends Controller
{
    /** How long a signed PUT stays valid; the page asks just before each chunk. */
    private const URL_TTL_MINUTES = 120;

    /**
     * Signed PUT URLs for a chunk of audio files: the browser sends each file
     * straight to the calls bucket, the server never handles the bytes.
     */
    public function __invoke(SignCallUploadFilesRequest $request, string $batch): JsonResponse
    {
        $disk = Storage::disk(CallRecording::disk());
        $expiresAt = now()->addMinutes(self::URL_TTL_MINUTES);

        $uploads = array_map(function (array $file) use ($disk, $batch, $expiresAt): array {
            $signed = $disk->temporaryUploadUrl(CallRecording::sourcePrefix($batch).'/'.$file['path'], $expiresAt);

            return [
                'path' => $file['path'],
                'url' => $signed['url'],
                'headers' => $this->settableHeaders($signed['headers'] ?? []),
            ];
        }, $request->validated('files'));

        return response()->json(['uploads' => $uploads]);
    }

    /**
     * Signed headers the browser is allowed to set itself ("host" it sets on its own).
     *
     * @param  array<string, string|array<int, string>>  $headers
     * @return array<string, string>|object
     */
    private function settableHeaders(array $headers): array|object
    {
        $settable = [];

        foreach ($headers as $name => $value) {
            if (strtolower($name) !== 'host') {
                $settable[$name] = is_array($value) ? implode(', ', $value) : $value;
            }
        }

        return $settable === [] ? (object) [] : $settable;
    }
}
