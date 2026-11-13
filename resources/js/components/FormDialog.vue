<script setup>
import { nextTick, onMounted, ref } from 'vue';
import { X, LoaderCircle } from '@lucide/vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    busy: { type: Boolean, default: false },
    error: { type: String, default: '' },
    errors: { type: Object, default: () => ({}) },
    submitLabel: { type: String, default: 'Guardar' },
    wide: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'submit']);
const dialog = ref(null);

onMounted(async () => {
    dialog.value.showModal();
    await nextTick();
    dialog.value.querySelector('input, select, textarea')?.focus();
});
</script>

<template>
    <dialog ref="dialog" class="form-dialog" :class="{ 'dialog-wide': wide }" aria-labelledby="dialog-title" @cancel.prevent="!busy && emit('close')">
        <form @submit.prevent="emit('submit')">
            <header class="dialog-header">
                <div>
                    <h2 id="dialog-title">{{ title }}</h2>
                    <p v-if="description" class="muted">{{ description }}</p>
                </div>
                <button type="button" class="icon-button" aria-label="Cerrar" :disabled="busy" @click="emit('close')"><X :size="20" /></button>
            </header>
            <div class="dialog-body">
                <div v-if="error" class="alert error" role="alert">
                    {{ error }}
                    <ul v-if="Object.keys(errors).length"><li v-for="(messages, field) in errors" :key="field">{{ Array.isArray(messages) ? messages.join(' ') : messages }}</li></ul>
                </div>
                <slot />
            </div>
            <footer class="dialog-footer">
                <button type="button" class="button secondary" :disabled="busy" @click="emit('close')">{{ readonly ? 'Cerrar' : 'Cancelar' }}</button>
                <button v-if="!readonly" type="submit" class="button primary" :disabled="busy"><LoaderCircle v-if="busy" class="spin" :size="17" />{{ busy ? 'Guardando…' : submitLabel }}</button>
            </footer>
        </form>
    </dialog>
</template>
