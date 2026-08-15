<script setup lang="ts">
import { computed } from 'vue';
import { ArrowUp, ArrowDown } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        value: number | null;
        /** Whether an increase counts as good news (revenue) or bad (e.g. cancellations). */
        positiveIsGood?: boolean;
    }>(),
    { positiveIsGood: true },
);

const isGood = computed(() => {
    if (props.value === null || props.value === 0) return null;
    const up = props.value > 0;
    return props.positiveIsGood ? up : !up;
});
</script>

<template>
    <span
        class="inline-flex items-center gap-0.5 text-xs font-medium"
        :class="{
            'text-neutral-400': isGood === null,
            'text-[#006300]': isGood === true,
            'text-[#d03b3b]': isGood === false,
        }"
    >
        <template v-if="value === null || value === 0">—</template>
        <template v-else>
            <ArrowUp v-if="value > 0" :size="12" />
            <ArrowDown v-else :size="12" />
            {{ Math.abs(value).toFixed(1) }}%
        </template>
    </span>
</template>
