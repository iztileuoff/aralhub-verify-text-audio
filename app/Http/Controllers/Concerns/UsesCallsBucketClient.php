<?php

namespace App\Http\Controllers\Concerns;

use App\Models\CallRecording;
use Aws\S3\S3Client;
use Illuminate\Support\Facades\Storage;

/**
 * Direct access to the calls bucket for what Flysystem does not cover:
 * multipart uploads straight from the customer's browser.
 */
trait UsesCallsBucketClient
{
    protected function bucketClient(): S3Client
    {
        return Storage::disk(CallRecording::disk())->getClient();
    }

    protected function bucketName(): string
    {
        return (string) config('filesystems.disks.'.CallRecording::disk().'.bucket');
    }

    protected function archiveKey(string $batch, string $name): string
    {
        return CallRecording::archivePrefix($batch).'/'.$name;
    }

    /**
     * Parts of a multipart upload already stored, all pages of them.
     *
     * @return array<int, array{size: int, etag: string}> part number => part
     */
    protected function uploadedParts(S3Client $client, string $key, string $uploadId): array
    {
        $parts = [];
        $marker = 0;

        do {
            $page = $client->listParts([
                'Bucket' => $this->bucketName(),
                'Key' => $key,
                'UploadId' => $uploadId,
                'PartNumberMarker' => $marker,
            ]);

            foreach ($page['Parts'] ?? [] as $part) {
                $parts[(int) $part['PartNumber']] = ['size' => (int) $part['Size'], 'etag' => (string) $part['ETag']];
            }

            $marker = (int) ($page['NextPartNumberMarker'] ?? 0);
        } while (! empty($page['IsTruncated']));

        ksort($parts);

        return $parts;
    }

    /**
     * Expected size of a part of a file split into UPLOAD_PART_BYTES pieces.
     */
    protected function expectedPartSize(int $partNumber, int $fileSize): int
    {
        $offset = ($partNumber - 1) * CallRecording::UPLOAD_PART_BYTES;

        return max(0, min(CallRecording::UPLOAD_PART_BYTES, $fileSize - $offset));
    }
}
