<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import { Check } from 'lucide-vue-next';

const props = defineProps<{
    isOwner: boolean;
    displayPrice: string | null;
    billingPeriodLabel: string;
    displayPriceVatIncluded: boolean | null;
    state: string;
}>();

const vatNote = computed(() => {
    if (!props.displayPrice || props.displayPriceVatIncluded === null) return null;

    return props.displayPriceVatIncluded ? 'Cena vključuje DDV.' : 'Cena ne vključuje DDV.';
});

const form = useForm({});

function startCheckout() {
    form.post(route('billing.activate.checkout'));
}

const features = [
    'Instagram in Facebook Messenger sporočila na enem mestu',
    'Evidenca strank in zgodovina pogovorov',
    'Naročila in termini',
    'Katalog izdelkov in storitev',
];
</script>

<template>
    <Head title="Aktiviraj naročnino — Beležka" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Aktivacija</h1>
        </template>

        <div class="mx-auto max-w-2xl space-y-4 px-4 py-6 sm:px-6">
            <SectionCard title="Še en korak in Beležka je pripravljena.">
                <p class="mb-4 text-sm text-neutral-600">
                    Za uporabo Beležke aktiviraj naročnino. Plačilo poteka varno prek Stripe — Beležka ne shranjuje
                    podatkov o tvoji kartici.
                </p>

                <div class="mb-4 rounded-lg border border-neutral-200 bg-neutral-50 p-4">
                    <p v-if="displayPrice" class="text-2xl font-semibold text-neutral-900">
                        {{ displayPrice }} <span class="text-sm font-normal text-neutral-500">/ {{ billingPeriodLabel }}</span>
                    </p>
                    <p v-else class="text-sm text-neutral-500">Cena bo prikazana ob nadaljevanju na plačilo.</p>
                    <p v-if="vatNote" class="mt-1 text-xs text-neutral-500">{{ vatNote }}</p>

                    <ul class="mt-3 space-y-1.5 text-sm text-neutral-700">
                        <li v-for="f in features" :key="f" class="flex items-start gap-2">
                            <Check :size="15" class="mt-0.5 shrink-0 text-emerald-600" />
                            {{ f }}
                        </li>
                    </ul>
                </div>

                <button
                    v-if="isOwner"
                    type="button"
                    class="w-full rounded-md bg-[var(--color-ink-900)] px-4 py-2.5 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                    :disabled="form.processing"
                    @click="startCheckout"
                >
                    Nadaljuj na plačilo
                </button>
                <p v-else class="text-sm text-neutral-500">Samo lastnik delovnega prostora lahko aktivira naročnino.</p>
            </SectionCard>
        </div>
    </AppLayout>
</template>
