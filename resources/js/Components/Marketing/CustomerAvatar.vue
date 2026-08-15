<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 36 });
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg" class="shrink-0">
        <circle cx="20" cy="20" r="20" fill="var(--color-accent-200)" />
        <circle cx="20" cy="17" r="7" fill="var(--color-accent-500)" />
        <path
            d="M6 39c1.2-8.5 8-13 14-13s12.8 4.5 14 13"
            fill="var(--color-accent-500)"
        />
    </svg>
</template>
