<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/Badge.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import CustomerContactCard from '@/Components/CustomerContactCard.vue';
import ExternalDocumentModal from '@/Components/ExternalDocumentModal.vue';
import FollowUpModal from '@/Components/FollowUpModal.vue';
import SendDocumentModal from '@/Components/SendDocumentModal.vue';
import StornoDocumentModal from '@/Components/StornoDocumentModal.vue';
import DateInput from '@/Components/DateInput.vue';
import { formatMoney, formatDate, formatDateTime, normalizeMoneyInput } from '@/lib/format';
import { APPOINTMENT_STATUS_ORDER, APPOINTMENT_STATUS_META } from '@/lib/statuses';
import type { ActivityLogEntry, Appointment, AppointmentStatus, FollowUp, SalesDocument } from '@/types/models';
import type { PageProps } from '@/types';
import { MessageSquare, Bell, FileText, Paperclip, Settings, Ban, Send, Check, Tag } from 'lucide-vue-next';

const props = defineProps<{
    appointment: Appointment;
    followUps: FollowUp[];
    activity: ActivityLogEntry[];
    invoiceSettingsConfigured: boolean;
}>();

const page = usePage<PageProps>();
const paymentStatuses = computed(() => page.props.paymentStatuses ?? []);
const acceptsDeposit = computed(() => page.props.workspace?.accepts_deposit ?? true);
const statusMeta = computed(() => APPOINTMENT_STATUS_META[props.appointment.status]);
const paymentMeta = computed(
    () =>
        paymentStatuses.value.find((s) => s.key === props.appointment.payment_status) ?? {
            label: props.appointment.payment_status,
            color: '#4B5563',
            bg: '#F1F2F4',
        },
);

const remainingBalance = computed(() => {
    const price = Number(props.appointment.price ?? 0);
    const paid = Number(props.appointment.amount_paid ?? 0);
    return Math.max(0, price - paid);
});

function updateStatus(status: string) {
    router.patch(route('appointments.update', props.appointment.id), { status }, { preserveScroll: true });
}

function cancelAppointment() {
    if (!confirm(`Prekličeš termin ${props.appointment.appointment_number}?`)) return;
    updateStatus('cancelled');
}

function updatePayment(payment_status: string) {
    router.patch(route('appointments.update', props.appointment.id), { payment_status }, { preserveScroll: true });
}

function completeFollowUp(id: number) {
    router.patch(route('follow-ups.complete', id), {}, { preserveScroll: true });
}

const paymentForm = useForm({
    price: props.appointment.price ?? '',
    deposit_amount: props.appointment.deposit_amount,
    amount_paid: props.appointment.amount_paid,
});

function savePayment() {
    paymentForm.price = normalizeMoneyInput(paymentForm.price);
    paymentForm.deposit_amount = normalizeMoneyInput(paymentForm.deposit_amount);
    paymentForm.amount_paid = normalizeMoneyInput(paymentForm.amount_paid);
    paymentForm.patch(route('appointments.update', props.appointment.id), { preserveScroll: true });
}

const scheduleForm = useForm({
    appointment_date: props.appointment.appointment_date,
    start_time: props.appointment.start_time,
    duration_minutes: props.appointment.duration_minutes,
});

const notesForm = useForm({
    internal_notes: props.appointment.internal_notes ?? '',
});

function saveNotes() {
    notesForm.patch(route('appointments.update', props.appointment.id), { preserveScroll: true });
}

function saveSchedule() {
    scheduleForm.patch(route('appointments.update', props.appointment.id), { preserveScroll: true });
}

watch(() => scheduleForm.appointment_date, saveSchedule);

const appointmentNotifyOpen = ref(false);
const followUpOpen = ref(false);
const canNotifyCustomer = computed(() => props.appointment.can_notify_customer ?? Boolean(props.appointment.conversation));
const appointmentReminderBody = computed(
    () =>
        `Živjo ${props.appointment.customer?.full_name ?? 'tam'} 😊 Opomnik za termin ${props.appointment.service_name}, ` +
        `${formatDate(props.appointment.appointment_date, { year: 'numeric' })} ob ${props.appointment.start_time.slice(0, 5)}.`,
);

