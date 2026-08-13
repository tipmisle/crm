<script setup lang="ts">
withDefaults(
    defineProps<{
        color?: string;
        bg?: string;
    }>(),
    {
        color: '#4B5563',
        bg: '#F1F2F4',
    },
);
</script>

<template>
    <span
        class="inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-xs font-medium whitespace-nowrap"
        :style="{ color, backgroundColor: bg }"
    >
        <slot />
    </span>
</template>
