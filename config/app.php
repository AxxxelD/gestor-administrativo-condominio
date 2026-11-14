<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nombre de la aplicación
    |--------------------------------------------------------------------------
    |
    | Nombre que Laravel muestra en las notificaciones y demás elementos de la interfaz.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Entorno de ejecución
    |--------------------------------------------------------------------------
    |
    | Define el entorno de la aplicación y permite ajustar la configuración de sus servicios. Se establece en .env.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Modo de depuración
    |--------------------------------------------------------------------------
    |
    | Al activarlo, los errores muestran información detallada y su traza. Al desactivarlo, se presenta una página de error genérica.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Dirección de la aplicación
    |--------------------------------------------------------------------------
    |
    | Dirección raíz que utiliza Artisan para generar enlaces desde la consola.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Zona horaria de la aplicación
    |--------------------------------------------------------------------------
    |
    | Zona horaria utilizada por las funciones de fecha y hora de PHP. El condominio trabaja con America/Caracas.
    |
    */

    'timezone' => 'America/Caracas',

    /*
    |--------------------------------------------------------------------------
    | Idioma y configuración regional
    |--------------------------------------------------------------------------
    |
    | Idioma predeterminado de las traducciones de Laravel. Debe corresponder a las traducciones disponibles en la aplicación.
    |
    */

    'locale' => 'es',

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'es'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'es_VE'),

    /*
    |--------------------------------------------------------------------------
    | Clave de cifrado
    |--------------------------------------------------------------------------
    |
    | Clave aleatoria utilizada por los servicios de cifrado. Debe generarse antes de publicar la aplicación y mantenerse protegida.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Controlador del modo de mantenimiento
    |--------------------------------------------------------------------------
    |
    | Determina cómo se guarda el estado de mantenimiento. El controlador cache permite compartirlo entre varios servidores.
    | Controladores compatibles file, cache y array.
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
