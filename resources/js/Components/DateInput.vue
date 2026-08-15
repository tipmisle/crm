<script setup lang="ts">
import { ref, watch } from 'vue';
import { CalendarDays } from 'lucide-vue-next';

// Native <input type="date"> renders dd/mm/yyyy or mm/dd/yyyy depending on
// the browser's OS-level locale, not the page's `lang` — Chromium ignores
// `lang` on the element for this. So we mask a text input to the Slovenian
// dd.mm.llll format ourselves and keep a hidden native input only to power
// the calendar-icon picker, syncing both to the same ISO (yyyy-mm-dd) value.

const props = withDefaults(
    defineProps<{
        modelValue: string;
        min?: string;
        max?: string;
        size?: 'sm' | 'md';
    }>(),
    { min: undefined, max: undefined, size: 'md' },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

function isoToDisplay(iso: string): string {
    const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
    if (!m) return '';
    return `${m[3]}.${m[2]}.${m[1]}`;
}

function displayToIso(display: string): string | null {
    const m = /^(\d{2})\.(\d{2})\.(\d{4})$/.exec(display);
    if (!m) return null;
    const [, day, month, year] = m;
    const iso = `${year}-${month}-${day}`;
    const date = new Date(iso);
    if (Number.isNaN(date.getTime()) || date.getUTCDate() !== Number(day) || date.getUTCMonth() + 1 !== Number(month)) return null;
    return iso;
}

const text = ref(isoToDisplay(props.modelValue));
const nativeInput = ref<HTMLInputElement | null>(null);

watch(
    () => props.modelValue,
    (value) => {
        const display = isoToDisplay(value);
        if (display !== text.value) text.value = display;
    },
);

function onTextInput(event: Event) {
    const raw = (event.target as HTMLInputElement).value;
    const digits = raw.replace(/\D/g, '').slice(0, 8);

    let formatted = digits;
    if (digits.length > 4) formatted = `${digits.slice(0, 2)}.${digits.slice(2, 4)}.${digits.slice(4)}`;
    else if (digits.length > 2) formatted = `${digits.slice(0, 2)}.${digits.slice(2)}`;

    text.value = formatted;

    const iso = displayToIso(formatted);
    if (iso) emit('update:modelValue', iso);
    else if (formatted === '') emit('update:modelValue', '');
}

function onBlur() {
    let iso = displayToIso(text.value);
    if (iso && props.min && iso < props.min) iso = null;
    if (iso && props.max && iso > props.max) iso = null;

    if (iso && iso !== props.modelValue) emit('update:modelValue', iso);
    text.value = iso ? isoToDisplay(iso) : isoToDisplay(props.modelValue);
}

function onNativeChange(event: Event) {
    const iso = (event.target as HTMLInputElement).value;
    emit('update:modelValue', iso);
    text.value = isoToDisplay(iso);
}

function openPicker() {
    nativeInput.value?.showPicker?.();
    nativeInput.value?.focus();
}
</script>

<template>
    <div class="relative">
        <input
            :value="text"
            type="text"
            inputmode="numeric"
            placeholder="dd.mm.llll"
            maxlength="10"
            :class="[
                'w-full rounded-md border border-neutral-200 outline-none focus:border-neutral-400',
                size === 'sm' ? 'px-2 py-1.5 pr-8 text-xs' : 'px-3 py-2 pr-9 text-sm',
            ]"
            @input="onTextInput"
            @blur="onBlur"
        />
        <button
            type="button"
            tabindex="-1"
            class="absolute top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600"
            :class="size === 'sm' ? 'right-2' : 'right-2.5'"
            @click="openPicker"
        >
            <CalendarDays :size="size === 'sm' ? 13 : 15" />
        </button>
        <input
            ref="nativeInput"
            :value="modelValue"
            type="date"
            :min="min"
            :max="max"
            tabindex="-1"
            class="pointer-events-none absolute inset-0 h-full w-full opacity-0"
            @change="onNativeChange"
        />
    </div>
</template>
