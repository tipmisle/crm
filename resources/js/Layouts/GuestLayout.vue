<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-neutral-50 px-4">
        <Link href="/" class="mb-6 flex items-center gap-2">
            <div
                class="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--color-accent-500)] text-base font-bold text-white"
            >
                B
            </div>
            <span class="text-base font-semibold text-neutral-900">Bloom &amp; Crumb</span>
        </Link>

        <div class="w-full max-w-sm rounded-xl border border-neutral-200 bg-white px-6 py-6 shadow-sm">
            <slot />
        </div>
    </div>
</template>
