<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    password: string;
}>();

const level = computed(() => {
    const pw = props.password;
    if (!pw) return 0;

    let score = 0;
    if (pw.length >= 8) score++;
    if (pw.length >= 12) score++;
    if (/[a-z]/.test(pw) && /[A-Z]/.test(pw)) score++;
    if (/\d/.test(pw)) score++;
    if (/[^A-Za-z0-9]/.test(pw)) score++;

    return Math.min(4, score);
});

const meta = computed(() => {
    switch (level.value) {
        case 1:
            return { label: 'Šibko', color: '#EF4444' };
        case 2:
            return { label: 'Srednje', color: '#F59E0B' };
        case 3:
            return { label: 'Dobro', color: '#84CC16' };
        case 4:
            return { label: 'Močno', color: '#10B981' };
        default:
            return { label: '', color: '#E5E7EB' };
    }
});
</script>

<template>
    <div v-if="password" class="mt-1.5">
        <div class="flex gap-1">
            <span
                v-for="segment in 4"
                :key="segment"
                class="h-1 flex-1 rounded-full transition-colors"
                :style="{ backgroundColor: segment <= level ? meta.color : '#E5E7EB' }"
            />
        </div>
        <p v-if="meta.label" class="mt-1 text-xs" :style="{ color: meta.color }">{{ meta.label }}</p>
    </div>
</template>
