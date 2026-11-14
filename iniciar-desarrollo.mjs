import { spawn, spawnSync } from 'node:child_process';
import { existsSync, readFileSync, readdirSync, openSync, closeSync, rmSync } from 'node:fs';
import { get } from 'node:http';
import { createConnection } from 'node:net';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const carpeta = dirname(fileURLToPath(import.meta.url));
process.chdir(carpeta);
const direccion = '127.0.0.1';
const url = `http://${direccion}:8000`;
const procesos = [];
let cerrando = false;

// Solo se omite el proxy para conexiones a esta misma computadora.
const entorno = {
    ...process.env,
    NO_PROXY: [process.env.NO_PROXY, '127.0.0.1', 'localhost', '::1'].filter(Boolean).join(','),
};
entorno.no_proxy = entorno.NO_PROXY;

function configuracion() {
    if (!existsSync('.env')) throw new Error('Falta .env. Copia .env.example y configura la base de datos.');
    return Object.fromEntries(readFileSync('.env', 'utf8').split(/\r?\n/)
        .filter((linea) => /^[A-Z_]+=/.test(linea))
        .map((linea) => {
            const corte = linea.indexOf('=');
            return [linea.slice(0, corte), linea.slice(corte + 1).trim().replace(/^(["'])(.*)\1$/, '$2')];
        }));
}

function puertoAbierto(puerto, servidor = direccion) {
    return new Promise((resolver) => {
        const conexion = createConnection({ host: servidor, port: Number(puerto) });
        const terminar = (abierto) => { conexion.destroy(); resolver(abierto); };
        conexion.setTimeout(1000);
        conexion.once('connect', () => terminar(true));
        conexion.once('error', () => terminar(false));
        conexion.once('timeout', () => terminar(false));
    });
}

function pagina(direccionPagina) {
    return new Promise((resolver) => {
        const solicitud = get(direccionPagina, { timeout: 3000 }, (respuesta) => {
            let texto = '';
            respuesta.setEncoding('utf8');
            respuesta.on('data', (fragmento) => { texto += fragmento; });
            respuesta.on('end', () => resolver({ estado: respuesta.statusCode, texto }));
            respuesta.on('error', () => resolver({ estado: 0, texto: '' }));
        });
        solicitud.on('error', () => resolver({ estado: 0, texto: '' }));
        solicitud.on('timeout', () => solicitud.destroy());
    });
}

async function esperar(comprobar, descripcion) {
    for (let intento = 0; intento < 40; intento++) {
        if (await comprobar()) return;
        await new Promise((resolver) => setTimeout(resolver, 250));
    }
    throw new Error(`No se pudo iniciar ${descripcion}. Revisa el error mostrado arriba.`);
}

function buscarPhp() {
    const candidatos = [process.env.PHP_BIN, 'php'].filter(Boolean);
    if (process.platform === 'win32' && process.env.LOCALAPPDATA) {
        const paquetes = join(process.env.LOCALAPPDATA, 'Microsoft', 'WinGet', 'Packages');
        if (existsSync(paquetes)) {
            for (const nombre of readdirSync(paquetes).filter((nombre) => nombre.startsWith('PHP.PHP.8.')).sort().reverse()) {
                candidatos.push(join(paquetes, nombre, 'php.exe'));
            }
        }
    }
    for (const candidato of candidatos) {
        if (spawnSync(candidato, ['-v'], { windowsHide: true, stdio: 'ignore', timeout: 5000 }).status === 0) return candidato;
    }
    throw new Error('No se encontró PHP. Reabre la terminal o indica su ruta en la variable PHP_BIN.');
}

function ejecutar(comando, argumentos, nombre) {
    console.log(`[${nombre}] Iniciando…`);
    const proceso = spawn(comando, argumentos, { cwd: carpeta, env: entorno, windowsHide: true, stdio: 'inherit' });
    procesos.push(proceso);
    proceso.on('error', (error) => { console.error(`[${nombre}] ${error.message}`); apagar(1); });
    proceso.on('exit', (codigo) => {
        if (!cerrando) {
            console.error(`[${nombre}] Se detuvo. Vuelve a ejecutar npm run dev para iniciar todo.`);
            apagar(codigo || 1);
        }
    });
    return proceso;
}

function apagar(codigo = 0) {
    if (cerrando) return;
    cerrando = true;
    for (const proceso of procesos) {
        if (!proceso.pid || proceso.exitCode !== null) continue;
        if (process.platform === 'win32') {
            spawnSync('taskkill', ['/PID', String(proceso.pid), '/T', '/F'], { windowsHide: true, stdio: 'ignore' });
        } else {
            proceso.kill('SIGTERM');
        }
    }
    if (procesos.some((proceso) => proceso.esVite)) rmSync(join(carpeta, 'public', 'hot'), { force: true });
    console.log('\nServidores de esta terminal detenidos. La base de datos conserva tus datos.');
    process.exit(codigo);
}

async function iniciarBase(datos) {
    if (!['mysql', 'mariadb'].includes(datos.DB_CONNECTION)) return;
    const servidor = datos.DB_HOST || direccion;
    const puerto = Number(datos.DB_PORT || 3306);
    if (await puertoAbierto(puerto, servidor)) {
        console.log('[Base de datos] Disponible.');
        return;
    }
    const instalacion = join(process.env.LOCALAPPDATA || '', 'Programs', 'mariadb');
    const ejecutable = join(instalacion, 'bin', 'mariadbd.exe');
    const ajustes = join(instalacion, 'data', 'my.ini');
    if (process.platform !== 'win32' || !['127.0.0.1', 'localhost'].includes(servidor) || !existsSync(ejecutable) || !existsSync(ajustes)) {
        throw new Error('La base de datos no está disponible. Inicia tu servicio MySQL/MariaDB y revisa DB_HOST y DB_PORT.');
    }
    console.log('[Base de datos] Iniciando MariaDB local…');
    const archivoRegistro = openSync(join(carpeta, 'storage', 'logs', 'mariadb-local.log'), 'a');
    const base = spawn(ejecutable, [`--defaults-file=${ajustes}`, `--bind-address=${direccion}`, `--port=${puerto}`], {
        windowsHide: true, detached: true, stdio: ['ignore', archivoRegistro, archivoRegistro],
    });
    base.on('error', (error) => console.error(`[Base de datos] ${error.message}`));
    base.unref();
    closeSync(archivoRegistro);
    await esperar(() => puertoAbierto(puerto, servidor), 'MariaDB (storage/logs/mariadb-local.log)');
}

async function comprobarEstado(datos) {
    const aplicacion = await pagina(url);
    const recursos = await pagina('http://127.0.0.1:5173/@vite/client');
    const base = !['mysql', 'mariadb'].includes(datos.DB_CONNECTION) || await puertoAbierto(datos.DB_PORT || 3306, datos.DB_HOST || direccion);
    const correcta = aplicacion.estado === 200 && aplicacion.texto.includes('Gestión del condominio');
    const vite = recursos.estado === 200 && recursos.texto.includes('createHotContext');
    console.log(`Base de datos: ${base ? 'disponible' : 'apagada'}`);
    console.log(`Aplicación en ${url}: ${correcta ? 'disponible' : 'apagada o con error'}`);
    console.log(`Vite en http://127.0.0.1:5173: ${vite ? 'disponible' : 'apagado'}`);
    console.log('Estas conexiones usan la propia computadora, sin DNS ni proxy externo.');
    process.exitCode = base && correcta && vite ? 0 : 1;
}

async function iniciar() {
    const datos = configuracion();
    if (process.argv.includes('--comprobar')) return comprobarEstado(datos);
    if (datos.APP_ENV !== 'local') throw new Error('Este iniciador es para desarrollo. APP_ENV debe ser local.');
    if (!existsSync('vendor/autoload.php')) throw new Error('Ejecuta composer install antes de iniciar.');
    if (!existsSync('node_modules/vite/bin/vite.js')) throw new Error('Ejecuta npm install antes de iniciar.');
    const php = buscarPhp();
    await iniciarBase(datos);
    const limpieza = spawnSync(php, ['artisan', 'config:clear', '--no-interaction'], { cwd: carpeta, env: entorno, windowsHide: true, stdio: 'inherit' });
    if (limpieza.status !== 0) throw new Error('No se pudo cargar Laravel. Revisa el error anterior.');
    if (await puertoAbierto(8000)) {
        const respuesta = await pagina(url);
        if (!respuesta.texto.includes('Gestión del condominio')) throw new Error('El puerto 8000 está ocupado por otro programa. Cierra ese servidor antes de continuar.');
        console.log('[Laravel] Se utiliza el servidor que ya estaba abierto.');
    } else {
        ejecutar(php, ['artisan', 'serve', '--host=127.0.0.1', '--port=8000', '--tries=1', '--no-reload'], 'Laravel');
    }
    if (await puertoAbierto(5173)) {
        const recurso = await pagina('http://127.0.0.1:5173/resources/js/app.js');
        if (!recurso.texto.includes('Aplicacion.vue')) throw new Error('El puerto 5173 está ocupado por otro proyecto. Cierra ese Vite y vuelve a iniciar.');
        console.log('[Vite] Se utiliza el servidor que ya estaba abierto.');
    } else {
        rmSync(join(carpeta, 'public', 'hot'), { force: true });
        ejecutar(process.execPath, ['node_modules/vite/bin/vite.js'], 'Vite').esVite = true;
    }
    await esperar(async () => (await pagina(`${url}/up`)).estado === 200, 'Laravel');
    await esperar(async () => (await pagina('http://127.0.0.1:5173/@vite/client')).estado === 200, 'Vite');
    if (!['log', 'array'].includes(datos.MAIL_MAILER || 'log')) {
        ejecutar(php, ['artisan', 'queue:work', '--sleep=1', '--tries=3', '--timeout=60'], 'Correos');
    }
    console.log(`\nLISTO. Abre ${url}`);
    console.log('Mantén esta terminal abierta. Ctrl + C detiene los servidores iniciados aquí.');
    console.log('Para revisar la conexión en otra terminal: npm run comprobar\n');
}

process.on('SIGINT', () => apagar());
process.on('SIGTERM', () => apagar());
iniciar().catch((error) => { console.error(`\nNo se pudo iniciar: ${error.message}`); apagar(1); });
