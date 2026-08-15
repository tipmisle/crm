<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Sparkles } from 'lucide-vue-next';
</script>

<template>
    <div
        class="flex shrink-0 flex-wrap items-center justify-center gap-x-3 gap-y-1 bg-[var(--color-accent-500)] px-4 py-2 text-center text-xs font-medium text-white sm:text-sm"
    >
        <span class="inline-flex items-center gap-1.5">
            <Sparkles :size="14" class="shrink-0" />
            To je demo Beležke z vzorčnimi podatki. Prosto klikaj in preizkusi.
        </span>
        <Link
            :href="route('register')"
            class="shrink-0 rounded-md bg-white/15 px-2.5 py-1 font-semibold whitespace-nowrap text-white transition hover:bg-white/25"
        >
            Ustvari svoj račun
        </Link>
    </div>
</template>
