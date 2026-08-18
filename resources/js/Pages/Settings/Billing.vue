<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import { formatDate } from '@/lib/format';
import { Download, Check } from 'lucide-vue-next';

interface SubscriptionInvoice {
    id: string;
    date: string;
    total: string;
    paid: boolean;
    pdfUrl: string | null;
}

const props = defineProps<{
    isOwner: boolean;
    state: string;
    stateLabel: string;
    displayPrice: string | null;
    billingPeriodLabel: string;
    periodEndsAt: string | null;
    cancelAtPeriodEnd: boolean;
    pmType: string | null;
    pmLastFour: string | null;
    invoices: SubscriptionInvoice[];
}>();

const badgeClass: Record<string, string> = {
    active: 'bg-emerald-50 text-emerald-700',
    canceling: 'bg-amber-50 text-amber-700',
    past_due: 'bg-red-50 text-red-700',
    incomplete: 'bg-neutral-100 text-neutral-500',
    canceled: 'bg-neutral-100 text-neutral-500',
    no_subscription: 'bg-neutral-100 text-neutral-500',
};

const features = [
    'Instagram in Facebook Messenger sporočila na enem mestu',
    'Evidenca strank in zgodovina pogovorov',
    'Naročila in termini',
    'Katalog izdelkov in storitev',
];
</script>

<template>
    <Head title="Nastavitve · Naročnina" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Naročnina</h1>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                <SectionCard title="Naročnina">
                    <div
                        class="mb-4 flex items-center gap-2 rounded-lg px-3 py-2.5 text-sm font-medium"
                        :class="badgeClass[state] ?? badgeClass.no_subscription"
                    >
                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-current" />
                        {{ stateLabel }}
                    </div>

                    <dl class="space-y-2 text-sm">
                        <div v-if="displayPrice" class="flex justify-between">
                            <dt class="text-neutral-500">Cena</dt>
                            <dd class="text-neutral-800">{{ displayPrice }} / {{ billingPeriodLabel }}</dd>
                        </div>
                        <div v-if="cancelAtPeriodEnd && periodEndsAt" class="flex justify-between">
                            <dt class="text-neutral-500">Poteče</dt>
                            <dd class="text-neutral-800">{{ formatDate(periodEndsAt) }}</dd>
                        </div>
                        <div v-if="pmType" class="flex justify-between">
                            <dt class="text-neutral-500">Način plačila</dt>
                            <dd class="text-neutral-800 capitalize">{{ pmType }} •••• {{ pmLastFour }}</dd>
                        </div>
                    </dl>

                    <ul v-if="state === 'no_subscription'" class="mt-4 space-y-1.5 border-t border-neutral-100 pt-4 text-sm text-neutral-700">
                        <li v-for="f in features" :key="f" class="flex items-start gap-2">
                            <Check :size="15" class="mt-0.5 shrink-0 text-emerald-600" />
                            {{ f }}
                        </li>
                    </ul>

                    <div v-if="isOwner" class="mt-5">
                        <a
                            v-if="state !== 'no_subscription'"
                            :href="route('settings.billing.portal')"
                            class="inline-flex rounded-md border border-neutral-300 px-4 py-2 text-sm font-medium text-neutral-700 hover:bg-neutral-50"
                        >
                            Upravljaj naročnino
                        </a>
                        <Link
                            v-else
                            :href="route('billing.activate')"
                            class="inline-flex rounded-md bg-[var(--color-ink-900)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)]"
                        >
                            Aktiviraj naročnino
                        </Link>
                    </div>
                    <p v-else class="mt-4 text-sm text-neutral-500">Samo lastnik delovnega prostora lahko upravlja naročnino.</p>
                </SectionCard>

                <SectionCard title="Računi za naročnino" subtitle="Kar ti zaračuna Beležka za mesečno naročnino">
                    <ul v-if="invoices.length" class="divide-y divide-neutral-100 text-sm">
                        <li v-for="invoice in invoices" :key="invoice.id" class="flex items-center justify-between py-2.5">
                            <div>
                                <p class="font-medium text-neutral-800">{{ formatDate(invoice.date) }}</p>
                                <p v-if="!invoice.paid" class="text-xs text-amber-600">Ni plačano</p>
                            </div>
                            <div class="flex items-center gap-4">
                                <span class="text-neutral-600">{{ invoice.total }}</span>
                                <a
                                    v-if="invoice.pdfUrl"
                                    :href="invoice.pdfUrl"
                                    target="_blank"
                                    rel="noopener"
                                    class="flex items-center gap-1 text-xs font-medium text-neutral-500 hover:text-neutral-800"
                                    title="Prenesi PDF"
                                >
                                    <Download :size="13" />
                                </a>
                            </div>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-neutral-500">Še ni izdanih računov za naročnino.</p>
                    <p class="mt-3 text-xs text-neutral-400">
                        To so Stripova potrdila o plačilu naročnine za Beležko, ne računi, ki jih ti izdajaš svojim strankam.
                    </p>
                </SectionCard>
                </div>

                <div class="lg:col-span-1">
                    <div class="lg:sticky lg:top-6 space-y-4">
                        <SectionCard title="Potrebuješ pomoč?">
                            <p class="text-sm text-neutral-600">
                                Za vprašanja o naročnini, plačilih ali računih se lahko kadarkoli obrneš na podporo.
                            </p>
                            <Link
                                :href="route('settings.support.edit')"
                                class="mt-3 inline-flex text-sm font-medium text-neutral-800 underline decoration-neutral-300 underline-offset-2 hover:text-neutral-900"
                            >
                                Pojdi na podporo
                            </Link>
                        </SectionCard>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
