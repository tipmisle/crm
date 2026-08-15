<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-neutral-50 px-4">
        <Link href="/" class="mb-6 flex items-center gap-2 rounded-lg bg-[var(--color-ink-950)] px-3 py-2">
            <img src="/images/logo.png" alt="Beležka" class="h-6 w-auto" />
        </Link>

        <div class="w-full max-w-sm rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] px-6 py-6">
            <slot />
        </div>
    </div>
</template>
