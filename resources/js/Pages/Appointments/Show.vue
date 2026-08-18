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
import CatalogItemModal from '@/Components/CatalogItemModal.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import { useConfirm } from '@/composables/useConfirm';
import { formatMoney, formatDate, formatDateTime, normalizeMoneyInput } from '@/lib/format';
import type { ActivityLogEntry, Appointment, FollowUp, Product, SalesDocument, Service } from '@/types/models';
import type { PageProps } from '@/types';
import { MessageSquare, Bell, FileText, Paperclip, Settings, Ban, Send, Check, Undo2, UserX, Plus, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    appointment: Appointment;
    services: Service[];
    followUps: FollowUp[];
    activity: ActivityLogEntry[];
    invoiceSettingsConfigured: boolean;
}>();

const { confirm } = useConfirm();

const page = usePage<PageProps>();
const paymentStatuses = computed(() => page.props.paymentStatuses ?? []);
const appointmentStatuses = computed(() => page.props.appointmentStatuses ?? []);
const acceptsDeposit = computed(() => page.props.workspace?.accepts_deposit ?? true);
const fallbackStatus = { label: props.appointment.status, color: '#4B5563', bg: '#F1F2F4' };
const statusMeta = computed(() => appointmentStatuses.value.find((s) => s.key === props.appointment.status) ?? fallbackStatus);
const paymentMeta = computed(
    () => paymentStatuses.value.find((s) => s.key === props.appointment.payment_status) ?? { ...fallbackStatus, label: props.appointment.payment_status },
);

const remainingBalance = computed(() => {
    const price = Number(props.appointment.price ?? 0);
    const paid = Number(props.appointment.amount_paid ?? 0);
    return Math.max(0, price - paid);
});

function updateStatus(status: string) {
    router.patch(route('appointments.update', props.appointment.id), { status }, { preserveScroll: true });
}

// Appointment statuses are workspace-customizable — "cancelled"/"completed"/
// "refunded" are whichever statuses are flagged as such, not literal keys.
const cancelledStatusKey = computed(() => appointmentStatuses.value.find((s) => s.is_cancelled)?.key);
const refundedStatusKey = computed(() => appointmentStatuses.value.find((s) => s.is_refunded)?.key);
const noShowStatusKey = computed(() => appointmentStatuses.value.find((s) => s.is_no_show)?.key);

async function cancelAppointment() {
    if (!cancelledStatusKey.value) return;
    if (!(await confirm(`Prekličeš termin ${props.appointment.appointment_number}?`, { danger: true }))) return;
    updateStatus(cancelledStatusKey.value);
}

async function refundAppointment() {
    if (!refundedStatusKey.value) return;
    if (!(await confirm(`Si prepričan/a, da želiš izvesti vračilo za termin ${props.appointment.appointment_number}?`, { danger: true }))) return;
    updateStatus(refundedStatusKey.value);
}

async function markNoShow() {
    if (!noShowStatusKey.value) return;
    if (!(await confirm(`Označiš termin ${props.appointment.appointment_number} kot "Ni se zglasil/a"?`, { danger: true }))) return;
    updateStatus(noShowStatusKey.value);
}

const isCancelled = computed(() => 'is_cancelled' in statusMeta.value && statusMeta.value.is_cancelled === true);
const isCompleted = computed(() => 'is_completed' in statusMeta.value && statusMeta.value.is_completed === true);
const isRefunded = computed(() => 'is_refunded' in statusMeta.value && statusMeta.value.is_refunded === true);
const isNoShow = computed(() => 'is_no_show' in statusMeta.value && statusMeta.value.is_no_show === true);
const hasUnstornoedInvoice = computed(() => (props.appointment.sales_documents ?? []).some((d) => d.type === 'invoice' && d.status === 'issued'));

function updatePayment(payment_status: string) {
    router.patch(route('appointments.update', props.appointment.id), { payment_status }, { preserveScroll: true });
}

function completeFollowUp(id: number) {
    router.patch(route('follow-ups.complete', id), {}, { preserveScroll: true });
}

const paymentForm = useForm({
    deposit_amount: props.appointment.deposit_amount,
    amount_paid: props.appointment.amount_paid,
});

