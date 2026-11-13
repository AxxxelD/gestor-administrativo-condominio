<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue';
import {
    ArrowDownLeft, ArrowRight, ArrowUpRight, Banknote, Building2, CalendarDays,
    Check, ChevronDown, CircleHelp, Download, FileText, Home, Leaf, LoaderCircle,
    LogOut, Mail, Menu, Pencil, Plus, RefreshCw, Search, Send, ShieldCheck,
    Trash2, Users, Wallet, X,
} from '@lucide/vue';
import FormDialog from './components/FormDialog.vue';
import { api, decimalAmount, displayDate, displayMonth, formatMoney, localDate } from './api';

const navigation = [
    { id: 'home', label: 'Inicio', icon: Home },
    { id: 'community', label: 'Comunidad', icon: Building2, permission: 'community.view' },
    { id: 'periods', label: 'Períodos', icon: CalendarDays, permission: 'finance.view' },
    { id: 'payments', label: 'Pagos', icon: ArrowDownLeft, permission: 'payments.view' },
    { id: 'treasury', label: 'Gastos y bancos', icon: Wallet, permission: 'treasury.view' },
    { id: 'documents', label: 'Documentos', icon: FileText, permission: 'reports.view' },
    { id: 'users', label: 'Usuarios', icon: Users, permission: 'users.manage' },
];
const categories = {
    sector_fixed: 'Gastos fijos del Sector C',
    sector_variable: 'Gastos variables del Sector C',
    common_fixed: 'Gastos comunes fijos',
    common_variable: 'Gastos comunes variables',
};
const occupancyLabels = { occupied: 'Ocupado', rented: 'Alquilado', vacant: 'Desocupado' };
const relationshipLabels = { owner: 'Propietario', tenant: 'Inquilino', resident: 'Residente' };
const roleLabels = { superuser: 'Superusuario', admin: 'Administrador', user: 'Usuario' };
const fundLabels = { operating: 'Operativo', reserve: 'Reserva', provision: 'Provisión' };
const permissionLabels = {
    'community.view': 'Consultar comunidad', 'community.manage': 'Gestionar comunidad',
    'finance.view': 'Consultar períodos', 'finance.manage': 'Gestionar períodos y cuotas',
    'payments.view': 'Consultar pagos', 'payments.manage': 'Registrar y anular pagos',
    'treasury.view': 'Consultar gastos y bancos', 'treasury.manage': 'Gestionar gastos y fondos',
    'reports.view': 'Consultar documentos', 'communications.manage': 'Enviar correos',
    'users.manage': 'Administrar usuarios', 'settings.manage': 'Modificar configuración',
};

const user = ref(null);
const loading = ref(true);
const refreshing = ref(false);
const busy = ref(false);
const mobileMenu = ref(false);
const page = ref('home');
const communityTab = ref('properties');
const treasuryTab = ref('expenses');
const documentsTab = ref('charges');
const search = ref('');
const selectedPeriodId = ref(null);
const error = ref('');
const notice = ref('');
const dialog = ref(null);
const dialogError = ref('');
const fieldErrors = ref({});
const form = reactive({});
const login = reactive({ email: '', password: '', remember: false });
const accounts = ref([]);
const availablePermissions = ref(Object.keys(permissionLabels));
const statement = ref(null);
const workspace = reactive({
    dashboard: {}, properties: [], people: [], occupancies: [], periods: [], items: [],
    banks: [], payments: [], expenses: [], templates: [], rates: [], settings: {},
    charges: [], deliveries: [],
});

const can = (permission) => !permission || user.value?.capabilities?.includes(permission);
const visibleNavigation = computed(() => navigation.filter((item) => can(item.permission)));
const currentNavigation = computed(() => navigation.find((item) => item.id === page.value) ?? navigation[0]);
const selectedPeriod = computed(() => workspace.periods.find((period) => period.id === Number(selectedPeriodId.value)));
const periodItems = computed(() => workspace.items.filter((item) => item.financial_period_id === selectedPeriod.value?.id));
const activeOccupancies = computed(() => workspace.occupancies.filter((occupancy) => !occupancy.ends_on));
const rateEquivalentCents = computed(() => {
    if (!(Number(form.rate) > 0)) return 0;
    return Math.round(Number(form.amount_bs || 0) / Number(form.rate) * 100);
});
const splitCents = computed(() => (form.splits ?? []).reduce((total, split) => total + Math.round(Number(split.amount_usd || 0) * 100), 0));
const filteredProperties = computed(() => workspace.properties.filter((property) => {
    const related = activeOccupancies.value.filter((occupancy) => occupancy.property_id === property.id);
    const names = related.map((occupancy) => personName(occupancy.person_id)).join(' ');
    return `${property.code} ${names}`.toLowerCase().includes(search.value.toLowerCase());
}));
const filteredPeople = computed(() => workspace.people.filter((person) => `${person.name} ${person.identity_number} ${person.email}`.toLowerCase().includes(search.value.toLowerCase())));
const recentMovements = computed(() => [
    ...workspace.payments.map((payment) => ({ ...payment, type: 'payment', label: `Transferencia ${payment.reference}`, direction: 'in' })),
    ...workspace.expenses.map((expense) => ({ ...expense, type: 'expense', label: expense.description, direction: 'out' })),
].sort((a, b) => String(b.paid_on).localeCompare(String(a.paid_on)) || b.id - a.id).slice(0, 6));
const maximumCashFlow = computed(() => Math.max(Number(workspace.dashboard.collected_month_bs_cents || 0), Number(workspace.dashboard.spent_month_bs_cents || 0), 1));
const currentDateLabel = new Intl.DateTimeFormat('es-VE', { day: 'numeric', month: 'long', year: 'numeric', timeZone: 'America/Caracas' }).format(new Date());

function personName(id) {
    return workspace.people.find((person) => person.id === Number(id))?.name ?? 'Sin asignar';
}

function propertyCode(id) {
    return workspace.properties.find((property) => property.id === Number(id))?.code ?? '—';
}

function bankName(id) {
    return workspace.banks.find((bank) => bank.id === Number(id))?.name ?? '—';
}

function occupantsFor(propertyId, relationship) {
    return activeOccupancies.value
        .filter((occupancy) => occupancy.property_id === propertyId && (!relationship || occupancy.relationship === relationship))
        .map((occupancy) => personName(occupancy.person_id)).join(', ') || 'Sin asignar';
}

function balanceFor(property) {
    return Number(property.balance_usd_cents ?? property.debt_usd_cents ?? 0);
}

function setForm(values) {
    Object.keys(form).forEach((key) => delete form[key]);
    Object.assign(form, values);
}

function openDialog(kind, title, path, values, options = {}) {
    dialogError.value = '';
    fieldErrors.value = {};
    setForm(values);
    dialog.value = { kind, title, path, method: 'POST', ...options };
}

function openProperty(property = null) {
    openDialog('property', property ? 'Editar inmueble' : 'Agregar inmueble', `/api/properties${property ? `/${property.id}` : ''}`, {
        code: property?.code ?? '', occupancy_status: property?.occupancy_status ?? 'occupied', active: property?.active ?? true,
    }, { method: property ? 'PUT' : 'POST' });
}

function openPerson(person = null) {
    openDialog('person', person ? 'Editar persona' : 'Agregar persona', `/api/people${person ? `/${person.id}` : ''}`, {
        name: person?.name ?? '', identity_number: person?.identity_number ?? '', email: person?.email ?? '', phone: person?.phone ?? '',
    }, { method: person ? 'PUT' : 'POST' });
}

