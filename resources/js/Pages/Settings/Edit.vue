<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import { channelMeta } from '@/lib/channels';
import { relativeTime, formatMoney } from '@/lib/format';
import { Plus, Trash2 } from 'lucide-vue-next';
import type { Channel, ChannelType, Service, Workspace } from '@/types/models';

interface MetaPendingAccount {
    channel_type: ChannelType;
    external_account_id: string;
    display_name: string;
    username: string | null;
}

const props = defineProps<{
    workspace: Workspace;
    channels: Channel[];
    services: Service[];
    metaPendingAccounts: MetaPendingAccount[] | null;
}>();

const form = useForm({
    name: props.workspace.name,
    email: props.workspace.email ?? '',
    timezone: props.workspace.timezone,
    currency: props.workspace.currency,
});

function submit() {
    form.patch(route('settings.update'));
}

const capabilitiesForm = useForm({
    orders_enabled: props.workspace.orders_enabled,
    appointments_enabled: props.workspace.appointments_enabled,
});

function saveCapabilities() {
    capabilitiesForm.patch(route('settings.capabilities.update'), { preserveScroll: true });
}

const newServiceForm = useForm({
    name: '',
    default_duration_minutes: 60,
    default_price: '',
    default_deposit_amount: '',
});

function addService() {
    newServiceForm.post(route('services.store'), {
        preserveScroll: true,
        onSuccess: () => newServiceForm.reset(),
    });
}

function toggleServiceActive(service: Service) {
    router.patch(route('services.update', service.id), { active: !service.active }, { preserveScroll: true });
}

function deleteService(service: Service) {
    if (!confirm(`Izbriši storitev "${service.name}"?`)) return;
    router.delete(route('services.destroy', service.id), { preserveScroll: true });
}

const timezones = ['Europe/Ljubljana', 'Europe/London', 'Europe/Berlin', 'America/New_York', 'America/Los_Angeles', 'UTC'];
const currencies = ['EUR', 'USD', 'GBP'];

const META_TYPES: ChannelType[] = ['instagram', 'facebook_messenger'];

function channelsOfType(type: ChannelType) {
    return props.channels.filter((c) => c.type === type);
}

function connectMeta() {
    window.location.href = route('integrations.meta.connect');
}

