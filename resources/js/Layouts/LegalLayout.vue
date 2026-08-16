<script setup lang="ts">
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import LegalToc from '@/Components/Legal/LegalToc.vue';

interface TocItem {
    id: string;
    label: string;
}

withDefaults(
    defineProps<{
        title: string;
        updatedAt?: string;
        toc?: TocItem[];
    }>(),
    { updatedAt: undefined, toc: () => [] },
);
</script>

<template>
    <MarketingLayout>
        <div class="legal-page">
            <div class="mx-auto max-w-3xl px-4 py-12 sm:px-6 sm:py-16">
                <header class="mb-10 border-b border-neutral-200 pb-6">
                    <h1 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">{{ title }}</h1>
                    <p v-if="updatedAt" class="mt-2 text-sm text-neutral-500">Zadnja posodobitev: {{ updatedAt }}</p>
                </header>

                <LegalToc v-if="toc.length" :items="toc" />

                <div class="legal-prose space-y-10 text-[15px] leading-relaxed text-neutral-600">
                    <slot />
                </div>
            </div>
        </div>
    </MarketingLayout>
</template>

<style scoped>
.legal-prose :deep(h2) {
    scroll-margin-top: 5rem;
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--color-ink-950, #241a42);
    letter-spacing: -0.01em;
}

.legal-prose :deep(h3) {
    scroll-margin-top: 5rem;
    font-size: 1rem;
    font-weight: 600;
    color: #262626;
}

.legal-prose :deep(p) {
    margin-top: 0.5rem;
}

.legal-prose :deep(ul) {
    margin-top: 0.5rem;
    list-style: disc;
    padding-left: 1.25rem;
}

.legal-prose :deep(table) {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.875rem;
}

.legal-prose :deep(th),
.legal-prose :deep(td) {
    border: 1px solid #e5e5e5;
    padding: 0.5rem 0.75rem;
    text-align: left;
    vertical-align: top;
}

.legal-prose :deep(th) {
    background: #fafafa;
    font-weight: 600;
}
</style>
