<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Calls Disk
    |--------------------------------------------------------------------------
    |
    | Where the phone-call recordings and their trimmed results live. These are
    | conversations of real people, so the disk must be a private bucket: the
    | browser only ever gets short-lived signed URLs (see "url_ttl_minutes").
    |
    */

    'disk' => env('CALLS_DISK', 'calls'),

    /*
    |--------------------------------------------------------------------------
    | Lock TTL
    |--------------------------------------------------------------------------
    |
    | How long a trimmer holds a call without submitting it. After that the
    | call goes back to the queue and may be handed to someone else. Calls are
    | at most two minutes long, so half an hour is plenty.
    |
    */

    'lock_ttl_minutes' => (int) env('CALLS_LOCK_TTL_MINUTES', 30),

    /*
    |--------------------------------------------------------------------------
    | Signed URL TTL
    |--------------------------------------------------------------------------
    |
    | Lifetime of the playback URLs handed to the browser.
    |
    */

    'url_ttl_minutes' => (int) env('CALLS_URL_TTL_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Result Limits
    |--------------------------------------------------------------------------
    |
    | A trimmed call is a 16 kHz mono 16-bit WAV: two minutes are ~3.8 MB. The
    | limits leave headroom but stop a wrong file from being accepted.
    |
    */

    'max_duration_seconds' => (int) env('CALLS_MAX_DURATION_SECONDS', 180),

    'max_upload_kb' => (int) env('CALLS_MAX_UPLOAD_KB', 10240),

];
