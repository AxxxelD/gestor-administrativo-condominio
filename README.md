# Gestor administrativo de condominio

Aplicación local para el Sector C de Terrazas del Atlántico. Incluye comunidad y censo, usuarios y permisos, períodos de cobro, transferencias, gastos, bancos, fondos, documentos PDF y preparación de correos.

## Iniciar el proyecto

Abre una terminal en esta carpeta y ejecuta:

```powershell
npm run dev
```

Espera el mensaje LISTO y abre **http://127.0.0.1:8000**. Mantén la terminal abierta. Ctrl + C detiene los servidores iniciados por esa terminal. También puedes abrir `iniciar.bat`; el antiguo `start-dev.bat` llama al mismo iniciador.

Este comando comprueba MariaDB, inicia Laravel y Vite, y activa el trabajador de correos cuando hay un servicio de correo configurado. No borra la base, no vuelve a sembrar datos y no aplica migraciones automáticamente.

Para comprobar los servicios desde otra terminal:

```powershell
npm run comprobar
```

La aplicación usa el puerto 8000, Vite el 5173 y MariaDB el 3306. El puerto 5173 no es la dirección de la aplicación. Se utiliza IPv4 local explícito para evitar cambios de resolución de localhost al conectar una VPN. La interfaz y las fuentes no necesitan Internet. Consultar tasas externas o enviar correos sí requiere conexión. Si una política de VPN bloquea incluso conexiones locales, esa política debe revisarse en el cliente VPN; el proyecto no cambia sus opciones de seguridad.

## Qué hace cada herramienta

| Herramienta | Función en este proyecto |
| --- | --- |
| PHP 8.4 | Ejecuta la lógica del servidor. |
| Laravel 13 | Gestiona rutas, sesiones, permisos, validaciones, base de datos y operaciones financieras. |
| Artisan | Es la consola incluida en Laravel; ejecuta migraciones, pruebas, servidores y tareas. |
| Composer | Instala las bibliotecas de PHP. |
| MariaDB | Guarda los datos; se usa mediante el controlador compatible con MySQL. |
| Beekeeper Studio | Permite consultar visualmente esa base. |
| JavaScript y Vue 3 | Construyen las pantallas y sus formularios interactivos en el navegador. |
| Node.js y npm | Ejecutan las herramientas de desarrollo y gestionan sus paquetes. |
| Vite | Sirve y actualiza los recursos durante desarrollo; compila la versión distribuible. |
| CSS y Tailwind | Definen el estilo de las pantallas. |
| Dompdf | Genera los avisos, comprobantes y estados de cuenta en PDF. |
| PHPUnit y Pint | Comprueban el funcionamiento y uniforman el formato del código PHP. |
| Git | Conserva el historial del código. |

Laragon es una alternativa para reunir y administrar herramientas locales. Este proyecto ya dispone de PHP, MariaDB y Node instalados directamente. No es necesario migrar a Laragon para continuar.

Una acción en Vue envía una petición a Laravel. Laravel comprueba la sesión y los permisos, valida los datos, aplica las reglas y guarda en MariaDB. Devuelve una respuesta que Vue presenta en pantalla. Vite participa en el desarrollo de la interfaz, pero no sustituye a Laravel ni a la base de datos.

## Reglas financieras implementadas

- Cuentas y deudas en USD; transferencias bancarias reales en Bs con tasa, fuente y fecha de referencia guardadas.
- La deuda se genera al emitir manualmente el período. No se impone vencimiento ni recargo automático.
- Reparto entre los 90 TH activos, cuatro grupos de conceptos y reserva configurable, inicialmente 10 %.
- Gastos habituales editables, conceptos negativos, provisiones y ajuste explícito de redondeo.
- Abonos a las deudas más antiguas, excedentes a favor, pagos repartidos entre inmuebles y anulaciones trazables.
- Registrar un concepto por cobrar y registrar su pago bancario son operaciones distintas.
- Los documentos emitidos conservan una copia de sus datos. La reserva cobrada se distingue de la facturada.

## Demostración y correo

La base de demostración contiene personas, inmuebles, movimientos y tasas ficticias. No son datos operativos. Las cuentas de demostración son `admin@condominio.test`, `super@condominio.test` y `vecino@condominio.test`; la contraseña aleatoria se entrega al ejecutar el sembrador por primera vez y no se guarda en este documento.

Con `MAIL_MAILER=log` los correos quedan en vista previa. Para entregarlos debe configurarse un proveedor en `.env`, incluyendo remitente y credenciales. No publiques `.env` ni lo subas a Git. La emisión mensual sigue siendo una decisión de la administradora; desde un período emitido se preparan los avisos individuales.

## Desarrollo y comprobaciones

```powershell
php artisan test --compact
npm run build
php artisan migrate --no-interaction
```

Las pruebas usan SQLite en memoria, separada de la base local. `migrate` aplica las migraciones pendientes conservando los registros existentes. No utilices `migrate:fresh` sobre datos que quieras conservar.

`npm run dev:assets` inicia únicamente Vite. `composer run dev` utiliza el mismo inicio completo que `npm run dev`.

La interfaz principal está en `resources/js/Aplicacion.vue`; los diálogos en `resources/js/components/DialogoFormulario.vue`; las traducciones del servidor en `lang/es`. Se mantienen los nombres y claves técnicas de Laravel y de la base de datos para conservar sus convenciones y las migraciones existentes.
