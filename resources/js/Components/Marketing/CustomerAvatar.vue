<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 36 });
</script>

<template>
    <img
        src="https://images.unsplash.com/photo-1544005313-94ddf0286df2?w=100&h=100&fit=crop&q=80"
        alt="Nina Kovač"
        :style="{ width: `${size}px`, height: `${size}px` }"
        class="shrink-0 rounded-full object-cover"
    />
</template>
