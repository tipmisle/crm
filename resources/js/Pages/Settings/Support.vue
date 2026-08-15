<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import { formatDateTime } from '@/lib/format';

interface Grant {
    id: number;
    expires_at: string;
}

interface HistoryEntry {
    id: number;
    granted_at: string;
    expires_at: string;
    revoked_at: string | null;
    grantedBy: { name: string } | null;
}

defineProps<{
    currentGrant: Grant | null;
    history: HistoryEntry[];
}>();

const durations = [
    { value: 30, label: '30 minut' },
    { value: 60, label: '1 ura' },
    { value: 240, label: '4 ure' },
];

const form = useForm({
    duration_minutes: 60,
});

function grant() {
    form.post(route('settings.support.store'), { preserveScroll: true });
}

function revoke() {
    if (!confirm('Prekliči dostop podpore do tega delovnega prostora?')) return;
    router.delete(route('settings.support.destroy'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Nastavitve · Podpora" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Podpora</h1>
        </template>

        <div class="mx-auto max-w-2xl space-y-4 px-4 py-6 sm:px-6">
            <SectionCard title="Dostop za podporo">
                <p class="mb-4 text-sm text-neutral-600">
                    Če pri reševanju težave potrebujemo vpogled v tvoj delovni prostor, lahko podpori začasno dovoliš
                    dostop do podatkov o strankah, naročilih, terminih in pogovorih. Dostop lahko kadarkoli
                    prekličeš, vsak tak vpogled pa se beleži.
                </p>

                <div v-if="currentGrant" class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">
                    <p>Dostop odobren do <strong>{{ formatDateTime(currentGrant.expires_at) }}</strong></p>
                    <button type="button" class="mt-2 rounded-md border border-emerald-300 bg-white px-3 py-1 text-xs font-medium text-emerald-800 hover:bg-emerald-100" @click="revoke">
                        Prekliči dostop
                    </button>
                </div>

                <form v-else class="space-y-4" @submit.prevent="grant">
                    <div>
                        <label class="mb-1 block text-xs font-medium text-neutral-600">Trajanje</label>
                        <select v-model.number="form.duration_minutes" class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm">
                            <option v-for="d in durations" :key="d.value" :value="d.value">{{ d.label }}</option>
                        </select>
                    </div>

                    <button type="submit" class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800" :disabled="form.processing">
                        Odobri dostop
                    </button>
                </form>
            </SectionCard>

            <SectionCard title="Zgodovina dostopov">
                <p v-if="history.length === 0" class="text-sm text-neutral-500">Ni pretekle zgodovine.</p>
                <ul v-else class="divide-y divide-neutral-100 text-sm">
                    <li v-for="h in history" :key="h.id" class="py-2">
                        <p class="text-neutral-800">Vpogled v podatke delovnega prostora</p>
                        <p class="text-xs text-neutral-500">
                            {{ formatDateTime(h.granted_at) }} → {{ formatDateTime(h.expires_at) }}
                            <span v-if="h.revoked_at"> · preklicano {{ formatDateTime(h.revoked_at) }}</span>
                        </p>
                    </li>
                </ul>
            </SectionCard>
        </div>
    </AppLayout>
</template>
