<script setup lang="ts">
import { UserRound, Building2 } from 'lucide-vue-next';

defineProps<{ modelValue: boolean }>();
defineEmits<{ 'update:modelValue': [value: boolean] }>();
</script>

<template>
    <div class="flex w-full rounded-md border border-neutral-200 bg-white p-0.5 text-sm font-medium">
        <button
            type="button"
            class="flex flex-1 items-center justify-center gap-1.5 rounded py-2 transition"
            :class="!modelValue ? 'bg-[var(--color-ink-900)] text-white' : 'text-neutral-500 hover:text-neutral-700'"
            @click="$emit('update:modelValue', false)"
        >
            <UserRound :size="14" /> Fizična oseba
        </button>
        <button
            type="button"
            class="flex flex-1 items-center justify-center gap-1.5 rounded py-2 transition"
            :class="modelValue ? 'bg-[var(--color-ink-900)] text-white' : 'text-neutral-500 hover:text-neutral-700'"
            @click="$emit('update:modelValue', true)"
        >
            <Building2 :size="14" /> Podjetje
        </button>
    </div>
</template>
