<script setup lang="ts">
import { ref, watch } from 'vue';

// Plain <input type="number"> can't render a Slovenian-formatted "85,00" —
// the browser always uses a period internally and never pads decimals.
// This masks a text input instead: shows the raw editable number while
// focused, formats to "85,00" once the user leaves the field.

const props = defineProps<{
    modelValue: number | string;
}>();

const emit = defineEmits<{
    (e: 'update:modelValue', value: number): void;
}>();

function format(value: number | string): string {
    const num = Number(value || 0);
    return num.toLocaleString('sl-SI', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

const focused = ref(false);
const text = ref(format(props.modelValue));

watch(
    () => props.modelValue,
    (value) => {
        if (!focused.value) text.value = format(value);
    },
);

function onFocus(event: FocusEvent) {
    focused.value = true;
    text.value = String(props.modelValue ?? '');
    requestAnimationFrame(() => (event.target as HTMLInputElement).select());
}

function onInput(event: Event) {
    text.value = (event.target as HTMLInputElement).value;
}

function onBlur() {
    focused.value = false;
    const parsed = parseFloat(text.value.replace(',', '.'));
    const value = Number.isFinite(parsed) ? parsed : 0;
    emit('update:modelValue', value);
    text.value = format(value);
}
</script>

<template>
    <input :value="text" type="text" inputmode="decimal" @focus="onFocus" @input="onInput" @blur="onBlur" />
</template>
