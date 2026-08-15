<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

interface PageLink {
    url: string | null;
    label: string;
    active: boolean;
}

defineProps<{ links: PageLink[] }>();
</script>

<template>
    <div v-if="links.length > 3" class="flex items-center justify-center gap-1 pt-4">
        <template v-for="(link, i) in links" :key="i">
            <span
                v-if="!link.url"
                class="rounded-md px-3 py-1.5 text-sm text-neutral-300"
                v-html="link.label"
            />
            <Link
                v-else
                :href="link.url"
                class="rounded-md px-3 py-1.5 text-sm"
                :class="link.active ? 'bg-[var(--color-ink-900)] text-white' : 'text-neutral-600 hover:bg-neutral-100'"
                v-html="link.label"
            />
        </template>
    </div>
</template>
