<script setup lang="ts">
import Delta from '@/Components/Charts/Delta.vue';

withDefaults(
    defineProps<{
        label: string;
        value: string;
        delta: number | null;
        positiveIsGood?: boolean;
        /** What the delta is measured against — e.g. "vs. prejšnjih 30 dni". Shown so a "-45%" is never unexplained. */
        compareLabel?: string | null;
    }>(),
    { positiveIsGood: true, compareLabel: null },
);
</script>

<template>
    <div class="min-w-0 rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] px-4 py-4 sm:px-5">
        <p class="truncate text-xs font-medium text-neutral-500">{{ label }}</p>
        <div class="mt-2 flex min-w-0 flex-wrap items-baseline gap-x-2 gap-y-1">
            <span class="truncate text-xl font-semibold text-neutral-900 sm:text-2xl">{{ value }}</span>
            <Delta :value="delta" :positive-is-good="positiveIsGood" />
        </div>
        <p v-if="compareLabel && delta !== null" class="mt-0.5 truncate text-[11px] text-neutral-400">{{ compareLabel }}</p>
    </div>
</template>
