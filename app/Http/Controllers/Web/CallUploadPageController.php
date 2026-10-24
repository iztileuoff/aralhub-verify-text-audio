<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CallRecording;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;
use Illuminate\View\View;

class CallUploadPageController extends Controller
{
    /**
     * The page behind a customer's upload link (calls:upload-link). The API
     * endpoints it talks to are signed with the same expiry, so the whole
     * link stops working at once.
     */
    public function __invoke(Request $request, string $batch): View
    {
        $expiresAt = Carbon::createFromTimestamp((int) $request->query('expires'));

        abort_if($request->query('expires') === null, 403);

        $sign = fn (string $route): string => URL::temporarySignedRoute($route, $expiresAt, ['batch' => $batch], absolute: false);

        return view('calls-upload', [
            'batch' => $batch,
            'expiresAt' => $expiresAt,
            'config' => [
                'urls' => [
                    'existing' => $sign('calls.upload.existing'),
                    'files' => $sign('calls.upload.files'),
                    'archiveStart' => $sign('calls.upload.archive.start'),
                    'archiveParts' => $sign('calls.upload.archive.parts'),
                    'archiveComplete' => $sign('calls.upload.archive.complete'),
                ],
                'audioExtensions' => CallRecording::UPLOAD_AUDIO_EXTENSIONS,
                'archivePattern' => CallRecording::UPLOAD_ARCHIVE_PATTERN,
                'maxFileBytes' => CallRecording::UPLOAD_MAX_FILE_BYTES,
                'maxArchiveBytes' => CallRecording::UPLOAD_MAX_ARCHIVE_BYTES,
            ],
        ]);
    }
}