function documentsOfType(type: 'proforma' | 'invoice' | 'storno') {
    return (props.appointment.sales_documents ?? []).filter((document) => document.type === type);
}

const proformaDocuments = computed(() => documentsOfType('proforma'));
const invoiceDocuments = computed(() => documentsOfType('invoice'));
const stornoDocuments = computed(() => documentsOfType('storno'));
const otherDocuments = computed(() => (props.appointment.sales_documents ?? []).filter((document) => document.type === 'other'));
const customerName = computed(() => props.appointment.customer?.full_name ?? 'tam');

const sendModal = ref<{ open: boolean; document: SalesDocument | null; title: string; submitLabel: string; body: string }>({
    open: false,
    document: null,
    title: '',
    submitLabel: '',
    body: '',
});

function openSendModal(document: SalesDocument) {
    const typeLabel = document.type === 'proforma' ? 'predračun' : document.type === 'storno' ? 'popravek/storno računa' : 'račun';
    const body =
        document.type === 'storno'
            ? `Živjo ${customerName.value}, pošiljam ${typeLabel} ${document.corrects_document?.document_number ?? ''}. Dokument je v priponki.`
            : `Živjo ${customerName.value} 😊 Pošiljam ti ${typeLabel} za termin. Podatke najdeš v priponki.`;

    sendModal.value = {
        open: true,
        document,
        title: document.sent_at ? `Pošlji ${typeLabel} znova` : `Pošlji ${typeLabel} stranki`,
        submitLabel: 'Pošlji',
        body,
    };
}

const stornoModal = ref<{ open: boolean; document: SalesDocument | null }>({ open: false, document: null });

function openStornoModal(document: SalesDocument) {
    stornoModal.value = { open: true, document };
}

function cancelProforma(document: SalesDocument) {
    if (!confirm(`Prekličeš predračun ${document.document_number}? Predračuna po tem ne bo več mogoče poslati kot aktivno plačilno zahtevo.`)) return;
    router.post(route('documents.cancel', document.id), {}, { preserveScroll: true });
}

function openReminderModal(document: SalesDocument) {
    sendModal.value = {
        open: true,
        document,
        title: 'Pošlji opomnik za plačilo',
        submitLabel: 'Pošlji opomnik',
        body: `Živjo ${customerName.value} 😊 Samo prijazen opomnik glede plačila za termin. Za plačilo je še ${formatMoney(remainingBalance.value)}. Če si nakazilo že uredila, sporočilo mirno prezri.`,
    };
}

const sendModalAction = computed(() => {
    if (!sendModal.value.document) return '';
    return sendModal.value.submitLabel === 'Pošlji opomnik'
        ? route('documents.remind', sendModal.value.document.id)
        : route('documents.send', sendModal.value.document.id);
});

const externalDocumentOpen = ref(false);
</script>

