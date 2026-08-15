<script setup lang="ts">
withDefaults(defineProps<{ size?: number }>(), { size: 76 });
</script>

<template>
    <svg :width="size" :height="size" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" class="shrink-0 rounded-lg">
        <rect width="80" height="80" rx="10" fill="#FBEFE6" />
        <rect x="0" y="0" width="80" height="30" fill="#F6D9E8" />
        <path d="M38 8c0 2-2 2-2 4s2 2 2 4" stroke="#E1306C" stroke-width="1.6" stroke-linecap="round" />
        <circle cx="38" cy="7" r="2" fill="#FBBF24" />
        <rect x="20" y="34" width="40" height="16" rx="3" fill="#FFFFFF" stroke="#F3C9DD" stroke-width="1.5" />
        <path d="M20 38c3-2 5 2 8 0s5 2 8 0 5 2 8 0 5 2 8 0v-2c-3 2-5-2-8 0s-5-2-8 0-5-2-8 0-5-2-8 0z" fill="#F9E1EC" />
        <rect x="14" y="50" width="52" height="20" rx="3" fill="#FFFFFF" stroke="#F3C9DD" stroke-width="1.5" />
        <path d="M14 55c4-2.5 6 2.5 10 0s6 2.5 10 0 6 2.5 10 0 6 2.5 10 0 6 2.5 10 0v-2.5c-4 2.5-6-2.5-10 0s-6-2.5-10 0-6-2.5-10 0-6-2.5-10 0-6-2.5-10 0z" fill="#FCE3EF" />
        <circle cx="24" cy="61" r="2" fill="#F472B6" />
        <circle cx="40" cy="63" r="2" fill="#F9A8D4" />
        <circle cx="56" cy="61" r="2" fill="#F472B6" />
    </svg>
</template>