function savePayment() {
    paymentForm.deposit_amount = normalizeMoneyInput(paymentForm.deposit_amount);
    paymentForm.amount_paid = normalizeMoneyInput(paymentForm.amount_paid);
    paymentForm.patch(route('appointments.update', props.appointment.id), { preserveScroll: true });
}

const itemsForm = useForm({
    items: props.appointment.items.map((item) => ({
        catalog_item_id: item.catalog_item_id,
        title: item.title,
        quantity: Number(item.quantity),
        unit_price: Number(item.unit_price),
    })),
});

const itemsTotal = computed(() => itemsForm.items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0), 0));

const NEW_SERVICE = '__new__';
const quickAddOpen = ref(false);
const quickAddRowIndex = ref<number | null>(null);

function addItem() {
    itemsForm.items.push({ catalog_item_id: null, title: '', quantity: 1, unit_price: 0 });
}

function removeItem(index: number) {
    itemsForm.items.splice(index, 1);
}

function onServiceSelectChange(index: number, event: Event) {
    const raw = (event.target as HTMLSelectElement).value;

    if (raw === NEW_SERVICE) {
        (event.target as HTMLSelectElement).value = String(itemsForm.items[index].catalog_item_id ?? '');
        quickAddRowIndex.value = index;
        quickAddOpen.value = true;
        return;
    }

    onServiceSelect(index, raw ? Number(raw) : null);
}

function onServiceSelect(index: number, serviceId: number | null) {
    const item = itemsForm.items[index];
    item.catalog_item_id = serviceId;

    const service = props.services.find((s) => s.id === serviceId);
    if (service) {
        item.title = service.name;
        if (service.default_price !== null) item.unit_price = Number(service.default_price);
    }
}

function onServiceSaved(item: Product | Service) {
    if (quickAddRowIndex.value === null) return;

    onServiceSelect(quickAddRowIndex.value, item.id);
    quickAddRowIndex.value = null;
}

