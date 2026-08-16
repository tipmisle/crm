<script setup lang="ts">
defineProps<{
    items: { id: string; label: string }[];
}>();
</script>

<template>
    <nav class="legal-page-chrome mb-10 rounded-lg border border-neutral-200 bg-neutral-50 p-4 print:hidden" aria-label="Vsebina dokumenta">
        <p class="mb-2 text-xs font-semibold tracking-wide text-neutral-400 uppercase">Vsebina</p>
        <ol class="grid grid-cols-1 gap-x-6 gap-y-1.5 text-sm text-neutral-600 sm:grid-cols-2">
            <li v-for="item in items" :key="item.id">
                <a :href="`#${item.id}`" class="hover:text-[var(--color-accent-600)] hover:underline">{{ item.label }}</a>
            </li>
        </ol>
    </nav>
</template>
