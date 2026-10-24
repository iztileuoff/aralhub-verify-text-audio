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
class CallArchiveCompleteController extends Controller
{
    use UsesCallsBucketClient;

    /**
     * Assemble the archive from its parts. The part list comes from the
     * bucket, not from the browser, and the archive is assembled only when
     * every part is there with the right size — a half-sent file never
     * becomes an object that looks complete.
     */
    public function __invoke(CallArchiveUploadRequest $request, string $batch): JsonResponse
    {
        $size = (int) $request->validated('size');
        $uploadId = $request->validated('upload_id');
        $key = $this->archiveKey($batch, $request->validated('name'));
        $partsTotal = (int) ceil($size / CallRecording::UPLOAD_PART_BYTES);

        try {
            $client = $this->bucketClient();
            $parts = $this->uploadedParts($client, $key, $uploadId);

            $missing = array_values(array_filter(
                range(1, $partsTotal),
                fn (int $number): bool => ($parts[$number]['size'] ?? null) !== $this->expectedPartSize($number, $size),
            ));

            if ($missing !== []) {
                return response()->json(['message' => 'Загружены не все части архива.', 'missing' => $missing], 422);
            }

            $client->completeMultipartUpload([
                'Bucket' => $this->bucketName(),
                'Key' => $key,
                'UploadId' => $uploadId,
                'MultipartUpload' => ['Parts' => array_map(
                    fn (int $number): array => ['PartNumber' => $number, 'ETag' => $parts[$number]['etag']],
                    range(1, $partsTotal),
                )],
            ]);
        } catch (AwsException $exception) {
            report($exception);

            return response()->json(['message' => 'Хранилище не ответило. Попробуйте ещё раз.'], 502);
        }

        return response()->json(['complete' => true]);
    }
}
