<?php

use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Autenticación predeterminada
    |--------------------------------------------------------------------------
    |
    | Define el guard de autenticación y el broker de recuperación de contraseñas que se utilizarán de forma predeterminada.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mecanismos de autenticación
    |--------------------------------------------------------------------------
    |
    | Cada guard define cómo se autentica una solicitud y utiliza un proveedor para recuperar los usuarios.
    | La configuración actual usa sesiones y el proveedor Eloquent. El controlador admitido es session.
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Proveedores de usuarios
    |--------------------------------------------------------------------------
    |
    | Definen cómo recuperar usuarios desde la base de datos. Pueden configurarse varios proveedores para diferentes tablas o modelos y asociarse a distintos guards.
    | Los controladores admitidos son database y eloquent.
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Recuperación de contraseñas
    |--------------------------------------------------------------------------
    |
    | Configura la tabla de tokens y el proveedor de usuarios para recuperar contraseñas.
    | expire indica los minutos de validez del token. throttle indica los segundos de espera antes de generar otro token.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vigencia de la confirmación de contraseña
    |--------------------------------------------------------------------------
    |
    | Cantidad de segundos durante los que se considera válida una confirmación de contraseña. Al vencer, se solicita nuevamente. El valor predeterminado equivale a tres horas.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
