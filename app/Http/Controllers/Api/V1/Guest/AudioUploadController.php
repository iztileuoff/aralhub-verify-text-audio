<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\Audio;
use Illuminate\Http\Request;

class AudioUploadController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'audio' => ['required', 'file', 'mimes:wav'],
            'duration' => ['required', 'integer'],
        ]);

        $file = $validated['audio'];
        $filename = $file->getClientOriginalName();

        $audio = Audio::query()
            ->where('edit_audio_filename', "audio/{$filename}")
            ->first();

        if (!$audio) {
            return response()->json([
                'message' => 'Audio not found',
            ], 404);
        }

        $path = $file->store('audio_conv', 'public');

        $audio->update([
            'edit_converted_audio_filename' => $path,
            'edit_converted_audio_duration' => $validated['duration'],
        ]);

        return response()->json([
            'message' => 'Audio uploaded successfully.',
        ]);
    }
}
