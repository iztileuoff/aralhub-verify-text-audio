<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Main File Id
    |--------------------------------------------------------------------------
    |
    | The identifier of the file whose texts make up the primary dataset that
    | the verification pipeline (editing, speaking, moderation) operates on.
    | Previously hard-coded as the magic number "8" across several queries.
    |
    */

    'main_file_id' => (int) env('DATASET_MAIN_FILE_ID', 8),

    /*
    |--------------------------------------------------------------------------
    | Max Audio Per Text
    |--------------------------------------------------------------------------
    |
    | How many recordings one text is worth collecting. Once a text reaches
    | this many audios it leaves the speaker queue, so the remaining effort
    | goes to texts nobody has read yet. Every recording is by a different
    | speaker — a speaker is never offered a text they already recorded.
    |
    */

    'max_audio_per_text' => (int) env('DATASET_MAX_AUDIO_PER_TEXT', 3),

];
