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

        'papers' => [
            'driver' => 'local',
            'root' => storage_path('app/private/papers'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => true,
        ],

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            // Disk ini menyimpan berkas privat (mis. full paper), jadi tidak
            // boleh disajikan lewat URL publik; unduhan lewat controller yang
            // memeriksa hak akses. Mematikannya juga membebaskan path /storage
            // untuk disk publik.
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            // Laravel mendaftarkan route /storage/{path} sebagai cadangan. Bila
            // symlink public/storage hilang (sering terjadi di shared hosting
            // yang mematikan exec sehingga storage:link gagal), berkas tetap
            // tersaji lewat PHP alih-alih 404. Saat symlink ada, web server
            // menyajikan berkas langsung dan route ini tidak pernah tersentuh.
            'serve' => true,
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
