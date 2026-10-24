<?php

namespace App\Http\Controllers\Api\V1\Calls;

use App\Http\Controllers\Concerns\UsesCallsBucketClient;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Calls\CallArchiveUploadRequest;
use App\Models\CallRecording;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\JsonResponse;

#[ExcludeRouteFromDocs]
class CallArchivePartsController extends Controller
{
    use UsesCallsBucketClient;

    /** How long a signed part URL stays valid; the page asks just before each group of parts. */
    private const URL_TTL = '+2 hours';

    /**
     * Signed PUT URLs for the given parts of an archive upload.
     */
    public function __invoke(CallArchiveUploadRequest $request, string $batch): JsonResponse
    {
        $size = (int) $request->validated('size');
        $partsTotal = (int) ceil($size / CallRecording::UPLOAD_PART_BYTES);
        $parts = $request->validated('parts');

        if (max($parts) > $partsTotal) {
            return response()->json(['message' => "У этого файла всего {$partsTotal} частей."], 422);
        }

        $client = $this->bucketClient();
        $key = $this->archiveKey($batch, $request->validated('name'));
        $urls = [];

        foreach ($parts as $partNumber) {
            $command = $client->getCommand('UploadPart', [
                'Bucket' => $this->bucketName(),
                'Key' => $key,
                'UploadId' => $request->validated('upload_id'),
                'PartNumber' => $partNumber,
            ]);

            $urls[(string) $partNumber] = (string) $client->createPresignedRequest($command, self::URL_TTL)->getUri();
        }

        return response()->json(['urls' => $urls]);
    }
}
