<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 76 });
</script>

<template>
    <img
        src="https://images.unsplash.com/photo-1535141192574-5d4897c12636?w=200&h=200&fit=crop&q=80"
        alt="Rojstnodnevna torta"
        :style="{ width: `${size}px`, height: `${size}px` }"
        class="shrink-0 rounded-lg object-cover"
    />
</template>