function openOccupancy(occupancy = null) {
    openDialog('occupancy', occupancy ? 'Editar relación con inmueble' : 'Vincular persona e inmueble', `/api/occupancies${occupancy ? `/${occupancy.id}` : ''}`, {
        property_id: occupancy?.property_id ?? '', person_id: occupancy?.person_id ?? '', relationship: occupancy?.relationship ?? 'owner',
        starts_on: occupancy?.starts_on?.slice(0, 10) ?? localDate(), ends_on: occupancy?.ends_on?.slice(0, 10) ?? '',
    }, { method: occupancy ? 'PUT' : 'POST' });
}

function openOpening(property) {
    openDialog('opening', `Saldo inicial · ${property.code}`, `/api/properties/${property.id}/opening`, { amount_usd: '', date: localDate() });
}

function openPeriod() {
    openDialog('period', 'Preparar un nuevo período', '/api/periods', { month: localDate().slice(0, 7), reserve_basis_points: 1000 });
}

function openItem(item = null) {
    openDialog('item', item ? 'Editar concepto del período' : 'Agregar concepto al período', item ? `/api/items/${item.id}` : `/api/periods/${selectedPeriod.value.id}/items`, {
        description: item?.description ?? '', category: item?.category ?? 'sector_fixed', amount_usd: item ? decimalAmount(item.amount_usd_cents) : '', is_provision: item?.is_provision ?? false,
    }, { method: item ? 'PUT' : 'POST' });
}

function openTemplate(template = null) {
    openDialog('template', template ? 'Editar gasto habitual' : 'Agregar gasto habitual', `/api/templates${template ? `/${template.id}` : ''}`, {
        description: template?.description ?? '', category: template?.category ?? 'sector_fixed', amount_usd: template ? decimalAmount(template.amount_usd_cents) : '',
        recurring: template?.recurring ?? true, active: template?.active ?? true, is_provision: template?.is_provision ?? false,
    }, { method: template ? 'PUT' : 'POST' });
}

function openBank() {
    openDialog('bank', 'Agregar cuenta bancaria', '/api/banks', { name: '', opening_balance_bs: '0.00' });
}

function openFund() {
    openDialog('fund', 'Registrar apertura de fondo', '/api/funds/opening', { fund: 'reserve', amount_usd: '', date: localDate() });
}

function paymentDefaults() {
    const storedRate = workspace.rates.find((rate) => rate.date?.slice(0, 10) === localDate());
    return {
        bank_account_id: workspace.banks.find((bank) => bank.active)?.id ?? '', paid_on: localDate(), amount_bs: '',
        rate: storedRate ? Number(storedRate.rate_micros) / 1000000 : '', rate_date: localDate(),
        rate_source: storedRate?.source ?? '', reference: '', idempotency_key: crypto.randomUUID(),
    };
}

function openPayment(propertyId = '') {
    openDialog('payment', 'Registrar transferencia recibida', '/api/payments', {
        ...paymentDefaults(), splits: [{ property_id: propertyId, amount_usd: '' }],
    }, { wide: true });
}

function openExpense() {
    openDialog('expense', 'Registrar pago de un gasto', '/api/expenses', {
        ...paymentDefaults(), description: '', fund: 'operating', period_item_id: '',
    }, { wide: true });
}

function openVoid(kind, record) {
    openDialog('void', 'Anular operación', `/api/${kind}/${record.id}/void`, { reason: '' }, { submitLabel: 'Confirmar anulación' });
}

function openCommunication() {
    openDialog('communication', 'Preparar correo', '/api/communications', {
        subject: '', body: '', property_ids: [], kind: 'announcement', period_id: '',
    }, { wide: true, submitLabel: 'Preparar envío' });
}

function openUser(account = null) {
    openDialog('user', account ? 'Editar usuario' : 'Agregar usuario', `/api/users${account ? `/${account.id}` : ''}`, {
        name: account?.name ?? '', email: account?.email ?? '', password: '', role: account?.role ?? 'user', active: account?.active ?? true,
        permissions: [...(account?.permissions ?? [])], property_ids: [...(account?.property_ids ?? [])],
    }, { method: account ? 'PUT' : 'POST', wide: true });
}

async function loadWorkspace() {
    const data = await api('/api/workspace');
    Object.assign(workspace, data);
    if (data.user) user.value = data.user;
    if (!selectedPeriodId.value && workspace.periods.length) selectedPeriodId.value = workspace.periods[0].id;
}

async function loadUsers() {
    const data = await api('/api/users');
    accounts.value = data.users ?? [];
    availablePermissions.value = data.permissions ?? Object.keys(permissionLabels);
}

async function refresh() {
    refreshing.value = true;
    error.value = '';
    try {
        await loadWorkspace();
        if (page.value === 'users') await loadUsers();
    } catch (failure) {
        error.value = failure.message;
        if (failure.status === 401) user.value = null;
    } finally {
        refreshing.value = false;
    }
}

async function navigate(id) {
    page.value = id;
    search.value = '';
    mobileMenu.value = false;
    error.value = '';
    if (id === 'users') {
        try { await loadUsers(); } catch (failure) { error.value = failure.message; }
    }
}

async function signIn() {
    busy.value = true;
    error.value = '';
    try {
        const data = await api('/api/login', { method: 'POST', body: login });
        user.value = data.user;
        login.password = '';
        await loadWorkspace();
    } catch (failure) {
        error.value = failure.message;
    } finally {
        busy.value = false;
    }
}

async function signOut() {
    try {
        await api('/api/logout', { method: 'POST' });
        user.value = null;
        page.value = 'home';
        error.value = '';
        notice.value = '';
    } catch (failure) {
        error.value = failure.message;
    }
}

async function saveDialog() {
    busy.value = true;
    dialogError.value = '';
    fieldErrors.value = {};
    const payload = JSON.parse(JSON.stringify(form));
    if (dialog.value.kind === 'user' && !payload.password) delete payload.password;
    if (payload.ends_on === '') payload.ends_on = null;
    if (payload.period_item_id === '') delete payload.period_item_id;
    if (payload.period_id === '') delete payload.period_id;
    try {
        const result = await api(dialog.value.path, { method: dialog.value.method, body: payload });
        notice.value = result.message ?? 'La información se guardó correctamente.';
        dialog.value = null;
        await loadWorkspace();
        if (page.value === 'users') await loadUsers();
    } catch (failure) {
        dialogError.value = failure.message;
        fieldErrors.value = failure.errors;
    } finally {
        busy.value = false;
    }
}

async function runAction(path, message, method = 'POST') {
    if (message && !window.confirm(message)) return;
    busy.value = true;
    error.value = '';
    try {
        const result = await api(path, { method });
        notice.value = result.message ?? 'Operación completada.';
        await loadWorkspace();
    } catch (failure) {
        error.value = failure.message;
    } finally {
        busy.value = false;
    }
}

async function fetchRate() {
    dialogError.value = '';
    try {
        const data = await api(`/api/rates/${form.paid_on}`);
        const rate = data.rate ?? data;
        form.rate = Number(rate.rate_micros) / 1000000;
        form.rate_date = form.paid_on;
        form.rate_source = rate.source ?? 'BCV';
    } catch (failure) {
        dialogError.value = `${failure.message} Puedes registrar la tasa y su fuente en este formulario.`;
    }
}

