<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Audio Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk new recordings are written to. Every audio row stores
    | the disk it was uploaded to, so this value can change without touching
    | anything already in storage.
    |
    */

    'disk' => env('AUDIO_DISK', 'r2'),

    /*
    |--------------------------------------------------------------------------
    | Legacy Audio Disk
    |--------------------------------------------------------------------------
    |
    | Where recordings made before "storage_disk" existed still live. Rows with
    | a NULL "storage_disk" are read from here; nothing is ever written to it
    | once the disk above points somewhere else.
    |
    */

    'legacy_disk' => env('AUDIO_LEGACY_DISK', 'yandex-s3'),

];
