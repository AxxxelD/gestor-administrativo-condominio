<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Controlador de sesiones predeterminado
    |--------------------------------------------------------------------------
    |
    | Determina dónde se guarda la sesión entre solicitudes. La aplicación puede usar archivos, cookies, base de datos u otros almacenes.
    | Controladores compatibles file, cookie, database, memcached, redis, dynamodb y array.
    |
    */

    'driver' => env('SESSION_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Duración de la sesión
    |--------------------------------------------------------------------------
    |
    | Minutos permitidos de inactividad antes de que venza la sesión. expire_on_close permite que termine al cerrar el navegador.
    |
    */

    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    /*
    |--------------------------------------------------------------------------
    | Cifrado de sesiones
    |--------------------------------------------------------------------------
    |
    | Indica si los datos de sesión deben cifrarse antes de almacenarse. Laravel realiza el cifrado de forma automática.
    |
    */

    'encrypt' => env('SESSION_ENCRYPT', false),

    /*
    |--------------------------------------------------------------------------
    | Ubicación de los archivos de sesión
    |--------------------------------------------------------------------------
    |
    | Directorio donde se guardan las sesiones cuando se utiliza el controlador file.
    |
    */

    'files' => storage_path('framework/sessions'),

    /*
    |--------------------------------------------------------------------------
    | Conexión de datos de las sesiones
    |--------------------------------------------------------------------------
    |
    | Conexión utilizada por los controladores database o redis. Debe coincidir con una conexión de la configuración de base de datos.
    |
    */

    'connection' => env('SESSION_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Tabla de sesiones
    |--------------------------------------------------------------------------
    |
    | Tabla utilizada para guardar las sesiones cuando el controlador seleccionado es database.
    |
    */

    'table' => env('SESSION_TABLE', 'sessions'),

    /*
    |--------------------------------------------------------------------------
    | Almacén de caché de las sesiones
    |--------------------------------------------------------------------------
    |
    | Almacén usado para conservar las sesiones entre solicitudes. Debe estar definido en la configuración de caché.
    | Esta opción afecta a los controladores dynamodb, memcached y redis.
    |
    */

    'store' => env('SESSION_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Frecuencia de limpieza de sesiones
    |--------------------------------------------------------------------------
    |
    | Probabilidad de eliminar sesiones vencidas en una solicitud cuando el controlador requiere limpieza manual. El valor predeterminado es 2 de cada 100 solicitudes.
    |
    */

    'lottery' => [2, 100],

    /*
    |--------------------------------------------------------------------------
    | Nombre de la cookie de sesión
    |--------------------------------------------------------------------------
    |
    | Nombre de la cookie creada por Laravel para identificar la sesión. Cambiarlo no aporta por sí solo una mejora significativa de seguridad.
    |
    */

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel')).'-session'
    ),

    /*
    |--------------------------------------------------------------------------
    | Ruta de la cookie de sesión
    |--------------------------------------------------------------------------
    |
    | Ruta de la aplicación para la que estará disponible la cookie. Habitualmente corresponde a la raíz.
    |
    */

    'path' => env('SESSION_PATH', '/'),

    /*
    |--------------------------------------------------------------------------
    | Dominio de la cookie de sesión
    |--------------------------------------------------------------------------
    |
    | Dominio y subdominios para los que estará disponible la cookie. Permite definir el alcance cuando la aplicación lo necesita.
    |
    */

    'domain' => env('SESSION_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Cookies enviadas mediante HTTPS
    |--------------------------------------------------------------------------
    |
    | Al activar esta opción, el navegador envía la cookie de sesión al servidor únicamente a través de una conexión HTTPS.
    |
    */

    'secure' => env('SESSION_SECURE_COOKIE'),

    /*
    |--------------------------------------------------------------------------
    | Acceso a la cookie mediante HTTP
    |--------------------------------------------------------------------------
    |
    | Al activar esta opción, JavaScript no puede leer la cookie. Su acceso queda reservado al protocolo HTTP.
    |
    */

    'http_only' => env('SESSION_HTTP_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Cookies y solicitudes entre sitios
    |--------------------------------------------------------------------------
    |
    | Controla cuándo se envía la cookie en solicitudes entre sitios y ayuda a mitigar ataques CSRF. El valor predeterminado es lax.
    | Valores compatibles lax, strict, none y null.
    | Referencia https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#samesitesamesite-value
    |
    */

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    /*
    |--------------------------------------------------------------------------
    | Cookies particionadas
    |--------------------------------------------------------------------------
    |
    | Vincula la cookie al sitio principal en contextos entre sitios. Los navegadores requieren secure activado y Same-Site establecido en none.
    |
    */

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

    /*
    |--------------------------------------------------------------------------
    | Serialización de sesiones
    |--------------------------------------------------------------------------
    |
    | Formato utilizado para guardar datos de sesión. JSON es el valor predeterminado.
    | El formato php permite almacenar objetos, pero puede facilitar ataques mediante cadenas de objetos si se filtra la clave de la aplicación.
    | Formatos compatibles json y php.
    |
    */

    'serialization' => 'json',

];
