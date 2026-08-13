<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    name: string;
    size?: 'xs' | 'sm' | 'md' | 'lg';
}>();

const PALETTE = [
    { bg: '#EEECFC', fg: '#5A4FD1' },
    { bg: '#FCE7F0', fg: '#C13D77' },
    { bg: '#E0F7FA', fg: '#0E7490' },
    { bg: '#DBF6EF', fg: '#0F766E' },
    { bg: '#FEF3C7', fg: '#B45309' },
    { bg: '#E4F9EC', fg: '#178A4C' },
];

const initials = computed(() => {
    const parts = props.name.trim().split(/\s+/).slice(0, 2);
    return parts.map((p) => p[0]?.toUpperCase() ?? '').join('');
});

const colors = computed(() => {
    let hash = 0;
    for (let i = 0; i < props.name.length; i++) {
        hash = (hash << 5) - hash + props.name.charCodeAt(i);
        hash |= 0;
    }
    return PALETTE[Math.abs(hash) % PALETTE.length];
});

const sizeClasses = computed(() => {
    switch (props.size) {
        case 'xs':
            return 'h-6 w-6 text-[10px]';
        case 'lg':
            return 'h-12 w-12 text-base';
        case 'md':
            return 'h-9 w-9 text-xs';
        default:
            return 'h-8 w-8 text-xs';
    }
});
</script>

<template>
    <div
        class="flex shrink-0 items-center justify-center rounded-full font-semibold"
        :class="sizeClasses"
        :style="{ backgroundColor: colors.bg, color: colors.fg }"
    >
        {{ initials }}
    </div>
</template>