async function viewStatement(property) {
    error.value = '';
    try {
        statement.value = await api(`/api/properties/${property.id}/statement`);
        openDialog('statement', `Estado de cuenta · ${property.code}`, '', { property_id: property.id }, { readonly: true, wide: true });
    } catch (failure) {
        error.value = failure.message;
    }
}

watch(() => [form.amount_bs, form.rate], () => {
    if (dialog.value?.kind === 'payment' && form.splits?.length === 1) {
        form.splits[0].amount_usd = decimalAmount(rateEquivalentCents.value);
    }
});

watch(() => form.paid_on, (date) => {
    if (date && ['payment', 'expense'].includes(dialog.value?.kind)) form.rate_date = date;
});

onMounted(async () => {
    try {
        const data = await api('/api/me');
        user.value = data.user;
        await loadWorkspace();
    } catch (failure) {
        if (failure.status !== 401) error.value = failure.message;
    } finally {
        loading.value = false;
    }
});
</script>

<template>
    <div v-if="loading" class="initial-loading" role="status"><LoaderCircle class="spin" :size="28" /><span>Cargando el condominio…</span></div>

    <main v-else-if="!user" class="login-screen">
        <section class="login-intro">
            <a class="brand" href="/" aria-label="Terrazas del Atlántico"><span class="brand-symbol"><Building2 :size="25" /></span><span>Terrazas<br><small>DEL ATLÁNTICO</small></span></a>
            <div class="login-message"><span class="eyebrow">SECTOR C</span><h1>Una comunidad.<br>Todo en orden.</h1><p>La administración de tu condominio, en un solo lugar.</p><div class="login-illustration" aria-hidden="true"><div class="building building-one"></div><div class="building building-two"></div><div class="building building-three"></div><div class="ground"></div></div></div>
            <p class="login-footer">Gestión del condominio · Terrazas del Atlántico</p>
        </section>
        <section class="login-form-panel">
            <form class="login-form" @submit.prevent="signIn">
                <span class="login-icon"><Leaf :size="25" /></span><h2>Bienvenido de nuevo</h2><p class="muted">Ingresa con tu cuenta para continuar.</p>
                <div v-if="error" class="alert error" role="alert">{{ error }}</div>
                <label>Correo electrónico<input v-model="login.email" type="email" autocomplete="username" required placeholder="nombre@correo.com" /></label>
                <label>Contraseña<input v-model="login.password" type="password" autocomplete="current-password" required placeholder="Ingresa tu contraseña" /></label>
                <label class="checkbox-label"><input v-model="login.remember" type="checkbox" />Mantener la sesión iniciada</label>
                <button class="button primary login-submit" :disabled="busy"><LoaderCircle v-if="busy" class="spin" :size="18" />{{ busy ? 'Ingresando…' : 'Iniciar sesión' }}<ArrowRight v-if="!busy" :size="18" /></button>
                <p class="login-help"><ShieldCheck :size="16" />El acceso es asignado por la administración.</p>
            </form>
        </section>
    </main>

    <div v-else class="app-shell">
        <button v-if="mobileMenu" class="menu-overlay" aria-label="Cerrar menú" @click="mobileMenu = false"></button>
        <aside class="sidebar" :class="{ 'is-open': mobileMenu }">
            <a href="#" class="brand" @click.prevent="navigate('home')"><span class="brand-symbol"><Building2 :size="24" /></span><span>Terrazas<br><small>DEL ATLÁNTICO</small></span></a>
            <div class="sidebar-context"><span class="context-dot"></span>Administración · Sector C</div>
            <nav aria-label="Navegación principal"><button v-for="item in visibleNavigation" :key="item.id" class="nav-item" :class="{ active: page === item.id }" :aria-current="page === item.id ? 'page' : undefined" @click="navigate(item.id)"><component :is="item.icon" :size="19" /><span>{{ item.label }}</span><span v-if="item.id === 'periods' && workspace.periods.some(period => period.status === 'draft')" class="nav-dot" aria-label="Hay períodos en borrador"></span></button></nav>
            <div class="sidebar-bottom"><div class="sidebar-note"><Leaf :size="18" /><span>Transparencia para<br>nuestra comunidad.</span></div><div class="account"><span class="avatar">{{ user.name.slice(0, 1) }}</span><div><strong>{{ user.name }}</strong><small>{{ roleLabels[user.role] }}</small></div><button class="logout-button" aria-label="Cerrar sesión" @click="signOut"><LogOut :size="18" /></button></div></div>
        </aside>

        <div class="main-shell">
            <header class="topbar"><div class="topbar-location"><button class="icon-button mobile-menu-button" aria-label="Abrir menú" @click="mobileMenu = true"><Menu :size="22" /></button><span>Sector C</span><span class="breadcrumb-divider">/</span><strong>{{ currentNavigation.label }}</strong></div><div class="topbar-actions"><span class="today">{{ currentDateLabel }}</span><button class="icon-button" aria-label="Actualizar datos" :disabled="refreshing" @click="refresh"><RefreshCw :size="18" :class="{ spin: refreshing }" /></button></div></header>
            <main class="workspace">
                <div v-if="error" class="alert error" role="alert">{{ error }}<button class="icon-button" aria-label="Cerrar aviso" @click="error = ''"><X :size="16" /></button></div>
                <div v-if="notice" class="alert success" role="status"><Check :size="18" />{{ notice }}<button class="icon-button" aria-label="Cerrar confirmación" @click="notice = ''"><X :size="16" /></button></div>

                <template v-if="page === 'home'">
                    <div class="page-heading"><div><p class="eyebrow">TU CONDOMINIO, AL DÍA</p><h1>Resumen del condominio</h1><p class="muted">Una mirada a las cuentas y la actividad de la comunidad.</p></div><button v-if="can('payments.manage')" class="button primary" @click="openPayment()"><Plus :size="18" />Registrar pago</button></div>
                    <section class="stats-grid" aria-label="Resumen financiero">
                        <article class="stat-card accent"><div class="stat-top"><span>Dinero en bancos</span><Wallet :size="19" /></div><strong>{{ formatMoney(workspace.dashboard.bank_bs_cents, 'VES') }}</strong><p>Saldo registrado en las cuentas</p></article>
                        <article class="stat-card"><div class="stat-top"><span>Por cobrar</span><ArrowDownLeft :size="19" /></div><strong>{{ formatMoney(workspace.dashboard.receivable_usd_cents) }}</strong><p>Deuda pendiente de los inmuebles</p></article>
                        <article class="stat-card"><div class="stat-top"><span>Fondo de reserva</span><ShieldCheck :size="19" /></div><strong>{{ formatMoney(workspace.dashboard.reserve_usd_cents) }}</strong><p>Reserva cobrada disponible</p></article>
                        <article class="stat-card"><div class="stat-top"><span>Saldos a favor</span><Banknote :size="19" /></div><strong>{{ formatMoney(workspace.dashboard.credits_usd_cents) }}</strong><p>Anticipos de los propietarios</p></article>
                    </section>
                    <div class="dashboard-columns"><section class="panel"><div class="panel-heading"><h2>Movimiento del mes</h2><span class="muted small">{{ displayMonth(localDate()) }}</span></div><div class="cash-flow"><div class="cash-row"><div><span class="legend-dot incoming"></span>Cobrado<strong>{{ formatMoney(workspace.dashboard.collected_month_bs_cents, 'VES') }}</strong></div><div class="bar-track"><div class="bar incoming" :style="{ width: `${Number(workspace.dashboard.collected_month_bs_cents || 0) / maximumCashFlow * 100}%` }"></div></div></div><div class="cash-row"><div><span class="legend-dot outgoing"></span>Pagado<strong>{{ formatMoney(workspace.dashboard.spent_month_bs_cents, 'VES') }}</strong></div><div class="bar-track"><div class="bar outgoing" :style="{ width: `${Number(workspace.dashboard.spent_month_bs_cents || 0) / maximumCashFlow * 100}%` }"></div></div></div></div><p class="panel-footnote">Transferencias y gastos confirmados durante el mes.</p></section><section class="panel"><div class="panel-heading"><h2>La comunidad</h2><Building2 :size="19" class="muted" /></div><div class="community-summary"><div><strong>{{ workspace.properties.filter(property => property.active).length }}</strong><span>Inmuebles activos</span></div><div><strong>{{ workspace.people.length }}</strong><span>Personas registradas</span></div><div><strong>{{ workspace.properties.filter(property => property.occupancy_status === 'rented').length }}</strong><span>Inmuebles alquilados</span></div></div><button v-if="can('community.view')" class="text-button" @click="navigate('community')">Consultar comunidad<ArrowRight :size="16" /></button></section></div>
                    <section class="panel"><div class="panel-heading"><h2>Últimos movimientos</h2><button v-if="can('treasury.view')" class="text-button" @click="navigate('treasury')">Ver movimientos<ArrowRight :size="16" /></button></div><div v-if="recentMovements.length" class="table-scroll"><table><thead><tr><th>Concepto</th><th>Fecha</th><th>Cuenta</th><th>Estado</th><th class="numeric">Monto</th></tr></thead><tbody><tr v-for="movement in recentMovements" :key="`${movement.type}-${movement.id}`"><td><div class="movement-description"><span class="movement-icon" :class="movement.direction"><ArrowDownLeft v-if="movement.direction === 'in'" :size="17" /><ArrowUpRight v-else :size="17" /></span>{{ movement.label }}</div></td><td>{{ displayDate(movement.paid_on) }}</td><td>{{ bankName(movement.bank_account_id) }}</td><td><span class="badge" :class="movement.status === 'void' || movement.status === 'voided' ? 'neutral' : 'green'">{{ ['void', 'voided'].includes(movement.status) ? 'Anulado' : 'Confirmado' }}</span></td><td class="numeric">{{ movement.direction === 'out' ? '−' : '+' }} {{ formatMoney(movement.amount_bs_cents, 'VES') }}</td></tr></tbody></table></div><div v-else class="empty-state"><Wallet :size="30" /><h3>Todavía no hay movimientos</h3><p>Los pagos y gastos registrados aparecerán aquí.</p></div></section>
                </template>

                <template v-if="page === 'community'">
                    <div class="page-heading"><div><p class="eyebrow">SECTOR C</p><h1>Comunidad</h1><p class="muted">Inmuebles, propietarios y personas que habitan el sector.</p></div><div v-if="can('community.manage')" class="button-row"><button v-if="communityTab === 'properties'" class="button primary" @click="openProperty()"><Plus :size="18" />Agregar inmueble</button><button v-if="communityTab === 'people'" class="button primary" @click="openPerson()"><Plus :size="18" />Agregar persona</button><button v-if="communityTab === 'census'" class="button primary" @click="openOccupancy()"><Plus :size="18" />Vincular persona</button></div></div>
                    <div class="section-toolbar"><div class="tabs" aria-label="Secciones de comunidad"><button :class="{ active: communityTab === 'properties' }" @click="communityTab = 'properties'">Inmuebles <span>{{ workspace.properties.length }}</span></button><button :class="{ active: communityTab === 'people' }" @click="communityTab = 'people'">Personas</button><button :class="{ active: communityTab === 'census' }" @click="communityTab = 'census'">Censo e historial</button></div><label v-if="communityTab !== 'census'" class="search-input"><Search :size="17" /><input v-model="search" type="search" :placeholder="communityTab === 'properties' ? 'Buscar TH o persona' : 'Buscar nombre o identificación'" aria-label="Buscar en la comunidad" /></label></div>
                    <section class="panel">
                        <div v-if="communityTab === 'properties'" class="table-scroll"><table><thead><tr><th>Inmueble</th><th>Propietario</th><th>Ocupación</th><th>Estado</th><th class="numeric">Saldo USD</th><th><span class="sr-only">Acciones</span></th></tr></thead><tbody><tr v-for="property in filteredProperties" :key="property.id"><td><strong class="property-code">{{ property.code }}</strong></td><td>{{ occupantsFor(property.id, 'owner') }}</td><td>{{ occupancyLabels[property.occupancy_status] ?? property.occupancy_status }}</td><td><span class="badge" :class="property.active ? 'green' : 'neutral'">{{ property.active ? 'Activo' : 'Inactivo' }}</span></td><td class="numeric"><span :class="balanceFor(property) > 0 ? 'amount-due' : 'amount-credit'">{{ formatMoney(Math.abs(balanceFor(property))) }}</span><small v-if="balanceFor(property) < 0" class="cell-subtext">A favor</small></td><td><div class="row-actions"><button v-if="can('finance.view') || can('payments.view')" class="text-button" @click="viewStatement(property)">Ver cuenta</button><button v-if="can('community.manage')" class="icon-button" :aria-label="`Editar ${property.code}`" @click="openProperty(property)"><Pencil :size="16" /></button><button v-if="can('finance.manage')" class="icon-button" :aria-label="`Saldo inicial de ${property.code}`" title="Saldo inicial" @click="openOpening(property)"><Banknote :size="17" /></button></div></td></tr><tr v-if="!filteredProperties.length"><td colspan="6" class="empty-cell">No hay inmuebles que coincidan con la búsqueda.</td></tr></tbody></table></div>
                        <div v-if="communityTab === 'people'" class="table-scroll"><table><thead><tr><th>Nombre</th><th>Identificación</th><th>Correo</th><th>Teléfono</th><th><span class="sr-only">Acciones</span></th></tr></thead><tbody><tr v-for="person in filteredPeople" :key="person.id"><td><strong>{{ person.name }}</strong></td><td>{{ person.identity_number || '—' }}</td><td>{{ person.email || '—' }}</td><td>{{ person.phone || '—' }}</td><td><button v-if="can('community.manage')" class="icon-button" :aria-label="`Editar ${person.name}`" @click="openPerson(person)"><Pencil :size="16" /></button></td></tr><tr v-if="!filteredPeople.length"><td colspan="5" class="empty-cell">Agrega las personas de la comunidad para vincularlas con sus inmuebles.</td></tr></tbody></table></div>
                        <div v-if="communityTab === 'census'" class="table-scroll"><table><thead><tr><th>Persona</th><th>Inmueble</th><th>Relación</th><th>Desde</th><th>Hasta</th><th><span class="sr-only">Acciones</span></th></tr></thead><tbody><tr v-for="occupancy in workspace.occupancies" :key="occupancy.id"><td><strong>{{ personName(occupancy.person_id) }}</strong></td><td>{{ propertyCode(occupancy.property_id) }}</td><td>{{ relationshipLabels[occupancy.relationship] }}</td><td>{{ displayDate(occupancy.starts_on) }}</td><td><span v-if="!occupancy.ends_on" class="badge green">Actual</span><span v-else>{{ displayDate(occupancy.ends_on) }}</span></td><td><button v-if="can('community.manage')" class="icon-button" aria-label="Editar relación" @click="openOccupancy(occupancy)"><Pencil :size="16" /></button></td></tr><tr v-if="!workspace.occupancies.length"><td colspan="6" class="empty-cell">Vincula propietarios, inquilinos y residentes con sus inmuebles.</td></tr></tbody></table></div>
                    </section>
                </template>

                <template v-if="page === 'periods'">
                    <div class="page-heading"><div><p class="eyebrow">CUOTAS Y COBRANZA</p><h1>Períodos</h1><p class="muted">Prepara los conceptos del mes y revisa el reparto antes de emitir.</p></div><button v-if="can('finance.manage')" class="button primary" @click="openPeriod()"><Plus :size="18" />Nuevo período</button></div>
                    <div v-if="workspace.periods.length" class="period-selector"><label>Período<select v-model="selectedPeriodId"><option v-for="period in workspace.periods" :key="period.id" :value="period.id">{{ displayMonth(period.month) }} · {{ period.status === 'draft' ? 'Borrador' : 'Emitido' }}</option></select></label><span v-if="selectedPeriod" class="badge" :class="selectedPeriod.status === 'draft' ? 'yellow' : 'green'">{{ selectedPeriod.status === 'draft' ? 'Borrador editable' : 'Cuotas emitidas' }}</span></div>
                    <template v-if="selectedPeriod">
                        <div class="stats-grid period-stats"><article class="stat-card"><div class="stat-top"><span>Base distribuible</span></div><strong>{{ formatMoney(selectedPeriod.net_usd_cents) }}</strong></article><article class="stat-card"><div class="stat-top"><span>Reserva · {{ selectedPeriod.reserve_basis_points / 100 }} %</span></div><strong>{{ formatMoney(selectedPeriod.reserve_usd_cents) }}</strong></article><article class="stat-card accent"><div class="stat-top"><span>Cuota por TH</span></div><strong>{{ formatMoney(selectedPeriod.unit_charge_usd_cents) }}</strong></article></div>
                        <section class="panel"><div class="panel-heading"><h2>Conceptos de {{ displayMonth(selectedPeriod.month) }}</h2><div v-if="selectedPeriod.status === 'draft' && can('finance.manage')" class="button-row"><button class="button secondary small-button" :disabled="busy" @click="runAction(`/api/periods/${selectedPeriod.id}/load-recurring`)"><RefreshCw :size="15" />Cargar habituales</button><button class="button primary small-button" @click="openItem()"><Plus :size="16" />Agregar concepto</button></div></div><div v-for="(label, category) in categories" :key="category" class="expense-category"><h3>{{ label }}</h3><div class="table-scroll"><table><thead><tr><th>Concepto</th><th class="numeric">Total USD</th><th class="numeric">Por TH USD</th><th v-if="selectedPeriod.status === 'draft' && can('finance.manage')"><span class="sr-only">Acciones</span></th></tr></thead><tbody><tr v-for="item in periodItems.filter(item => item.category === category)" :key="item.id"><td>{{ item.description }}<span v-if="item.is_provision" class="badge neutral inline-badge">Provisión</span></td><td class="numeric" :class="{ 'amount-credit': item.amount_usd_cents < 0 }">{{ formatMoney(item.amount_usd_cents) }}</td><td class="numeric muted">{{ formatMoney(Number(item.amount_usd_cents) / 90) }}</td><td v-if="selectedPeriod.status === 'draft' && can('finance.manage')"><div class="row-actions"><button class="icon-button" aria-label="Editar concepto" @click="openItem(item)"><Pencil :size="15" /></button><button class="icon-button" aria-label="Eliminar concepto" :disabled="busy" @click="runAction(`/api/items/${item.id}`, '¿Eliminar este concepto del borrador?', 'DELETE')"><Trash2 :size="15" /></button></div></td></tr><tr v-if="!periodItems.some(item => item.category === category)"><td :colspan="selectedPeriod.status === 'draft' ? 4 : 3" class="muted empty-category">Sin conceptos en esta categoría.</td></tr></tbody></table></div></div><div class="period-total"><div><span>Total del período</span><strong>{{ formatMoney(selectedPeriod.total_usd_cents) }}</strong><small class="muted">Ajuste de redondeo · {{ formatMoney(selectedPeriod.rounding_adjustment_usd_cents) }}</small></div><button v-if="selectedPeriod.status === 'draft' && can('finance.manage')" class="button primary" :disabled="busy" @click="runAction(`/api/periods/${selectedPeriod.id}/issue`, 'Se incorporarán las cuotas de este período a los estados de cuenta. ¿Confirmar la emisión?')"><Send :size="17" />Emitir cuotas</button><button v-else-if="can('reports.view')" class="button secondary" @click="navigate('documents')"><FileText :size="17" />Ver avisos de cobro</button></div></section>
                    </template>
                    <section v-else class="panel empty-state"><CalendarDays :size="32" /><h3>Prepara tu primer período</h3><p>Agrega los gastos y revisa las cuotas antes de emitir los avisos de cobro.</p><button v-if="can('finance.manage')" class="button primary" @click="openPeriod()">Crear período</button></section>
                </template>

                <template v-if="page === 'payments'">
                    <div class="page-heading"><div><p class="eyebrow">INGRESOS DEL CONDOMINIO</p><h1>Pagos recibidos</h1><p class="muted">Transferencias en bolívares y su aplicación a las cuentas en dólares.</p></div><button v-if="can('payments.manage')" class="button primary" @click="openPayment()"><Plus :size="18" />Registrar pago</button></div>
                    <section class="panel"><div class="table-scroll"><table><thead><tr><th>Referencia</th><th>Fecha</th><th>Cuenta</th><th class="numeric">Recibido Bs</th><th class="numeric">Equivalente USD</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead><tbody><tr v-for="payment in workspace.payments" :key="payment.id"><td><strong>{{ payment.reference }}</strong><small class="cell-subtext">Tasa {{ Number(payment.rate_micros) / 1000000 }} Bs/USD</small></td><td>{{ displayDate(payment.paid_on) }}</td><td>{{ bankName(payment.bank_account_id) }}</td><td class="numeric">{{ formatMoney(payment.amount_bs_cents, 'VES') }}</td><td class="numeric">{{ formatMoney(payment.amount_usd_cents) }}</td><td><span class="badge" :class="['void', 'voided'].includes(payment.status) ? 'neutral' : 'green'">{{ ['void', 'voided'].includes(payment.status) ? 'Anulado' : 'Confirmado' }}</span></td><td><div class="row-actions"><a v-if="can('reports.view')" :href="`/documents/payments/${payment.id}`" target="_blank" rel="noopener" class="icon-button" aria-label="Descargar comprobante"><Download :size="16" /></a><button v-if="can('payments.manage') && !['void', 'voided'].includes(payment.status)" class="icon-button" aria-label="Anular pago" @click="openVoid('payments', payment)"><X :size="16" /></button></div></td></tr><tr v-if="!workspace.payments.length"><td colspan="7" class="empty-cell">Los pagos registrados aparecerán aquí con su referencia y tasa de cambio.</td></tr></tbody></table></div></section>
                </template>

                <template v-if="page === 'treasury'">
                    <div class="page-heading"><div><p class="eyebrow">TESORERÍA</p><h1>Gastos y bancos</h1><p class="muted">Controla los pagos, las cuentas y los fondos de la comunidad.</p></div><div v-if="can('treasury.manage')" class="button-row"><button v-if="treasuryTab === 'expenses'" class="button primary" @click="openExpense()"><Plus :size="18" />Registrar gasto pagado</button><button v-if="treasuryTab === 'banks'" class="button primary" @click="openBank()"><Plus :size="18" />Agregar cuenta</button><button v-if="treasuryTab === 'funds'" class="button primary" @click="openFund()"><Plus :size="18" />Apertura de fondo</button></div><button v-if="treasuryTab === 'templates' && can('finance.manage')" class="button primary" @click="openTemplate()"><Plus :size="18" />Agregar concepto</button></div>
                    <div class="tabs section-tabs"><button :class="{ active: treasuryTab === 'expenses' }" @click="treasuryTab = 'expenses'">Gastos pagados</button><button :class="{ active: treasuryTab === 'banks' }" @click="treasuryTab = 'banks'">Cuentas bancarias</button><button :class="{ active: treasuryTab === 'funds' }" @click="treasuryTab = 'funds'">Fondos</button><button :class="{ active: treasuryTab === 'templates' }" @click="treasuryTab = 'templates'">Gastos habituales</button></div>
                    <section v-if="treasuryTab === 'expenses'" class="panel"><div class="table-scroll"><table><thead><tr><th>Concepto</th><th>Fecha</th><th>Fondo</th><th class="numeric">Pagado Bs</th><th class="numeric">Equivalente USD</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead><tbody><tr v-for="expense in workspace.expenses" :key="expense.id"><td><strong>{{ expense.description }}</strong><small class="cell-subtext">{{ bankName(expense.bank_account_id) }} · {{ expense.reference }}</small></td><td>{{ displayDate(expense.paid_on) }}</td><td>{{ fundLabels[expense.fund] }}</td><td class="numeric">{{ formatMoney(expense.amount_bs_cents, 'VES') }}</td><td class="numeric">{{ formatMoney(expense.amount_usd_cents) }}</td><td><span class="badge" :class="['void', 'voided'].includes(expense.status) ? 'neutral' : 'green'">{{ ['void', 'voided'].includes(expense.status) ? 'Anulado' : 'Confirmado' }}</span></td><td><button v-if="can('treasury.manage') && !['void', 'voided'].includes(expense.status)" class="icon-button" aria-label="Anular gasto" @click="openVoid('expenses', expense)"><X :size="16" /></button></td></tr><tr v-if="!workspace.expenses.length"><td colspan="7" class="empty-cell">Registra los pagos realizados a servicios y proveedores.</td></tr></tbody></table></div></section>
                    <div v-if="treasuryTab === 'banks'" class="bank-grid"><article v-for="bank in workspace.banks" :key="bank.id" class="panel bank-card"><div class="panel-heading"><span class="bank-icon"><Wallet :size="22" /></span><span class="badge" :class="bank.active ? 'green' : 'neutral'">{{ bank.active ? 'Activa' : 'Inactiva' }}</span></div><h2>{{ bank.name }}</h2><p class="muted small">Saldo registrado</p><strong class="bank-balance">{{ formatMoney(bank.balance_bs_cents ?? workspace.dashboard.accounts?.find(account => account.id === bank.id)?.balance_bs_cents ?? bank.opening_balance_bs_cents, 'VES') }}</strong><p class="bank-opening">Apertura · {{ formatMoney(bank.opening_balance_bs_cents, 'VES') }}</p></article><section v-if="!workspace.banks.length" class="panel empty-state"><Wallet :size="30" /><h3>Agrega una cuenta bancaria</h3><p>Permitirá registrar transferencias recibidas y gastos pagados.</p></section></div>
                    <div v-if="treasuryTab === 'funds'" class="fund-grid"><article class="panel fund-card"><ShieldCheck :size="24" /><h2>Fondo de reserva</h2><strong>{{ formatMoney(workspace.dashboard.reserve_usd_cents) }}</strong><p>Saldo cobrado y disponible para gastos de reserva.</p></article><article class="panel fund-card"><Banknote :size="24" /><h2>Provisiones</h2><strong>{{ formatMoney(workspace.dashboard.provision_usd_cents) }}</strong><p>Saldo registrado para conceptos provisionados.</p></article></div>
                    <section v-if="treasuryTab === 'templates'" class="panel"><div class="table-scroll"><table><thead><tr><th>Concepto</th><th>Categoría</th><th class="numeric">Importe habitual</th><th>Recurrente</th><th>Estado</th><th><span class="sr-only">Acciones</span></th></tr></thead><tbody><tr v-for="template in workspace.templates" :key="template.id"><td><strong>{{ template.description }}</strong><small v-if="template.is_provision" class="cell-subtext">Provisión</small></td><td>{{ categories[template.category] }}</td><td class="numeric">{{ formatMoney(template.amount_usd_cents) }}</td><td>{{ template.recurring ? 'Sí' : 'No' }}</td><td><span class="badge" :class="template.active ? 'green' : 'neutral'">{{ template.active ? 'Activo' : 'Inactivo' }}</span></td><td><button v-if="can('finance.manage')" class="icon-button" aria-label="Editar gasto habitual" @click="openTemplate(template)"><Pencil :size="16" /></button></td></tr><tr v-if="!workspace.templates.length"><td colspan="6" class="empty-cell">Guarda los conceptos habituales para reutilizarlos en cada período.</td></tr></tbody></table></div></section>
                </template>

                <template v-if="page === 'documents'">
                    <div class="page-heading"><div><p class="eyebrow">DOCUMENTOS Y COMUNICACIÓN</p><h1>Documentos</h1><p class="muted">Avisos de cobro, estados de cuenta e historial de correos.</p></div><button v-if="can('communications.manage')" class="button primary" @click="openCommunication()"><Mail :size="18" />Preparar correo</button></div>
                    <div class="tabs section-tabs"><button :class="{ active: documentsTab === 'charges' }" @click="documentsTab = 'charges'">Avisos de cobro</button><button :class="{ active: documentsTab === 'statements' }" @click="documentsTab = 'statements'">Estados de cuenta</button><button v-if="can('communications.manage')" :class="{ active: documentsTab === 'deliveries' }" @click="documentsTab = 'deliveries'">Correos</button></div>
                    <section class="panel"><div v-if="documentsTab === 'charges'" class="table-scroll"><table><thead><tr><th>Inmueble</th><th>Período</th><th>Concepto</th><th class="numeric">Importe</th><th><span class="sr-only">Descargar</span></th></tr></thead><tbody><tr v-for="charge in workspace.charges" :key="charge.id"><td><strong>{{ propertyCode(charge.property_id) }}</strong></td><td>{{ displayMonth(charge.period?.month ?? workspace.periods.find(period => period.id === charge.financial_period_id)?.month) }}</td><td>{{ charge.description ?? 'Cuota del condominio' }}</td><td class="numeric">{{ formatMoney(charge.amount_usd_cents) }}</td><td><a :href="`/documents/charges/${charge.id}`" target="_blank" rel="noopener" class="text-button"><Download :size="16" />Descargar PDF</a></td></tr><tr v-if="!workspace.charges.length"><td colspan="5" class="empty-cell">Los avisos estarán disponibles al emitir un período.</td></tr></tbody></table></div><div v-if="documentsTab === 'statements'" class="table-scroll"><table><thead><tr><th>Inmueble</th><th>Propietario</th><th><span class="sr-only">Descargar</span></th></tr></thead><tbody><tr v-for="property in workspace.properties" :key="property.id"><td><strong>{{ property.code }}</strong></td><td>{{ occupantsFor(property.id, 'owner') }}</td><td><a :href="`/documents/properties/${property.id}`" target="_blank" rel="noopener" class="text-button"><Download :size="16" />Estado de cuenta PDF</a></td></tr><tr v-if="!workspace.properties.length"><td colspan="3" class="empty-cell">No hay inmuebles disponibles para consultar.</td></tr></tbody></table></div><div v-if="documentsTab === 'deliveries'" class="table-scroll"><table><thead><tr><th>Asunto</th><th>Destinatario</th><th>Estado</th><th>Fecha</th><th><span class="sr-only">Acciones</span></th></tr></thead><tbody><tr v-for="delivery in workspace.deliveries" :key="delivery.id"><td><strong>{{ delivery.subject }}</strong></td><td>{{ delivery.recipient ?? delivery.email ?? delivery.to_email }}</td><td><span class="badge" :class="delivery.status === 'sent' ? 'green' : 'yellow'">{{ ({ sent: 'Enviado', pending: 'Pendiente', queued: 'En cola', preview: 'Vista previa', failed: 'Fallido' })[delivery.status] ?? delivery.status }}</span></td><td>{{ displayDate(delivery.created_at) }}</td><td><a :href="`/api/deliveries/${delivery.id}/preview`" target="_blank" rel="noopener" class="text-button">Ver correo<ArrowRight :size="15" /></a></td></tr><tr v-if="!workspace.deliveries.length"><td colspan="5" class="empty-cell">Los correos preparados aparecerán aquí con su estado.</td></tr></tbody></table></div></section>
                </template>

                <template v-if="page === 'users'">
                    <div class="page-heading"><div><p class="eyebrow">ACCESOS Y PERMISOS</p><h1>Usuarios</h1><p class="muted">Asigna a cada persona las herramientas y los inmuebles que necesita.</p></div><button class="button primary" @click="openUser()"><Plus :size="18" />Agregar usuario</button></div><section class="panel"><div class="table-scroll"><table><thead><tr><th>Usuario</th><th>Rol</th><th>Estado</th><th>Acceso</th><th><span class="sr-only">Acciones</span></th></tr></thead><tbody><tr v-for="account in accounts" :key="account.id"><td><strong>{{ account.name }}</strong><small class="cell-subtext">{{ account.email }}</small></td><td>{{ roleLabels[account.role] }}</td><td><span class="badge" :class="account.active ? 'green' : 'neutral'">{{ account.active ? 'Habilitado' : 'Deshabilitado' }}</span></td><td>{{ account.role === 'user' ? `${account.permissions?.length ?? 0} permisos` : 'Administración' }}</td><td><button v-if="user.role === 'superuser' || account.role !== 'superuser'" class="icon-button" :aria-label="`Editar ${account.name}`" @click="openUser(account)"><Pencil :size="16" /></button></td></tr><tr v-if="!accounts.length"><td colspan="5" class="empty-cell">No hay usuarios disponibles.</td></tr></tbody></table></div></section>
                </template>
            </main>
            <footer class="workspace-footer"><span>Terrazas del Atlántico · Sector C</span><span>Importes de cuenta en USD · Movimientos bancarios en Bs</span></footer>
        </div>
    </div>

    <FormDialog v-if="dialog" :key="dialog.kind" :title="dialog.title" :wide="dialog.wide" :readonly="dialog.readonly" :busy="busy" :error="dialogError" :errors="fieldErrors" :submit-label="dialog.submitLabel ?? 'Guardar'" @close="dialog = null" @submit="saveDialog">
        <div v-if="dialog.kind === 'property'" class="form-grid"><label>Código del inmueble<input v-model="form.code" required placeholder="TH 14-01" maxlength="50" /></label><label>Ocupación<select v-model="form.occupancy_status"><option v-for="(label, value) in occupancyLabels" :key="value" :value="value">{{ label }}</option></select></label><label class="checkbox-label full-width"><input v-model="form.active" type="checkbox" />Inmueble activo</label></div>
        <div v-if="dialog.kind === 'person'" class="form-grid"><label class="full-width">Nombre y apellido<input v-model="form.name" required maxlength="255" autocomplete="name" /></label><label>Cédula o RIF<input v-model="form.identity_number" required placeholder="V-12345678" /></label><label>Teléfono<input v-model="form.phone" type="tel" autocomplete="tel" /></label><label class="full-width">Correo electrónico<input v-model="form.email" type="email" autocomplete="email" /></label></div>
        <div v-if="dialog.kind === 'occupancy'" class="form-grid"><label>Inmueble<select v-model="form.property_id" required><option disabled value="">Seleccionar inmueble</option><option v-for="property in workspace.properties" :key="property.id" :value="property.id">{{ property.code }}</option></select></label><label>Persona<select v-model="form.person_id" required><option disabled value="">Seleccionar persona</option><option v-for="person in workspace.people" :key="person.id" :value="person.id">{{ person.name }}</option></select></label><label class="full-width">Relación<select v-model="form.relationship"><option v-for="(label, value) in relationshipLabels" :key="value" :value="value">{{ label }}</option></select></label><label>Desde<input v-model="form.starts_on" type="date" required /></label><label>Hasta <span class="optional">opcional</span><input v-model="form.ends_on" type="date" :min="form.starts_on" /></label></div>
        <div v-if="dialog.kind === 'opening'" class="form-grid"><p class="form-help full-width">Un importe positivo registra deuda. Un importe negativo registra saldo a favor. La apertura se conserva en el historial.</p><label>Importe en USD<input v-model="form.amount_usd" type="number" step="0.01" required /></label><label>Fecha de corte<input v-model="form.date" type="date" required /></label></div>
        <div v-if="dialog.kind === 'period'" class="form-grid"><label>Mes a cobrar<input v-model="form.month" type="month" required /></label><label>Reserva en puntos básicos<input v-model="form.reserve_basis_points" type="number" min="0" max="10000" step="1" required /><span class="form-help">{{ Number(form.reserve_basis_points || 0) / 100 }} % de la base distribuible</span></label></div>
        <div v-if="['item', 'template'].includes(dialog.kind)" class="form-grid"><label class="full-width">Concepto<input v-model="form.description" required maxlength="500" placeholder="Mantenimiento de áreas verdes" /></label><label class="full-width">Categoría<select v-model="form.category"><option v-for="(label, value) in categories" :key="value" :value="value">{{ label }}</option></select></label><label>Importe USD<input v-model="form.amount_usd" type="number" step="0.01" required /><span class="form-help">Un valor negativo reduce el reparto.</span></label><label class="checkbox-label"><input v-model="form.is_provision" type="checkbox" />Corresponde a una provisión</label><template v-if="dialog.kind === 'template'"><label class="checkbox-label"><input v-model="form.recurring" type="checkbox" />Incluir en gastos habituales</label><label class="checkbox-label"><input v-model="form.active" type="checkbox" />Concepto activo</label></template></div>
        <div v-if="dialog.kind === 'bank'" class="form-grid"><label class="full-width">Nombre de la cuenta<input v-model="form.name" required placeholder="Banco Caroní · Cuenta corriente" /></label><label class="full-width">Saldo inicial Bs<input v-model="form.opening_balance_bs" type="number" step="0.01" min="0" required /></label></div>
        <div v-if="dialog.kind === 'fund'" class="form-grid"><label class="full-width">Fondo<select v-model="form.fund"><option value="reserve">Reserva</option><option value="provision">Provisión</option></select></label><label>Saldo inicial USD<input v-model="form.amount_usd" type="number" step="0.01" min="0.01" required /></label><label>Fecha de corte<input v-model="form.date" type="date" required /></label><p class="form-help full-width">Registra fondos existentes. El saldo bancario se incorpora desde la cuenta bancaria correspondiente.</p></div>
        <template v-if="['payment', 'expense'].includes(dialog.kind)">
            <div class="form-grid"><label v-if="dialog.kind === 'expense'" class="full-width">Descripción del gasto<input v-model="form.description" required /></label><label>Cuenta bancaria<select v-model="form.bank_account_id" required><option disabled value="">Seleccionar cuenta</option><option v-for="bank in workspace.banks.filter(bank => bank.active)" :key="bank.id" :value="bank.id">{{ bank.name }}</option></select></label><label>Fecha de la transferencia<input v-model="form.paid_on" type="date" required :max="localDate()" /></label><label>Referencia bancaria<input v-model="form.reference" required autocomplete="off" /></label><label>Importe Bs<input v-model="form.amount_bs" type="number" step="0.01" min="0.01" required /></label><label>Tasa BCV · Bs por USD<input v-model="form.rate" type="number" step="0.000001" min="0.000001" required /></label><div class="rate-action"><button type="button" class="button secondary" @click="fetchRate"><RefreshCw :size="16" />Consultar tasa del día</button></div><label class="full-width">Fuente de la tasa<input v-model="form.rate_source" required placeholder="BCV · referencia de la tasa consultada" /></label><template v-if="dialog.kind === 'expense'"><label>Fondo utilizado<select v-model="form.fund"><option v-for="(label, value) in fundLabels" :key="value" :value="value">{{ label }}</option></select></label><label>Concepto del período <span class="optional">opcional</span><select v-model="form.period_item_id"><option value="">Sin vinculación</option><option v-for="item in workspace.items.filter(item => item.amount_usd_cents > 0)" :key="item.id" :value="item.id">{{ item.description }}</option></select></label></template></div>
            <div class="conversion-preview"><span>Equivalente de la transferencia</span><strong>{{ formatMoney(rateEquivalentCents) }}</strong></div>
            <div v-if="dialog.kind === 'payment'" class="payment-splits"><div class="panel-heading"><h3>Aplicar a inmuebles</h3><button type="button" class="text-button" @click="form.splits.push({ property_id: '', amount_usd: '' })"><Plus :size="16" />Agregar inmueble</button></div><div v-for="(split, index) in form.splits" :key="index" class="split-row"><label>Inmueble<select v-model="split.property_id" required><option disabled value="">Seleccionar TH</option><option v-for="property in workspace.properties" :key="property.id" :value="property.id">{{ property.code }}</option></select></label><label>Aplicar USD<input v-model="split.amount_usd" type="number" step="0.01" min="0.01" required /></label><button v-if="form.splits.length > 1" type="button" class="icon-button" aria-label="Quitar inmueble del reparto" @click="form.splits.splice(index, 1)"><Trash2 :size="17" /></button></div><p class="form-help" :class="{ 'validation-error': splitCents !== rateEquivalentCents }">Distribuido {{ formatMoney(splitCents) }} de {{ formatMoney(rateEquivalentCents) }}. El excedente sobre la deuda quedará a favor del inmueble.</p></div>
        </template>
        <div v-if="dialog.kind === 'void'" class="form-grid"><p class="form-help full-width">La operación se revertirá conservando su registro y el motivo de anulación.</p><label class="full-width">Motivo<textarea v-model="form.reason" required minlength="5" rows="3"></textarea></label></div>
        <div v-if="dialog.kind === 'communication'" class="form-grid"><label>Tipo de correo<select v-model="form.kind"><option value="announcement">Comunicado general</option><option value="notice">Aviso de cobro</option></select></label><label v-if="form.kind === 'notice'">Período<select v-model="form.period_id" required><option disabled value="">Seleccionar período</option><option v-for="period in workspace.periods.filter(period => period.status === 'issued')" :key="period.id" :value="period.id">{{ displayMonth(period.month) }}</option></select></label><label class="full-width">Asunto<input v-model="form.subject" required maxlength="255" /></label><label class="full-width">Mensaje<textarea v-model="form.body" required rows="5"></textarea></label><fieldset class="full-width"><legend>Destinatarios</legend><p class="form-help">Sin selección se incluyen todos los inmuebles autorizados con correo registrado.</p><div class="checkbox-grid property-checkboxes"><label v-for="property in workspace.properties" :key="property.id" class="checkbox-label"><input v-model="form.property_ids" type="checkbox" :value="property.id" />{{ property.code }}</label></div></fieldset></div>
        <div v-if="dialog.kind === 'user'" class="form-grid"><label>Nombre<input v-model="form.name" required autocomplete="off" /></label><label>Correo electrónico<input v-model="form.email" type="email" required autocomplete="off" /></label><label>Contraseña <span v-if="dialog.method === 'PUT'" class="optional">opcional</span><input v-model="form.password" type="password" minlength="10" :required="dialog.method === 'POST'" autocomplete="new-password" /><span class="form-help">Al menos 10 caracteres.</span></label><label>Rol<select v-model="form.role"><option value="user">Usuario</option><option value="admin">Administrador</option><option v-if="user.role === 'superuser'" value="superuser">Superusuario</option></select></label><label class="checkbox-label full-width"><input v-model="form.active" type="checkbox" />Cuenta habilitada</label><fieldset v-if="form.role === 'user'" class="full-width"><legend>Permisos</legend><div class="checkbox-grid"><label v-for="permission in availablePermissions" :key="permission" class="checkbox-label"><input v-model="form.permissions" type="checkbox" :value="permission" />{{ permissionLabels[permission] ?? permission }}</label></div></fieldset><fieldset v-if="form.role === 'user'" class="full-width"><legend>Inmuebles asignados</legend><div class="checkbox-grid property-checkboxes"><label v-for="property in workspace.properties" :key="property.id" class="checkbox-label"><input v-model="form.property_ids" type="checkbox" :value="property.id" />{{ property.code }}</label></div></fieldset></div>
        <div v-if="dialog.kind === 'statement' && statement"><div class="statement-summary"><div><span>Deuda pendiente</span><strong>{{ formatMoney(statement.debt_usd_cents) }}</strong></div><div><span>Saldo a favor</span><strong>{{ formatMoney(statement.credit_usd_cents) }}</strong></div><a :href="`/documents/properties/${form.property_id}`" target="_blank" rel="noopener" class="button secondary"><Download :size="16" />Descargar PDF</a></div><div class="table-scroll"><table><thead><tr><th>Concepto</th><th>Fecha</th><th class="numeric">Cargo</th><th class="numeric">Pendiente</th></tr></thead><tbody><tr v-for="charge in statement.charges" :key="charge.id"><td>{{ charge.description ?? 'Cuota del condominio' }}</td><td>{{ displayDate(charge.date ?? charge.created_at) }}</td><td class="numeric">{{ formatMoney(charge.amount_usd_cents) }}</td><td class="numeric">{{ formatMoney(charge.remaining_usd_cents ?? charge.balance_usd_cents) }}</td></tr><tr v-if="!statement.charges?.length"><td colspan="4" class="empty-cell">Este inmueble todavía no tiene cargos.</td></tr></tbody></table></div></div>
    </FormDialog>
</template>
