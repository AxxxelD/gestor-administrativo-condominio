<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disco de archivos predeterminado
    |--------------------------------------------------------------------------
    |
    | Disco utilizado por Laravel para guardar archivos. Puede ser local o un servicio de almacenamiento en la nube.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Discos de almacenamiento
    |--------------------------------------------------------------------------
    |
    | Define los discos y sus opciones. Pueden existir varios discos con el mismo controlador.
    | Los controladores compatibles son local, ftp, sftp y s3.
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

    ],

    /*
    |--------------------------------------------------------------------------
    | Enlaces simbólicos
    |--------------------------------------------------------------------------
    |
    | Enlaces creados mediante el comando storage:link. Cada clave indica la ubicación del enlace y cada valor indica su destino.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