function disconnectChannel(channel: Channel) {
    if (!confirm(`Odklopi ${channel.display_name}? Pretekli pogovori, stranke in naročila ostanejo shranjeni.`)) return;
    router.delete(route('integrations.meta.disconnect', channel.id), { preserveScroll: true });
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
</script>

<template>
    <Head title="Nastavitve" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Nastavitve</h1>
        </template>

        <div class="mx-auto max-w-3xl space-y-6 px-6 py-8">
            <h1 class="text-2xl font-semibold text-neutral-900">Nastavitve</h1>

            <SectionCard title="Podjetje" subtitle="Osnovni podatki o tvojem podjetju">
                <form class="space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Ime podjetja</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full max-w-sm rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                    </div>

                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-neutral-700">Kontaktni e-naslov</label>
                        <input
                            v-model="form.email"
                            type="email"
                            class="w-full max-w-sm rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                    </div>

                    <div class="grid max-w-sm grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Časovni pas</label>
                            <select v-model="form.timezone" class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none">
                                <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-neutral-700">Valuta</label>
                            <select v-model="form.currency" class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none">
                                <option v-for="c in currencies" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800 disabled:opacity-50"
                    >
                        Shrani spremembe
                    </button>
                </form>
            </SectionCard>

            <SectionCard title="Kako upravljaš stranke?" subtitle="Vklopi funkcije, ki jih tvoje podjetje potrebuje">
                <div class="space-y-3">
                    <label class="flex items-start gap-3 rounded-lg border border-neutral-200 px-4 py-3">
                        <input v-model="capabilitiesForm.orders_enabled" type="checkbox" class="mt-0.5" />
                        <span>
                            <span class="block text-sm font-medium text-neutral-900">Naročila</span>
                            <span class="block text-xs text-neutral-500">Upravljaj naročila, roke, are in status izdelave.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-lg border border-neutral-200 px-4 py-3">
                        <input v-model="capabilitiesForm.appointments_enabled" type="checkbox" class="mt-0.5" />
                        <span>
                            <span class="block text-sm font-medium text-neutral-900">Termini</span>
                            <span class="block text-xs text-neutral-500">Upravljaj termine, storitve, datume, ure in zgodovino strank.</span>
                        </span>
                    </label>
                </div>
                <p v-if="capabilitiesForm.errors.appointments_enabled" class="mt-2 text-xs text-red-500">
                    {{ capabilitiesForm.errors.appointments_enabled }}
                </p>
                <button
                    type="button"
                    :disabled="capabilitiesForm.processing"
                    class="mt-4 rounded-md bg-neutral-900 px-4 py-2 text-sm font-medium text-white hover:bg-neutral-800 disabled:opacity-50"
                    @click="saveCapabilities"
                >
                    Shrani način poslovanja
                </button>
            </SectionCard>

            <SectionCard v-if="workspace.appointments_enabled" title="Storitve" subtitle="Vnaprej pripravljene storitve za hitro rezervacijo terminov">
                <div class="space-y-2">
                    <div
                        v-for="service in services"
                        :key="service.id"
                        class="flex items-center justify-between rounded-lg border border-neutral-200 px-4 py-3"
                        :class="!service.active && 'opacity-50'"
                    >
                        <div>
                            <p class="text-sm font-medium text-neutral-900">{{ service.name }}</p>
                            <p class="text-xs text-neutral-500">
                                {{ service.default_duration_minutes }} min
                                <span v-if="service.default_price !== null"> · {{ formatMoney(service.default_price) }}</span>
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <button
                                type="button"
                                class="text-xs font-medium text-neutral-500 hover:text-neutral-700"
                                @click="toggleServiceActive(service)"
                            >
                                {{ service.active ? 'Deaktiviraj' : 'Aktiviraj' }}
                            </button>
                            <button type="button" class="text-neutral-400 hover:text-red-600" @click="deleteService(service)">
                                <Trash2 :size="14" />
                            </button>
                        </div>
                    </div>
                    <p v-if="!services.length" class="rounded-lg border border-dashed border-neutral-200 px-4 py-3 text-sm text-neutral-400">
                        Še ni storitev.
                    </p>
                </div>

                <form class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4" @submit.prevent="addService">
                    <input
                        v-model="newServiceForm.name"
                        type="text"
                        placeholder="npr. Gel nohti"
                        class="col-span-2 rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400 sm:col-span-1"
                    />
                    <input
                        v-model.number="newServiceForm.default_duration_minutes"
                        type="number"
                        min="5"
                        placeholder="Min"
                        class="rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                    <input
                        v-model="newServiceForm.default_price"
                        type="number"
                        step="0.01"
                        min="0"
                        placeholder="Cena"
                        class="rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                    />
                    <button
                        type="submit"
                        :disabled="newServiceForm.processing || !newServiceForm.name"
                        class="flex items-center justify-center gap-1.5 rounded-md bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800 disabled:opacity-50"
                    >
                        <Plus :size="14" /> Dodaj
                    </button>
                </form>
            </SectionCard>

            <SectionCard title="Kanali" subtitle="Poveži svoje Meta račune za samodejno sinhronizacijo pogovorov">
                <p class="mb-4 text-sm text-neutral-500">
                    Ko povežeš Instagram ali Facebook Messenger, bodo nova sporočila samodejno prihajala v tvojo prejeto pošto,
                    odgovore pa boš pošiljal/-a neposredno od tam.
                </p>

                <div v-if="metaPendingAccounts?.length" class="mb-4 rounded-lg border border-[var(--color-accent-200)] bg-[var(--color-accent-50)] p-4">
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
                        <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-white" @click="cancelPending">
                            Prekliči
                        </button>
                        <button
                            type="button"
                            :disabled="!selectedAccounts.length || pendingForm.processing"
                            class="rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-800 disabled:opacity-50"
                            @click="connectSelected"
                        >
                            Poveži izbrane
                        </button>
                    </div>
                </div>

                <div class="space-y-4">
                    <div v-for="type in META_TYPES" :key="type">
                        <div class="mb-2 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <ChannelIcon :type="type" size="md" />
                                <p class="text-sm font-medium text-neutral-900">{{ channelMeta(type).label }}</p>
                            </div>
                            <button
                                type="button"
                                class="rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                                @click="connectMeta"
                            >
                                Poveži {{ channelMeta(type).label }}
                            </button>
                        </div>

                        <div v-if="channelsOfType(type).length" class="space-y-2">
                            <div
                                v-for="channel in channelsOfType(type)"
                                :key="channel.id"
                                class="flex items-center justify-between rounded-lg border border-neutral-200 px-4 py-3"
                            >
                                <div>
                                    <p class="text-sm font-medium text-neutral-900">{{ channel.display_name }}</p>
                                    <p v-if="channel.handle" class="text-xs text-neutral-500">{{ channel.handle }}</p>
                                    <p v-if="channel.status === 'connected' && channel.last_synced_at" class="text-xs text-neutral-400">
                                        Zadnja sinhronizacija: {{ relativeTime(channel.last_synced_at) }}
                                    </p>
                                </div>

                                <div class="flex items-center gap-3">
                                    <span
                                        class="rounded-full px-2 py-0.5 text-xs font-medium"
                                        :class="channel.status === 'connected' ? 'bg-emerald-50 text-emerald-700' : 'bg-neutral-100 text-neutral-500'"
                                    >
                                        {{ channel.status === 'connected' ? 'Povezano' : 'Ni povezano' }}
                                    </span>
                                    <button
                                        v-if="channel.status === 'connected'"
                                        type="button"
                                        class="rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                                        @click="disconnectChannel(channel)"
                                    >
                                        Odklopi
                                    </button>
                                </div>
                            </div>
                        </div>
                        <p v-else class="rounded-lg border border-dashed border-neutral-200 px-4 py-3 text-sm text-neutral-400">
                            Ni povezanih računov.
                        </p>
                    </div>
                </div>
            </SectionCard>
        </div>
    </AppLayout>
</template>