<template>
    <Head :title="appointment.appointment_number" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('appointments.index')" class="text-sm text-neutral-400 hover:text-neutral-600">Termini</Link>
                <span class="text-neutral-300">/</span>
                <span class="text-sm font-semibold text-neutral-900">{{ appointment.appointment_number }}</span>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-y-3">
                <div>
                    <h1 class="text-2xl font-semibold text-neutral-900">{{ appointment.service_name }}</h1>
                    <p class="mt-1 text-sm text-neutral-500">
                        {{ appointment.appointment_number }} · {{ formatDate(appointment.appointment_date) }} ob {{ appointment.start_time.slice(0, 5) }}
                    </p>
                </div>
                <div class="flex flex-wrap items-stretch gap-2">
                    <Link
                        :href="route('settings.statuses.edit')"
                        title="Nastavitve statusov"
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <Settings :size="14" />
                    </Link>
                    <button
                        v-if="appointment.status !== 'cancelled'"
                        type="button"
                        class="flex items-center gap-1.5 rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-100"
                        @click="cancelAppointment"
                    >
                        <Ban :size="14" /> Prekliči termin
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-neutral-800 uppercase">Podrobnosti termina</h3>
                            <Badge :color="statusMeta.color" :bg="statusMeta.bg">{{ statusMeta.label }}</Badge>
                        </div>
                        <p class="mt-2 text-sm text-neutral-700">{{ appointment.description || 'Opis ni dodan.' }}</p>

                        <p v-if="appointment.service" class="mt-2 flex items-center gap-1.5 text-xs text-neutral-500">
                            <Tag :size="12" />
                            Storitev:
                            <Link :href="route('catalog.index')" class="font-medium text-[var(--color-accent-600)] hover:underline">
                                {{ appointment.service.name }}
                            </Link>
                        </p>

                        <div class="mt-4">
                            <h3 class="text-xs font-medium text-neutral-500">Opombe stranke</h3>
                            <p class="mt-1 text-sm text-neutral-700">{{ appointment.customer_notes || '—' }}</p>
                        </div>

                        <div class="mt-4 flex items-end justify-between gap-4">
                            <div class="max-w-xs flex-1">
                                <label class="block text-xs text-neutral-500">Status termina</label>
                                <select
                                    :value="appointment.status"
                                    class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                                    @change="updateStatus(($event.target as HTMLSelectElement).value)"
                                >
                                    <option v-for="s in APPOINTMENT_STATUS_ORDER" :key="s" :value="s">
                                        {{ APPOINTMENT_STATUS_META[s as AppointmentStatus].label }}
                                    </option>
                                </select>
                            </div>
                            <button
                                v-if="canNotifyCustomer"
                                type="button"
                                class="flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                                @click="appointmentNotifyOpen = true"
                            >
                                <Send :size="14" /> Obvesti stranko
                            </button>
                        </div>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-neutral-800 uppercase">Podrobnosti o plačilu</h3>
                            <Badge :color="paymentMeta.color" :bg="paymentMeta.bg">{{ paymentMeta.label }}</Badge>
                        </div>
                        <div class="mt-3 grid grid-cols-2 gap-4" :class="acceptsDeposit ? 'sm:grid-cols-4' : 'sm:grid-cols-3'">
                            <div>
                                <label class="block text-xs text-neutral-500">Cena</label>
                                <input v-model="paymentForm.price" type="number" step="0.01" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" @change="savePayment" />
                            </div>
                            <div v-if="acceptsDeposit">
                                <label class="block text-xs text-neutral-500">Ara</label>
                                <input v-model="paymentForm.deposit_amount" type="number" step="0.01" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" @change="savePayment" />
                            </div>
                            <div>
                                <label class="block text-xs text-neutral-500">Plačan znesek</label>
                                <input v-model="paymentForm.amount_paid" type="number" step="0.01" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" @change="savePayment" />
                            </div>
                            <div>
                                <label class="block text-xs text-neutral-500">Status plačila</label>
                                <select :value="appointment.payment_status" class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" @change="updatePayment(($event.target as HTMLSelectElement).value)">
                                    <option v-for="s in paymentStatuses" :key="s.key" :value="s.key">{{ s.label }}</option>
                                </select>
                            </div>
                        </div>
                        <p class="mt-3 text-xs text-neutral-500">Preostanek: {{ formatMoney(remainingBalance) }}</p>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-neutral-800 uppercase">Dokumenti</h3>
                            <button type="button" class="flex items-center gap-1.5 text-xs font-medium text-neutral-500 hover:text-neutral-700" @click="externalDocumentOpen = true">
                                <Paperclip :size="13" /> Priloži obstoječ dokument
                            </button>
                        </div>
                        <p v-if="!invoiceSettingsConfigured" class="mt-3 text-sm text-neutral-500">
                            Za izdajo računov najprej nastavi
                            <Link :href="route('settings.invoicing.edit')" class="font-medium text-[var(--color-accent-500)] hover:underline">podatke o računih</Link>.
                        </p>
                        <div class="mt-3 space-y-3">
                            <div v-for="doc in [...proformaDocuments, ...invoiceDocuments, ...stornoDocuments, ...otherDocuments]" :id="`doc-${doc.id}`" :key="doc.id" class="flex items-center justify-between rounded-lg bg-neutral-50 px-3 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <FileText :size="16" class="text-neutral-400" />
                                    <div>
                                        <a
                                            :href="route('documents.download', doc.id)"
                                            target="_blank"
                                            rel="noopener"
                                            class="text-sm font-medium text-neutral-800 hover:text-[var(--color-accent-600)] hover:underline"
                                            title="Odpri / prenesi dokument"
                                        >
                                            <template v-if="doc.type === 'storno'">
                                                Storno računa {{ doc.corrects_document?.document_number }}
                                                <span class="font-normal text-neutral-400">({{ doc.document_number }})</span>
                                            </template>
                                            <template v-else>
                                                {{ doc.type === 'proforma' ? 'Predračun' : doc.type === 'invoice' ? 'Račun' : 'Drugo' }}
                                                {{ doc.document_number ?? doc.external_document_number }}
                                            </template>
                                            <span v-if="doc.source === 'external'" class="ml-1 text-xs font-normal text-neutral-400">(zunanji)</span>
                                        </a>
                                        <span
                                            v-if="doc.status_label"
                                            class="ml-2 inline-flex items-center rounded-full bg-red-50 px-2 py-0.5 text-[10px] font-medium text-red-700"
                                        >
                                            {{ doc.status_label }}
                                        </span>
                                        <p class="text-xs text-neutral-400">
                                            Izdano {{ formatDate(doc.issued_at) }}
                                            <span v-if="doc.sent_at"> · Poslano {{ formatDate(doc.sent_at) }}</span>
                                            <span v-else-if="doc.status === 'issued'"> · Ni še poslano</span>
                                            <template v-if="doc.status === 'reversed' && doc.correction">
                                                · <a :href="`#doc-${doc.correction.id}`" class="font-medium text-red-700 hover:underline">→ Storno {{ doc.correction.document_number }}</a>
                                            </template>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <button v-if="appointment.conversation && doc.status === 'issued'" type="button" class="text-xs font-medium text-[var(--color-accent-500)] hover:underline" @click="openSendModal(doc)">
                                        {{ doc.sent_at ? 'Pošlji znova' : 'Pošlji stranki' }}
                                    </button>
                                    <button v-if="appointment.conversation && doc.sent_at && doc.status === 'issued'" type="button" class="text-xs font-medium text-neutral-500 hover:underline" @click="openReminderModal(doc)">Opomnik</button>
                                    <button v-if="doc.can_be_cancelled" type="button" class="text-xs font-medium text-neutral-500 hover:text-red-600 hover:underline" @click="cancelProforma(doc)">
                                        Prekliči predračun
                                    </button>
                                    <button v-if="doc.can_be_stornoed" type="button" class="text-xs font-medium text-neutral-500 hover:text-red-600 hover:underline" @click="openStornoModal(doc)">
                                        Storniraj račun
                                    </button>
                                </div>
                            </div>
                            <p v-if="!(appointment.sales_documents ?? []).length" class="text-sm text-neutral-400">Še ni dokumentov.</p>
                        </div>
                        <div class="mt-4 flex items-center gap-2">
                            <Link :href="route('appointments.documents.create', { appointment: appointment.id, type: 'proforma' })" class="rounded-md border border-neutral-200 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50">Izstavi predračun</Link>
                            <Link :href="route('appointments.documents.create', { appointment: appointment.id, type: 'invoice' })" class="rounded-md border border-neutral-200 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50">Izstavi račun</Link>
                        </div>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Opombe</h3>
                        <textarea
                            v-model="notesForm.internal_notes"
                            rows="3"
                            placeholder="Dodaj interno opombo o tem terminu…"
                            class="mt-3 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                        <div class="mt-2 flex justify-end">
                            <button
                                type="button"
                                :disabled="notesForm.processing"
                                class="rounded-md bg-[var(--color-ink-900)] px-3 py-1.5 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                                @click="saveNotes"
                            >
                                Shrani opombo
                            </button>
                        </div>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Časovnica</h3>
                        <div class="mt-3 space-y-3">
                            <div v-for="entry in activity" :key="entry.id" class="flex gap-2.5 text-sm">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-neutral-300" />
                                <div>
                                    <p class="text-neutral-700">{{ entry.description }}</p>
                                    <p class="text-xs text-neutral-400">{{ formatDateTime(entry.created_at) }}</p>
                                </div>
                            </div>

                            <div v-for="followUp in followUps" :key="`f-${followUp.id}`" class="flex items-start gap-2.5 text-sm">
                                <Bell :size="13" class="mt-1 shrink-0 text-amber-500" />
                                <div class="min-w-0 flex-1">
                                    <p class="text-neutral-700">{{ followUp.note }}</p>
                                    <p class="text-xs text-neutral-400">
                                        Opomnik {{ followUp.completed_at ? 'zaključen' : 'zapade' }} {{ formatDateTime(followUp.due_at) }}
                                    </p>
                                </div>
                                <button
                                    v-if="!followUp.completed_at"
                                    type="button"
                                    class="flex shrink-0 items-center gap-1 text-xs font-medium text-neutral-500 hover:text-emerald-600"
                                    @click="completeFollowUp(followUp.id)"
                                >
                                    <Check :size="12" /> Zaključi
                                </button>
                            </div>

                            <p v-if="!activity.length && !followUps.length" class="text-sm text-neutral-400">Še ni aktivnosti.</p>
                        </div>
                    </section>

                    <section v-if="appointment.conversation" class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-neutral-800 uppercase">Izvorni pogovor</h3>
                            <Link
                                :href="route('inbox.show', appointment.conversation.id)"
                                class="flex items-center gap-1.5 text-sm font-medium text-[var(--color-accent-600)] hover:underline"
                            >
                                <MessageSquare :size="14" /> Odpri pogovor
                            </Link>
                        </div>
                    </section>
                </div>

                <div class="space-y-5">
                    <CustomerContactCard v-if="appointment.customer" :customer="appointment.customer" />

                    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Termin</h3>
                        <div class="mt-2 space-y-2">
                            <DateInput v-model="scheduleForm.appointment_date" />
                            <input
                                v-model="scheduleForm.start_time"
                                type="time"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                                @change="saveSchedule"
                            />
                            <select
                                v-model.number="scheduleForm.duration_minutes"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                                @change="saveSchedule"
                            >
                                <option v-for="d in [15, 30, 45, 60, 75, 90, 120, 150, 180, 240]" :key="d" :value="d">{{ d }} min</option>
                            </select>
                        </div>
                        <button
                            type="button"
                            class="mt-3 flex w-full items-center justify-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                            @click="followUpOpen = true"
                        >
                            <Bell :size="14" /> Nastavi opomnik
                        </button>
                    </section>

                    <section v-if="appointment.channel" class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Vir</h3>
                        <div class="mt-2 flex items-center gap-2">
                            <ChannelIcon :type="appointment.channel.type" size="md" />
                            <span class="text-sm text-neutral-700">{{ appointment.channel.display_name }}</span>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <SendDocumentModal
            :show="appointmentNotifyOpen"
            title="Pošlji opomnik za termin"
            submit-label="Pošlji"
            :action="route('appointments.notify.store', appointment.id)"
            :default-body="appointmentReminderBody"
            @close="appointmentNotifyOpen = false"
        />

        <FollowUpModal
            :show="followUpOpen"
            followable-type="App\Models\Appointment"
            :followable-id="appointment.id"
            :default-note="`Opomnik za termin: ${appointment.service_name}`"
            @close="followUpOpen = false"
        />

        <SendDocumentModal
            :show="sendModal.open"
            :title="sendModal.title"
            :submit-label="sendModal.submitLabel"
            :action="sendModalAction"
            :default-body="sendModal.body"
            @close="sendModal.open = false"
        />

        <ExternalDocumentModal
            :show="externalDocumentOpen"
            :documentable-id="appointment.id"
            documentable-type="appointment"
            @close="externalDocumentOpen = false"
        />

        <StornoDocumentModal
            :show="stornoModal.open"
            :document-number="stornoModal.document?.document_number ?? ''"
            :action="stornoModal.document ? route('documents.storno', stornoModal.document.id) : ''"
            @close="stornoModal.open = false"
        />
    </AppLayout>
</template>
