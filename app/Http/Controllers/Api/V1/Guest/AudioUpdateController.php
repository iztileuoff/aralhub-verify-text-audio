<?php

namespace App\Http\Controllers\Api\V1\Guest;

use App\Http\Controllers\Controller;
use App\Models\Audio;
use Illuminate\Http\Request;

class AudioUpdateController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'audio_filename' => ['required', 'string', 'max:255'],
            'duration' => ['required', 'integer'],
        ]);

        $filename = $validated['audio_filename'];

        $audio = Audio::query()
            ->where('edit_audio_filename', "audio/{$filename}")
            ->first();

        if (! $audio) {
            return response()->json([
                'message' => 'Audio not found',
            ], 404);
        }

        $audio->update([
            'edit_converted_audio_duration' => $validated['duration'],
        ]);

        return response()->json([
            'message' => 'Audio updated successfully.',
        ]);
    }
}
