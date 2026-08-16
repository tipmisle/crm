<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import { formatDate } from '@/lib/format';

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
}>();

const badgeClass: Record<string, string> = {
    active: 'bg-emerald-50 text-emerald-700',
    canceling: 'bg-amber-50 text-amber-700',
    past_due: 'bg-red-50 text-red-700',
    incomplete: 'bg-neutral-100 text-neutral-500',
    canceled: 'bg-neutral-100 text-neutral-500',
    no_subscription: 'bg-neutral-100 text-neutral-500',
};
</script>

<template>
    <Head title="Nastavitve · Naročnina" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Naročnina</h1>
        </template>

        <div class="mx-auto max-w-2xl space-y-4 px-4 py-6 sm:px-6">
            <SectionCard title="Naročnina">
                <div class="mb-4 flex items-center gap-2">
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="badgeClass[state] ?? badgeClass.no_subscription">
                        {{ stateLabel }}
                    </span>
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
        </div>
    </AppLayout>
</template>
