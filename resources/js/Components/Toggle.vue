<script setup lang="ts">
defineProps<{
    modelValue: boolean;
    disabled?: boolean;
}>();

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :disabled="disabled"
        class="relative inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors disabled:cursor-not-allowed disabled:opacity-50"
        :class="modelValue ? 'bg-[var(--color-ink-900)]' : 'bg-neutral-300'"
        @click="!disabled && emit('update:modelValue', !modelValue)"
    >
        <span
            class="inline-block h-3.5 w-3.5 transform rounded-full bg-white shadow transition-transform"
            :class="modelValue ? 'translate-x-[18px]' : 'translate-x-1'"
        />
    </button>
</template>
