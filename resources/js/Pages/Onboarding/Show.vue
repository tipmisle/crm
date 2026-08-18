<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import CatalogItemModal from '@/Components/CatalogItemModal.vue';
import { channelMeta } from '@/lib/channels';
import { Check, ChevronLeft, ChevronRight, Sparkles } from 'lucide-vue-next';
import type { Channel, ChannelType, Workspace } from '@/types/models';

interface MetaPendingAccount {
    channel_type: ChannelType;
    external_account_id: string;
    display_name: string;
    username: string | null;
}

const props = defineProps<{
    workspace: Workspace;
    channels: Channel[];
    metaPendingAccounts: MetaPendingAccount[] | null;
    hasCatalogItems: boolean;
}>();

// Business name and the Orders/Appointments/deposit toggles are two
// separate forms because they post to Settings' two existing routes
// (settings.update / settings.capabilities.update) — same validation, same
// "Orders or Appointments" guard, no divergent onboarding-only rules.
// settings.update also requires email/timezone/currency, so those are
// carried through unchanged even though only the name is editable here.
const businessForm = useForm({
    name: props.workspace.name,
    email: props.workspace.email ?? '',
    timezone: props.workspace.timezone,
    currency: props.workspace.currency,
});

const capabilitiesForm = useForm({
    orders_enabled: props.workspace.orders_enabled,
    appointments_enabled: props.workspace.appointments_enabled,
    accepts_deposit: props.workspace.accepts_deposit,
});

const savingStep1 = computed(() => businessForm.processing || capabilitiesForm.processing);

function saveBusinessAndAdvance() {
    businessForm.patch(route('settings.update'), {
        preserveScroll: true,
        onSuccess: () =>
            capabilitiesForm.patch(route('settings.capabilities.update'), {
                preserveScroll: true,
                onSuccess: () => goTo(2),
            }),
    });
}

const businessDone = computed(() => Boolean(props.workspace.name) && (props.workspace.orders_enabled || props.workspace.appointments_enabled));

const connectedChannels = computed(() => props.channels.filter((c) => c.status === 'connected'));

function connectMeta() {
    window.location.href = route('integrations.meta.connect');
}

const selectedAccounts = ref<string[]>(props.metaPendingAccounts?.map((a) => a.external_account_id) ?? []);
const pendingForm = useForm({ external_account_ids: [] as string[] });

function connectSelected() {
    pendingForm.external_account_ids = selectedAccounts.value;
    pendingForm.post(route('integrations.meta.store'), { preserveScroll: true });
}

function cancelPending() {
    router.delete(route('integrations.meta.cancel'), { preserveScroll: true });
}

const completeForm = useForm({});

function complete() {
    completeForm.post(route('onboarding.complete'));
}

const catalogItemAdded = ref(props.hasCatalogItems);
const catalogModalOpen = ref(false);
const catalogModalKind = ref<'product' | 'service'>('product');

function openCatalogModal(kind: 'product' | 'service') {
    catalogModalKind.value = kind;
    catalogModalOpen.value = true;
}

function onCatalogItemSaved() {
    catalogItemAdded.value = true;
}

type StepId = 1 | 2 | 3;

const steps: { id: StepId; label: string; done: () => boolean }[] = [
    { id: 1, label: 'Podjetje', done: () => businessDone.value },
    { id: 2, label: 'Ponudba', done: () => catalogItemAdded.value },
    { id: 3, label: 'Sporočila', done: () => connectedChannels.value.length > 0 },
];

const step = ref<StepId>(1);

function goTo(target: StepId) {
    step.value = target;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}
</script>

