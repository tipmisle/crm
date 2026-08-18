<script setup lang="ts">
withDefaults(
    defineProps<{
        title: string;
        subtitle?: string;
    }>(),
    {},
);
</script>

<template>
    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04]">
        <header class="flex items-center justify-between border-b border-neutral-100 px-5 py-4">
            <div class="flex items-center gap-2">
                <slot name="icon" />
                <div>
                    <h2 class="text-sm font-semibold text-neutral-900">{{ title }}</h2>
                    <p v-if="subtitle" class="text-xs text-neutral-500">{{ subtitle }}</p>
                </div>
            </div>
            <slot name="actions" />
        </header>
        <div class="p-5">
            <slot />
        </div>
    </section>
</template>
