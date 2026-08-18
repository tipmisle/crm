<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { onClickOutside } from '@vueuse/core';
import { Clock } from 'lucide-vue-next';

// Native <input type="time"> renders the browser/OS's own picker (the
// "--:--" placeholder can't be restyled), so we mask a text input to HH:mm
// ourselves and back it with a scrollable dropdown styled like the rest of
// the app's popovers (see CustomerCombobox / DateInput).

const props = withDefaults(
    defineProps<{
        modelValue: string;
        stepMinutes?: number;
        size?: 'sm' | 'md';
    }>(),
    { stepMinutes: 15, size: 'md' },
);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
}>();

function isValid(value: string): boolean {
    return /^([01]\d|2[0-3]):[0-5]\d$/.test(value);
}

const text = ref(props.modelValue);
const root = ref<HTMLElement | null>(null);
const open = ref(false);
const listEl = ref<HTMLElement | null>(null);

watch(
    () => props.modelValue,
    (value) => {
        if (value !== text.value) text.value = value;
    },
);

function onTextInput(event: Event) {
    const raw = (event.target as HTMLInputElement).value;
    const digits = raw.replace(/\D/g, '').slice(0, 4);

    let formatted = digits;
    if (digits.length > 2) formatted = `${digits.slice(0, 2)}:${digits.slice(2)}`;

    text.value = formatted;

    if (isValid(formatted)) emit('update:modelValue', formatted);
    else if (formatted === '') emit('update:modelValue', '');
}

function onBlur() {
    if (isValid(text.value)) {
        if (text.value !== props.modelValue) emit('update:modelValue', text.value);
    } else {
        text.value = props.modelValue;
    }
}

const options = computed(() => {
    const step = props.stepMinutes;
    const list: string[] = [];
    for (let mins = 0; mins < 24 * 60; mins += step) {
        list.push(`${String(Math.floor(mins / 60)).padStart(2, '0')}:${String(mins % 60).padStart(2, '0')}`);
    }
    return list;
});

function selectTime(value: string) {
    text.value = value;
    emit('update:modelValue', value);
    open.value = false;
}

async function openList() {
    open.value = !open.value;
    if (!open.value) return;
    await new Promise((r) => requestAnimationFrame(r));
    const active = listEl.value?.querySelector('[data-active="true"]') as HTMLElement | null;
    active?.scrollIntoView({ block: 'center' });
}

onClickOutside(root, () => {
    open.value = false;
});
</script>

<template>
    <div ref="root" class="relative">
        <input
            :value="text"
            type="text"
            inputmode="numeric"
            placeholder="14:30"
            maxlength="5"
            :class="[
                'w-full rounded-md border border-neutral-200 outline-none focus:border-neutral-400',
                size === 'sm' ? 'px-2 py-1.5 pr-8 text-xs' : 'px-3 py-2 pr-9 text-sm',
            ]"
            @input="onTextInput"
            @blur="onBlur"
            @focus="open = true"
        />
        <button
            type="button"
            tabindex="-1"
            class="absolute top-1/2 -translate-y-1/2 text-neutral-400 hover:text-neutral-600"
            :class="size === 'sm' ? 'right-2' : 'right-2.5'"
            @click="openList"
        >
            <Clock :size="size === 'sm' ? 13 : 15" />
        </button>

        <div
            v-if="open"
            ref="listEl"
            class="absolute z-20 mt-1.5 max-h-56 w-28 overflow-y-auto rounded-lg border border-neutral-200 bg-white py-1 shadow-lg shadow-neutral-900/[0.06]"
        >
            <button
                v-for="opt in options"
                :key="opt"
                type="button"
                tabindex="-1"
                :data-active="opt === modelValue"
                class="block w-full px-3 py-1.5 text-left text-xs transition"
                :class="
                    opt === modelValue
                        ? 'bg-[var(--color-ink-900)] font-semibold text-white'
                        : 'text-neutral-700 hover:bg-[var(--color-accent-50)]'
                "
                @click="selectTime(opt)"
            >
                {{ opt }}
            </button>
        </div>
    </div>
</template>