<template>
    <Head title="Dobrodošli v Beležki" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Dobrodošli v Beležki</h1>
        </template>

        <div class="mx-auto max-w-xl px-4 py-10 sm:px-6 sm:py-14">
            <div class="mb-8 text-center">
                <div class="mx-auto mb-4 flex h-11 w-11 items-center justify-center rounded-2xl bg-[var(--color-accent-500)] shadow-sm shadow-[var(--color-accent-500)]/30">
                    <Sparkles class="h-5 w-5 text-white" />
                </div>
                <h1 class="text-2xl font-semibold text-neutral-900">Dobrodošli v Beležki</h1>
                <p class="mx-auto mt-1.5 max-w-sm text-sm text-neutral-500">
                    Par korakov, preden začneš — vse lahko kasneje spremeniš v Nastavitvah.
                </p>
            </div>

            <ol class="mb-8 flex items-start">
                <li v-for="(s, i) in steps" :key="s.id" class="flex flex-1 items-center last:flex-none">
                    <button
                        type="button"
                        class="flex flex-col items-center gap-1.5"
                        :class="s.id > step && !s.done() ? 'cursor-default' : 'cursor-pointer'"
                        :disabled="s.id > step && !s.done()"
                        @click="(s.id < step || s.done()) && goTo(s.id)"
                    >
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold transition"
                            :class="[
                                s.id === step
                                    ? 'bg-[var(--color-accent-500)] text-white ring-4 ring-[var(--color-accent-100)]'
                                    : s.done()
                                      ? 'bg-emerald-500 text-white'
                                      : 'bg-neutral-100 text-neutral-400',
                            ]"
                        >
                            <Check v-if="s.done() && s.id !== step" class="h-4 w-4" />
                            <template v-else>{{ s.id }}</template>
                        </span>
                        <span class="text-xs font-medium" :class="s.id === step ? 'text-neutral-900' : 'text-neutral-400'">
                            {{ s.label }}
                        </span>
                    </button>
                    <span v-if="i < steps.length - 1" class="mx-2 mt-4 h-px flex-1" :class="s.done() ? 'bg-emerald-300' : 'bg-neutral-200'" />
                </li>
            </ol>

            <section class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm shadow-neutral-900/[0.04] sm:p-8">
                <!-- Step 1 — Podjetje + način dela -->
                <div v-if="step === 1">
                    <h2 class="text-base font-semibold text-neutral-900">Podjetje in način dela</h2>
                    <p class="mt-1 text-sm text-neutral-500">Osnovni podatki, ki jih Beležka potrebuje za začetek.</p>

                    <form class="mt-6 space-y-5" @submit.prevent="saveBusinessAndAdvance">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ime podjetja</label>
                            <input
                                v-model="businessForm.name"
                                type="text"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-[var(--color-accent-400)] focus:ring-2 focus:ring-[var(--color-accent-100)]"
                            />
                            <p v-if="businessForm.errors.name" class="mt-1 text-xs text-red-500">{{ businessForm.errors.name }}</p>
                        </div>

                        <div class="space-y-2.5">
                            <label
                                class="flex items-start gap-3 rounded-lg border px-4 py-3 transition"
                                :class="capabilitiesForm.orders_enabled ? 'border-[var(--color-accent-300)] bg-[var(--color-accent-50)]' : 'border-neutral-200'"
                            >
                                <input v-model="capabilitiesForm.orders_enabled" type="checkbox" class="mt-0.5" />
                                <span>
                                    <span class="block text-sm font-medium text-neutral-900">Naročila</span>
                                    <span class="block text-xs text-neutral-500">Upravljaj naročila, roke, are in status izdelave.</span>
                                </span>
                            </label>
                            <label
                                class="flex items-start gap-3 rounded-lg border px-4 py-3 transition"
                                :class="
                                    capabilitiesForm.appointments_enabled ? 'border-[var(--color-accent-300)] bg-[var(--color-accent-50)]' : 'border-neutral-200'
                                "
                            >
                                <input v-model="capabilitiesForm.appointments_enabled" type="checkbox" class="mt-0.5" />
                                <span>
                                    <span class="block text-sm font-medium text-neutral-900">Termini</span>
                                    <span class="block text-xs text-neutral-500">Upravljaj termine, storitve, datume, ure in zgodovino strank.</span>
                                </span>
                            </label>
                            <label
                                class="flex items-start gap-3 rounded-lg border px-4 py-3 transition"
                                :class="capabilitiesForm.accepts_deposit ? 'border-[var(--color-accent-300)] bg-[var(--color-accent-50)]' : 'border-neutral-200'"
                            >
                                <input v-model="capabilitiesForm.accepts_deposit" type="checkbox" class="mt-0.5" />
                                <span>
                                    <span class="block text-sm font-medium text-neutral-900">Sprejemam aro</span>
                                    <span class="block text-xs text-neutral-500">Če izklopiš, se polje za aro skrije pri podrobnostih naročila.</span>
                                </span>
                            </label>
                            <p v-if="capabilitiesForm.errors.appointments_enabled" class="text-xs text-red-500">
                                {{ capabilitiesForm.errors.appointments_enabled }}
                            </p>
                        </div>

                        <div class="flex justify-end pt-2">
                            <button
                                type="submit"
                                :disabled="savingStep1"
                                class="inline-flex items-center gap-1.5 rounded-md bg-[var(--color-ink-900)] px-5 py-2.5 text-sm font-medium text-white transition hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                            >
                                Naprej
                                <ChevronRight class="h-4 w-4" />
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Step 2 — Ponudba -->
                <div v-else-if="step === 2">
                    <h2 class="text-base font-semibold text-neutral-900">Ponudba</h2>
                    <p class="mt-1 text-sm text-neutral-500">Dodaj vsaj en produkt ali storitev, da boš lahko ustvarjal-a naročila in termine.</p>

                    <div class="mt-6">
                        <div v-if="catalogItemAdded" class="flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
                            <Check class="h-4 w-4" />
                            Ponudba je nastavljena.
                        </div>
                        <div v-else class="flex flex-wrap gap-2">
                            <button
                                v-if="capabilitiesForm.orders_enabled"
                                type="button"
                                class="rounded-md border border-neutral-200 px-4 py-2 text-sm font-medium text-neutral-700 transition hover:border-neutral-300 hover:bg-neutral-50"
                                @click="openCatalogModal('product')"
                            >
                                Dodaj prvi produkt
                            </button>
                            <button
                                v-if="capabilitiesForm.appointments_enabled"
                                type="button"
                                class="rounded-md border border-neutral-200 px-4 py-2 text-sm font-medium text-neutral-700 transition hover:border-neutral-300 hover:bg-neutral-50"
                                @click="openCatalogModal('service')"
                            >
                                Dodaj prvo storitev
                            </button>
                        </div>
                    </div>

                    <div class="mt-8 flex items-center justify-between">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium text-neutral-500 transition hover:bg-neutral-50 hover:text-neutral-700"
                            @click="goTo(1)"
                        >
                            <ChevronLeft class="h-4 w-4" />
                            Nazaj
                        </button>
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-md bg-[var(--color-ink-900)] px-5 py-2.5 text-sm font-medium text-white transition hover:bg-[var(--color-ink-800)]"
                            @click="goTo(3)"
                        >
                            Naprej
                            <ChevronRight class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- Step 3 — Poveži sporočila -->
                <div v-else>
                    <h2 class="text-base font-semibold text-neutral-900">Poveži sporočila</h2>
                    <p class="mt-1 text-sm text-neutral-500">Neobvezno — to lahko urediš kadarkoli kasneje v Nastavitvah.</p>

                    <div class="mt-6">
                        <div v-if="connectedChannels.length" class="space-y-2">
                            <div
                                v-for="channel in connectedChannels"
                                :key="channel.id"
                                class="flex items-center gap-3 rounded-lg border border-neutral-200 px-4 py-3"
                            >
                                <ChannelIcon :type="channel.type" size="sm" />
                                <p class="text-sm font-medium text-neutral-900">{{ channel.display_name }}</p>
                                <span class="ml-auto rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Povezano</span>
                            </div>
                        </div>

                        <div
                            v-else-if="metaPendingAccounts?.length"
                            class="rounded-lg border border-[var(--color-accent-200)] bg-[var(--color-accent-50)] p-4"
                        >
                            <p class="text-sm font-medium text-neutral-900">Izberi račune, ki jih želiš povezati</p>
                            <div class="mt-3 space-y-2">
                                <label
                                    v-for="account in metaPendingAccounts"
                                    :key="account.external_account_id"
                                    class="flex items-center gap-3 rounded-md bg-white px-3 py-2 text-sm"
                                >
                                    <input type="checkbox" :value="account.external_account_id" v-model="selectedAccounts" />
                                    <ChannelIcon :type="account.channel_type" size="sm" />
                                    <span class="font-medium text-neutral-900">{{ account.display_name }}</span>
                                    <span v-if="account.username" class="text-neutral-400">@{{ account.username }}</span>
                                    <span class="ml-auto text-xs text-neutral-400">{{ channelMeta(account.channel_type).label }}</span>
                                </label>
                            </div>
                            <div class="mt-3 flex justify-end gap-2">
                                <button
                                    type="button"
                                    class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-white"
                                    @click="cancelPending"
                                >
                                    Prekliči
                                </button>
                                <button
                                    type="button"
                                    :disabled="!selectedAccounts.length || pendingForm.processing"
                                    class="rounded-md bg-[var(--color-ink-900)] px-3 py-1.5 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                                    @click="connectSelected"
                                >
                                    Poveži izbrane
                                </button>
                            </div>
                        </div>

                        <button
                            v-else
                            type="button"
                            class="rounded-md border border-neutral-200 px-4 py-2 text-sm font-medium text-neutral-700 transition hover:border-neutral-300 hover:bg-neutral-50"
                            @click="connectMeta"
                        >
                            Poveži Instagram ali Facebook
                        </button>
                    </div>

                    <div class="mt-8 flex items-center justify-between">
                        <button
                            type="button"
                            class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium text-neutral-500 transition hover:bg-neutral-50 hover:text-neutral-700"
                            @click="goTo(2)"
                        >
                            <ChevronLeft class="h-4 w-4" />
                            Nazaj
                        </button>
                        <button
                            type="button"
                            :disabled="completeForm.processing"
                            class="inline-flex items-center gap-1.5 rounded-md bg-[var(--color-ink-900)] px-5 py-2.5 text-sm font-medium text-white transition hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                            @click="complete"
                        >
                            Začni uporabljati Beležko
                        </button>
                    </div>
                </div>
            </section>
        </div>

        <CatalogItemModal v-model:open="catalogModalOpen" :kind="catalogModalKind" @saved="onCatalogItemSaved" />
    </AppLayout>
</template>
