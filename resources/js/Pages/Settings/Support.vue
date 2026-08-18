<script setup lang="ts">
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import { useConfirm } from '@/composables/useConfirm';
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

interface BugReport {
    id: number;
    subject: string;
    status: 'open' | 'resolved';
    created_at: string;
}

interface FeatureRequest {
    id: number;
    subject: string;
    status: 'open' | 'planned' | 'done';
    created_at: string;
}

defineProps<{
    currentGrant: Grant | null;
    history: HistoryEntry[];
    bugReports: BugReport[];
    featureRequests: FeatureRequest[];
}>();

const { confirm } = useConfirm();

const bugForm = useForm({
    subject: '',
    message: '',
    page_url: '',
});

function submitBugReport() {
    bugForm.page_url = window.location.origin + '/settings/support';
    bugForm.post(route('settings.support.bug-reports.store'), {
        preserveScroll: true,
        onSuccess: () => bugForm.reset(),
    });
}

const bugStatusLabel: Record<string, string> = {
    open: 'Odprto',
    resolved: 'Rešeno',
};

const bugStatusClass: Record<string, string> = {
    open: 'bg-amber-50 text-amber-700',
    resolved: 'bg-emerald-50 text-emerald-700',
};

const featureForm = useForm({
    subject: '',
    message: '',
});

function submitFeatureRequest() {
    featureForm.post(route('settings.support.feature-requests.store'), {
        preserveScroll: true,
        onSuccess: () => featureForm.reset(),
    });
}

const featureStatusLabel: Record<string, string> = {
    open: 'Predlagano',
    planned: 'Načrtovano',
    done: 'Izvedeno',
};

const featureStatusClass: Record<string, string> = {
    open: 'bg-neutral-100 text-neutral-600',
    planned: 'bg-amber-50 text-amber-700',
    done: 'bg-emerald-50 text-emerald-700',
};

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

async function revoke() {
    if (!(await confirm('Prekliči dostop podpore do tega delovnega prostora?'))) return;
    router.delete(route('settings.support.destroy'), { preserveScroll: true });
}
</script>

<template>
    <Head title="Nastavitve · Podpora" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Podpora</h1>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <SectionCard title="Prijavi napako" subtitle="Opiši težavo, na katero si naletel — sporočilo gre neposredno naši podpori.">
                        <form class="space-y-4" @submit.prevent="submitBugReport">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-neutral-600">Naslov</label>
                                <input
                                    v-model="bugForm.subject"
                                    type="text"
                                    maxlength="150"
                                    placeholder="Na kratko opiši težavo"
                                    class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm"
                                />
                                <p v-if="bugForm.errors.subject" class="mt-1 text-xs text-red-600">{{ bugForm.errors.subject }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-neutral-600">Opis</label>
                                <textarea
                                    v-model="bugForm.message"
                                    rows="4"
                                    maxlength="5000"
                                    placeholder="Kaj si počel, kaj si pričakoval in kaj se je dejansko zgodilo?"
                                    class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm"
                                />
                                <p v-if="bugForm.errors.message" class="mt-1 text-xs text-red-600">{{ bugForm.errors.message }}</p>
                            </div>
                            <button
                                type="submit"
                                class="rounded-md bg-[var(--color-ink-900)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                                :disabled="bugForm.processing"
                            >
                                Pošlji prijavo
                            </button>
                        </form>
                    </SectionCard>

                    <SectionCard title="Moje prijave">
                        <p v-if="bugReports.length === 0" class="text-sm text-neutral-500">Še ni prijavljenih napak.</p>
                        <ul v-else class="divide-y divide-neutral-100 text-sm">
                            <li v-for="b in bugReports" :key="b.id" class="flex items-center justify-between py-2.5">
                                <div>
                                    <p class="text-neutral-800">{{ b.subject }}</p>
                                    <p class="text-xs text-neutral-500">{{ formatDateTime(b.created_at) }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="bugStatusClass[b.status]">
                                    {{ bugStatusLabel[b.status] }}
                                </span>
                            </li>
                        </ul>
                    </SectionCard>

                    <SectionCard title="Predlagaj funkcionalnost" subtitle="Kaj bi Beležki manjkalo, da bi bila zate še boljša?">
                        <form class="space-y-4" @submit.prevent="submitFeatureRequest">
                            <div>
                                <label class="mb-1 block text-xs font-medium text-neutral-600">Naslov</label>
                                <input
                                    v-model="featureForm.subject"
                                    type="text"
                                    maxlength="150"
                                    placeholder="Na kratko opiši predlog"
                                    class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm"
                                />
                                <p v-if="featureForm.errors.subject" class="mt-1 text-xs text-red-600">{{ featureForm.errors.subject }}</p>
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-medium text-neutral-600">Opis</label>
                                <textarea
                                    v-model="featureForm.message"
                                    rows="4"
                                    maxlength="5000"
                                    placeholder="Kaj bi ta funkcionalnost omogočala in zakaj bi ti koristila?"
                                    class="w-full rounded-md border border-neutral-300 px-3 py-2 text-sm"
                                />
                                <p v-if="featureForm.errors.message" class="mt-1 text-xs text-red-600">{{ featureForm.errors.message }}</p>
                            </div>
                            <button
                                type="submit"
                                class="rounded-md bg-[var(--color-ink-900)] px-4 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                                :disabled="featureForm.processing"
                            >
                                Pošlji predlog
                            </button>
                        </form>
                    </SectionCard>

                    <SectionCard title="Moji predlogi">
                        <p v-if="featureRequests.length === 0" class="text-sm text-neutral-500">Še ni poslanih predlogov.</p>
                        <ul v-else class="divide-y divide-neutral-100 text-sm">
                            <li v-for="f in featureRequests" :key="f.id" class="flex items-center justify-between py-2.5">
                                <div>
                                    <p class="text-neutral-800">{{ f.subject }}</p>
                                    <p class="text-xs text-neutral-500">{{ formatDateTime(f.created_at) }}</p>
                                </div>
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :class="featureStatusClass[f.status]">
                                    {{ featureStatusLabel[f.status] }}
                                </span>
                            </li>
                        </ul>
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

                <div class="lg:col-span-1">
                    <div class="lg:sticky lg:top-6">
                        <SectionCard title="Dostop za podporo">
                            <p class="mb-4 text-sm text-neutral-600">
                                Če pri reševanju težave potrebujemo vpogled v tvoj delovni prostor, lahko podpori
                                začasno dovoliš dostop do podatkov o strankah, naročilih, terminih in pogovorih.
                                Dostop lahko kadarkoli prekličeš, vsak tak vpogled pa se beleži.
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
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
