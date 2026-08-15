<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 36 });
</script>

<template>
    <img
        src="/images/marketing/nina-avatar.webp"
        alt="Nina Kovač"
        :style="{ width: `${size}px`, height: `${size}px` }"
        class="shrink-0 rounded-full object-cover"
    />
</template>
