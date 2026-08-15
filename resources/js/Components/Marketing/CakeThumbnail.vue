<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 76 });
</script>

<template>
    <img
        src="/images/marketing/cake-reference.webp"
        alt="Rojstnodnevna torta"
        :style="{ width: `${size}px`, height: `${size}px` }"
        class="shrink-0 rounded-lg object-cover"
    />
</template>
