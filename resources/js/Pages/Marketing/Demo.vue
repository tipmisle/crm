<script setup lang="ts">
import { ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import MarketingLayout from '@/Layouts/MarketingLayout.vue';
import Reveal from '@/Components/Marketing/Reveal.vue';
import { CalendarDays, Package, ArrowRight, LoaderCircle } from 'lucide-vue-next';
import { trackDemoVariantSelected } from '@/lib/analytics';

type Variant = 'services' | 'orders' | 'both';

const submitting = ref(false);
const loadingVariant = ref<Variant | null>(null);

const variants: {
    key: Variant;
    title: string;
    examples: string;
}[] = [
    { key: 'services', title: 'Storitve', examples: 'Frizerstvo, nohti, trepalnice, kozmetika …' },
    { key: 'orders', title: 'Naročila', examples: 'Torte, cvetje, darila po meri, ročno izdelani izdelki …' },
    { key: 'both', title: 'Storitve + naročila', examples: 'Fotografija, poročne storitve in drugi posli, kjer sprejemaš oboje.' },
];

function startDemo(variant: Variant) {
    if (submitting.value) return;

    trackDemoVariantSelected(variant);

    submitting.value = true;
    loadingVariant.value = variant;

    router.post(
        route('demo.create', variant),
        {},
        {
            onError: () => {
                submitting.value = false;
                loadingVariant.value = null;
            },
        },
    );
}
</script>

<template>
    <Head title="Preizkusi demo — Beležka">
        <meta name="description" content="Preizkusi Beležko z vzorčnimi strankami, pogovori in podatki — brez registracije." />
    </Head>

    <MarketingLayout>
        <section class="bg-neutral-50 py-20 sm:py-28">
            <div class="mx-auto max-w-4xl px-4 text-center sm:px-6">
                <Reveal>
                    <h1 class="text-3xl font-semibold tracking-tight text-neutral-900 sm:text-4xl">
                        Kateri posel je najbolj podoben tvojemu?
                    </h1>
                </Reveal>

                <Reveal :delay="80">
                    <p class="mx-auto mt-4 max-w-lg text-[15px] text-neutral-500">
                        Izberi primer in pripravljamo ti demo Beležke z vzorčnimi strankami, pogovori in podatki.
                    </p>
                </Reveal>

                <div class="mt-14 grid grid-cols-1 gap-5 sm:grid-cols-3">
                    <Reveal v-for="(variant, i) in variants" :key="variant.key" :delay="140 + i * 60">
                        <button
                            type="button"
                            class="flex h-full w-full flex-col items-start rounded-2xl border border-neutral-200 bg-white p-6 text-left shadow-sm shadow-neutral-900/[0.04] transition hover:-translate-y-0.5 hover:border-[var(--color-accent-200)] hover:shadow-md disabled:cursor-not-allowed disabled:opacity-60 disabled:hover:translate-y-0"
                            :disabled="submitting"
                            @click="startDemo(variant.key)"
                        >
                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-[var(--color-accent-50)] text-[var(--color-accent-600)]">
                                <CalendarDays v-if="variant.key === 'services'" :size="18" />
                                <Package v-else-if="variant.key === 'orders'" :size="18" />
                                <span v-else class="flex items-center -space-x-1">
                                    <CalendarDays :size="15" />
                                    <Package :size="15" />
                                </span>
                            </span>

                            <h2 class="mt-4 text-lg font-semibold text-neutral-900">{{ variant.title }}</h2>
                            <p class="mt-1.5 text-sm text-neutral-500">{{ variant.examples }}</p>

                            <span class="mt-5 inline-flex items-center gap-1.5 text-sm font-semibold text-[var(--color-accent-600)]">
                                <LoaderCircle v-if="loadingVariant === variant.key" :size="14" class="animate-spin" />
                                <template v-else>
                                    Preizkusi demo
                                    <ArrowRight :size="14" />
                                </template>
                            </span>
                        </button>
                    </Reveal>
                </div>
            </div>
        </section>

        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150 ease-in"
            leave-to-class="opacity-0"
        >
            <div v-if="submitting" class="fixed inset-0 z-50 flex items-center justify-center bg-[var(--color-ink-950)]/70 backdrop-blur-sm">
                <div class="mx-4 max-w-sm rounded-2xl bg-white p-8 text-center shadow-2xl">
                    <LoaderCircle :size="28" class="mx-auto animate-spin text-[var(--color-accent-500)]" />
                    <p class="mt-4 text-base font-semibold text-neutral-900">Pripravljamo tvojo demo Beležko …</p>
                    <p class="mt-2 text-sm text-neutral-500">Polnimo jo z vzorčnimi strankami, pogovori in naročili.</p>
                </div>
            </div>
        </Transition>
    </MarketingLayout>
</template>
