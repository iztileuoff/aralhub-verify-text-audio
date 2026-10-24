<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        'yandex-s3' => [
            'driver' => 's3',
            'key' => env('YANDEX_ACCESS_KEY_ID'),
            'secret' => env('YANDEX_SECRET_ACCESS_KEY'),
            'region' => env('YANDEX_DEFAULT_REGION'),
            'bucket' => env('YANDEX_BUCKET_NAME'),
            'url' => env('YANDEX_STORAGE_URL'),
            'endpoint' => env('YANDEX_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * Cloudflare R2. S3-compatible, so the same driver applies; "region"
         * is always "auto" and the bucket must be reached through a public
         * r2.dev subdomain or a custom domain set as R2_URL, because playback
         * URLs are handed straight to the browser.
         */
        'r2' => [
            'driver' => 's3',
            'key' => env('R2_ACCESS_KEY_ID'),
            'secret' => env('R2_SECRET_ACCESS_KEY'),
            'region' => env('R2_DEFAULT_REGION', 'auto'),
            'bucket' => env('R2_BUCKET_NAME'),
            'url' => env('R2_URL'),
            'endpoint' => env('R2_ENDPOINT'),
            'use_path_style_endpoint' => env('R2_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        /*
         * Phone-call recordings (config/calls.php). A separate, private R2
         * bucket: unlike the speaker recordings it has no public URL, so
         * playback goes through temporaryUrl(). Credentials default to the
         * main R2 ones, since both buckets usually sit in one account.
         */
        'calls' => [
            'driver' => 's3',
            'key' => env('CALLS_ACCESS_KEY_ID', env('R2_ACCESS_KEY_ID')),
            'secret' => env('CALLS_SECRET_ACCESS_KEY', env('R2_SECRET_ACCESS_KEY')),
            'region' => env('CALLS_DEFAULT_REGION', 'auto'),
            'bucket' => env('CALLS_BUCKET_NAME'),
            'endpoint' => env('CALLS_ENDPOINT', env('R2_ENDPOINT')),
            'use_path_style_endpoint' => env('CALLS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
