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

];