function saveItems() {
    itemsForm.patch(route('appointments.update', props.appointment.id), { preserveScroll: true });
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

async function cancelProforma(document: SalesDocument) {
    if (!(await confirm(`Prekličeš predračun ${document.document_number}? Predračuna po tem ne bo več mogoče poslati kot aktivno plačilno zahtevo.`, { danger: true }))) return;
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
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        v-if="isCompleted && !isRefunded && refundedStatusKey"
                        type="button"
                        class="text-xs font-medium text-red-600 underline"
                        @click="refundAppointment"
                    >
                        Izvedi vračilo
                    </button>
                    <Link
                        :href="route('settings.statuses.edit')"
                        title="Nastavitve statusov"
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <Settings :size="14" />
                    </Link>
                    <span
                        v-if="isCompleted"
                        class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium"
                        style="color: #15803d; background-color: #dcfce7"
                    >
                        <Check :size="14" /> Termin zaključen
                    </span>
                    <span
                        v-else-if="isRefunded"
                        class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium"
                        :style="{ color: statusMeta.color, backgroundColor: statusMeta.bg }"
                    >
                        <Undo2 :size="14" /> Vračilo
                    </span>
                    <span
                        v-else-if="isCancelled"
                        class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium"
                        :style="{ color: statusMeta.color, backgroundColor: statusMeta.bg }"
                    >
                        <Ban :size="14" /> Termin preklican
                    </span>
                    <span
                        v-else-if="isNoShow"
                        class="flex items-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-medium"
                        :style="{ color: statusMeta.color, backgroundColor: statusMeta.bg }"
                    >
                        <UserX :size="14" /> Ni se zglasil/a
                    </span>
                    <template v-else>
                        <button
                            v-if="noShowStatusKey"
                            type="button"
                            class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                            @click="markNoShow"
                        >
                            <UserX :size="14" /> Ni se zglasil/a
                        </button>
                        <button
                            v-if="cancelledStatusKey"
                            type="button"
                            class="flex items-center gap-1.5 rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-100"
                            @click="cancelAppointment"
                        >
                            <Ban :size="14" /> Prekliči termin
                        </button>
                    </template>
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

                        <div class="mt-4">
                            <h3 class="text-xs font-medium text-neutral-500">Opombe stranke</h3>
                            <p class="mt-1 text-sm text-neutral-700">{{ appointment.customer_notes || '—' }}</p>
                        </div>

                        <div class="mt-4 max-w-xs">
                            <label class="block text-xs text-neutral-500">Status termina</label>
                            <select
                                :value="appointment.status"
                                class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                                @change="updateStatus(($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="s in appointmentStatuses" :key="s.key" :value="s.key">
                                    {{ s.label }}
                                </option>
                            </select>
                        </div>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Postavke</h3>

                        <div class="mt-3 space-y-3">
                            <div v-for="(item, index) in itemsForm.items" :key="index" class="grid grid-cols-12 items-end gap-2">
                                <div class="col-span-3">
                                    <label class="block text-xs text-neutral-500">Storitev</label>
                                    <select
                                        :value="item.catalog_item_id"
                                        class="mt-1 w-full rounded-md border border-neutral-200 px-2 py-1.5 text-sm outline-none"
                                        @change="onServiceSelectChange(index, $event)"
                                    >
                                        <option :value="null">Brez storitve</option>
                                        <option v-for="service in services" :key="service.id" :value="service.id">{{ service.name }}</option>
                                        <option :value="NEW_SERVICE">+ Dodaj novo storitev</option>
                                    </select>
                                </div>
                                <div class="col-span-4">
                                    <label class="block text-xs text-neutral-500">Naziv postavke</label>
                                    <input v-model="item.title" type="text" class="mt-1 w-full rounded-md border border-neutral-200 px-2 py-1.5 text-sm outline-none" />
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs text-neutral-500">Količina</label>
                                    <input v-model.number="item.quantity" type="number" step="0.01" min="0.01" class="mt-1 w-full rounded-md border border-neutral-200 px-2 py-1.5 text-sm outline-none" />
                                </div>
                                <div class="col-span-2">
                                    <label class="block text-xs text-neutral-500">Cena/kos</label>
                                    <MoneyInput v-model="item.unit_price" class="mt-1 w-full rounded-md border border-neutral-200 px-2 py-1.5 text-sm outline-none" />
                                </div>
                                <div class="col-span-1 flex justify-end">
                                    <button
                                        type="button"
                                        :disabled="itemsForm.items.length === 1"
                                        class="rounded-md p-1.5 text-neutral-400 hover:bg-red-50 hover:text-red-600 disabled:opacity-30"
                                        @click="removeItem(index)"
                                    >
                                        <Trash2 :size="15" />
                                    </button>
                                </div>
                            </div>

                            <div class="flex items-center justify-between">
                                <button type="button" class="flex items-center gap-1.5 text-sm font-medium text-[var(--color-accent-500)] hover:underline" @click="addItem">
                                    <Plus :size="14" /> Dodaj postavko
                                </button>
                                <button
                                    type="button"
                                    :disabled="itemsForm.processing"
                                    class="rounded-md border border-neutral-200 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50 disabled:opacity-50"
                                    @click="saveItems"
                                >
                                    Shrani postavke
                                </button>
                            </div>

                            <p v-if="itemsForm.errors.items" class="text-xs text-red-600">{{ itemsForm.errors.items }}</p>
                        </div>

                        <div class="mt-4 flex justify-end border-t border-neutral-200 pt-3 text-sm font-semibold text-neutral-900">
                            <span>Skupaj: {{ formatMoney(itemsTotal) }}</span>
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
                                <p class="mt-1 rounded-md border border-transparent px-3 py-2 text-sm text-neutral-900">{{ formatMoney(appointment.price ?? 0) }}</p>
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
                        <p v-if="isRefunded && hasUnstornoedInvoice" class="mt-3 rounded-md bg-red-50 px-3 py-2 text-sm text-red-700">
                            Termin je označen kot vrnjen, izdan račun pa še ni storniran — storniraj ga spodaj.
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
                            v-if="canNotifyCustomer"
                            type="button"
                            class="mt-3 flex w-full items-center justify-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                            @click="appointmentNotifyOpen = true"
                        >
                            <Send :size="14" /> Obvesti stranko
                        </button>
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

        <CatalogItemModal v-model:open="quickAddOpen" kind="service" @saved="onServiceSaved" />
    </AppLayout>
</template>
