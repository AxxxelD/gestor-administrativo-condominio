let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

export async function api(path, options = {}) {
    const multipart = options.body instanceof FormData;
    const response = await fetch(path, {
        credentials: 'same-origin',
        ...options,
        headers: {
            Accept: 'application/json',
            ...(multipart ? {} : { 'Content-Type': 'application/json' }),
            'X-CSRF-TOKEN': csrfToken,
            ...options.headers,
        },
        body: options.body === undefined || multipart ? options.body : JSON.stringify(options.body),
    }).catch(() => {
        throw new Error('No se pudo conectar con el servidor. Comprueba que el entorno de desarrollo esté iniciado e intenta nuevamente.');
    });

    const contentType = response.headers.get('content-type') ?? '';
    const data = contentType.includes('application/json') ? await response.json().catch(() => {
        throw new Error('El servidor devolvió una respuesta que no se pudo interpretar. Intenta nuevamente.');
    }) : {};

    if (data.csrf_token) {
        csrfToken = data.csrf_token;
    }

    if (!response.ok) {
        const error = new Error(data.message ?? 'No se pudo completar la operación. Intenta nuevamente.');
        error.status = response.status;
        error.errors = data.errors ?? {};
        throw error;
    }

    return data;
}

export function formatMoney(cents, currency = 'USD') {
    const number = new Intl.NumberFormat('es-VE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(cents ?? 0) / 100);

    return `${currency === 'USD' ? 'USD' : 'Bs'} ${number}`;
}

export function decimalAmount(cents) {
    return (Number(cents ?? 0) / 100).toFixed(2);
}

export function localDate() {
    const parts = new Intl.DateTimeFormat('es-VE', {
        timeZone: 'America/Caracas',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
    }).formatToParts(new Date());
    const part = (type) => parts.find((item) => item.type === type).value;

    return `${part('year')}-${part('month')}-${part('day')}`;
}

export function displayDate(date) {
    if (!date) return '—';

    return new Intl.DateTimeFormat('es-VE', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(`${String(date).slice(0, 10)}T12:00:00`));
}

export function displayMonth(month) {
    if (!month) return '—';

    return new Intl.DateTimeFormat('es-VE', {
        month: 'long',
        year: 'numeric',
    }).format(new Date(`${String(month).slice(0, 7)}-01T12:00:00`));
}
