<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Http\Controllers\Concerns\UsesCallsBucketClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\CallArchiveUploadRequest;
use App\Models\CallRecording;
use Aws\Exception\AwsException;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\JsonResponse;

#[ExcludeRouteFromDocs]
class CallArchiveStartController extends Controller
{
    use UsesCallsBucketClient;

    /**
     * Start — or resume — the multipart upload of an archive. Resuming is
     * found on the bucket side (an unfinished upload of the same key), so it
     * works after a reload, in another browser or on another computer.
     */
    public function __invoke(CallArchiveUploadRequest $request, string $batch): JsonResponse
    {
        $name = $request->validated('name');
        $size = (int) $request->validated('size');
        $key = $this->archiveKey($batch, $name);

        try {
            $client = $this->bucketClient();

            $unfinished = collect($client->listMultipartUploads(['Bucket' => $this->bucketName(), 'Prefix' => $key])['Uploads'] ?? [])
                ->where('Key', $key)
                ->sortByDesc(fn (array $upload): string => (string) $upload['Initiated'])
                ->first();

            $uploadId = $unfinished['UploadId']
                ?? $client->createMultipartUpload([
                    'Bucket' => $this->bucketName(),
                    'Key' => $key,
                    'ContentType' => 'application/octet-stream',
                ])['UploadId'];

            // A part counts only if it has the size this file's part must have —
            // a leftover from a different file of the same name is re-sent.
            $uploaded = collect($unfinished ? $this->uploadedParts($client, $key, $uploadId) : [])
                ->filter(fn (array $part, int $number): bool => $part['size'] === $this->expectedPartSize($number, $size))
                ->keys()
                ->values();
        } catch (AwsException $exception) {
            report($exception);

            return response()->json(['message' => 'Хранилище не ответило. Попробуйте ещё раз.'], 502);
        }

        return response()->json([
            'upload_id' => $uploadId,
            'part_size' => CallRecording::UPLOAD_PART_BYTES,
            'parts_total' => (int) ceil($size / CallRecording::UPLOAD_PART_BYTES),
            'uploaded' => $uploaded,
        ]);
    }
}
