<script setup lang="ts">
import { ref } from 'vue';
import { useIntersectionObserver } from '@vueuse/core';

withDefaults(defineProps<{ delay?: number }>(), { delay: 0 });

const el = ref<HTMLElement | null>(null);
const visible = ref(false);

useIntersectionObserver(
    el,
    ([entry], observer) => {
        if (entry?.isIntersecting) {
            visible.value = true;
            observer.disconnect();
        }
    },
    { threshold: 0.15 },
);
</script>

<template>
    <div
        ref="el"
        class="transition-all duration-700 ease-out"
        :class="visible ? 'translate-y-0 opacity-100' : 'translate-y-4 opacity-0'"
        :style="{ transitionDelay: `${delay}ms` }"
    >
        <slot />
    </div>
</template>
