<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Text;
use Illuminate\Http\Request;

class DatasetController extends Controller
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:txt',
        ]);

        $file = $request->file('file');

        $handle = fopen($file->getRealPath(), 'r');

        $updated = 0;

        while (($line = fgets($handle)) !== false) {

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $parts = explode("\t", $line);

            if (count($parts) < 4) {
                continue;
            }

            $audioFilename = trim($parts[0]);
            $filterOriginalTranscript = trim($parts[1]);
            $filterNormalizedTranscript = trim($parts[2]);
            $filterTokenizedTranscript = trim($parts[3]);

            Text::where('audio_filename', $audioFilename)->update([
                'filter_original_transcript' => $filterOriginalTranscript,
                'filter_normalized_transcript' => $filterNormalizedTranscript,
                'filter_tokenized_transcript' => $filterTokenizedTranscript,
            ]);

            $updated++;
        }

        fclose($handle);

        return response()->json([
            'status' => 'success',
            'updated_rows' => $updated,
        ]);
    }
}
