<script setup lang="ts">
withDefaults(
    defineProps<{
        title: string;
        description?: string;
    }>(),
    {},
);
</script>

<template>
    <div class="flex flex-col items-center justify-center gap-1 py-8 text-center">
        <component :is="$slots.icon ? 'div' : 'span'" class="mb-1 text-neutral-300">
            <slot name="icon" />
        </component>
        <p class="text-sm font-medium text-neutral-700">{{ title }}</p>
        <p v-if="description" class="max-w-xs text-xs text-neutral-500">{{ description }}</p>
        <slot name="action" />
    </div>
</template>
