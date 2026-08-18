<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/Badge.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import FollowUpModal from '@/Components/FollowUpModal.vue';
import SendDocumentModal from '@/Components/SendDocumentModal.vue';
import StornoDocumentModal from '@/Components/StornoDocumentModal.vue';
import ExternalDocumentModal from '@/Components/ExternalDocumentModal.vue';
import NotifyCustomerModal from '@/Components/NotifyCustomerModal.vue';
import CustomerContactCard from '@/Components/CustomerContactCard.vue';
import DateInput from '@/Components/DateInput.vue';
import CatalogItemModal from '@/Components/CatalogItemModal.vue';
import MoneyInput from '@/Components/MoneyInput.vue';
import { useConfirm } from '@/composables/useConfirm';
import { formatMoney, formatDate, formatDateTime, formatTime, normalizeMoneyInput } from '@/lib/format';
import type { ActivityLogEntry, FollowUp, Order, Product, SalesDocument, Service } from '@/types/models';
import type { PageProps } from '@/types';
import { CalendarClock, MessageSquare, Bell, Check, Ban, Settings, FileText, Paperclip, Send, Truck, Store, Undo2, Plus, Trash2 } from 'lucide-vue-next';

const props = defineProps<{
    order: Order;
    products: Product[];
    followUps: FollowUp[];
    activity: ActivityLogEntry[];
    invoiceSettingsConfigured: boolean;
}>();

const { confirm } = useConfirm();

const page = usePage<PageProps>();
const orderStatuses = computed(() => page.props.orderStatuses ?? []);
const paymentStatuses = computed(() => page.props.paymentStatuses ?? []);
const acceptsDeposit = computed(() => page.props.workspace?.accepts_deposit ?? true);

const fallbackStatus = { label: props.order.status, color: '#4B5563', bg: '#F1F2F4' };
const statusMeta = computed(() => orderStatuses.value.find((s) => s.key === props.order.status) ?? fallbackStatus);
const paymentMeta = computed(
    () => paymentStatuses.value.find((s) => s.key === props.order.payment_status) ?? { ...fallbackStatus, label: props.order.payment_status },
);
// Order statuses are workspace-customizable — "cancelled"/"completed"/
// "refunded" are whichever statuses are flagged as such, not literal keys.
const cancelledStatusKey = computed(() => orderStatuses.value.find((s) => s.is_cancelled)?.key);
const refundedStatusKey = computed(() => orderStatuses.value.find((s) => s.is_refunded)?.key);
const isCancelled = computed(() => 'is_cancelled' in statusMeta.value && statusMeta.value.is_cancelled === true);
const isCompleted = computed(() => 'is_completed' in statusMeta.value && statusMeta.value.is_completed === true);
const isRefunded = computed(() => 'is_refunded' in statusMeta.value && statusMeta.value.is_refunded === true);

function updateStatus(status: string) {
    router.patch(route('orders.update', props.order.id), { status }, { preserveScroll: true });
}

async function cancelOrder() {
    if (!cancelledStatusKey.value) return;
    if (!(await confirm(`Prekličeš naročilo ${props.order.order_number}?`, { danger: true }))) return;
    updateStatus(cancelledStatusKey.value);
}

async function refundOrder() {
    if (!refundedStatusKey.value) return;
    if (!(await confirm(`Si prepričan/a, da želiš izvesti vračilo za naročilo ${props.order.order_number}?`, { danger: true }))) return;
    updateStatus(refundedStatusKey.value);
}

function completeFollowUp(id: number) {
    router.patch(route('follow-ups.complete', id), {}, { preserveScroll: true });
}

function updatePayment(payment_status: string) {
    router.patch(route('orders.update', props.order.id), { payment_status }, { preserveScroll: true });
}

const paymentForm = useForm({
    deposit_amount: props.order.deposit_amount,
    amount_paid: props.order.amount_paid,
});

function savePayment() {
    paymentForm.deposit_amount = normalizeMoneyInput(paymentForm.deposit_amount);
    paymentForm.amount_paid = normalizeMoneyInput(paymentForm.amount_paid);
    paymentForm.patch(route('orders.update', props.order.id), { preserveScroll: true });
}

const itemsForm = useForm({
    items: props.order.items.map((item) => ({
        catalog_item_id: item.catalog_item_id,
        title: item.title,
        quantity: Number(item.quantity),
        unit_price: Number(item.unit_price),
    })),
});

const itemsTotal = computed(() => itemsForm.items.reduce((sum, item) => sum + Number(item.quantity || 0) * Number(item.unit_price || 0), 0));

const NEW_PRODUCT = '__new__';
const quickAddOpen = ref(false);
const quickAddRowIndex = ref<number | null>(null);

function addItem() {
    itemsForm.items.push({ catalog_item_id: null, title: '', quantity: 1, unit_price: 0 });
}

function removeItem(index: number) {
    itemsForm.items.splice(index, 1);
}

function onProductSelectChange(index: number, event: Event) {
    const raw = (event.target as HTMLSelectElement).value;

    if (raw === NEW_PRODUCT) {
        (event.target as HTMLSelectElement).value = String(itemsForm.items[index].catalog_item_id ?? '');
        quickAddRowIndex.value = index;
        quickAddOpen.value = true;
        return;
    }

    onProductSelect(index, raw ? Number(raw) : null);
}

function onProductSelect(index: number, productId: number | null) {
    const item = itemsForm.items[index];
    item.catalog_item_id = productId;

    const product = props.products.find((p) => p.id === productId);
    if (product) {
        item.title = product.name;
        if (product.default_price !== null) item.unit_price = Number(product.default_price);
    }
}

function onProductSaved(item: Product | Service) {
    if (quickAddRowIndex.value === null) return;

    onProductSelect(quickAddRowIndex.value, item.id);
    quickAddRowIndex.value = null;
}

function saveItems() {
    itemsForm.patch(route('orders.update', props.order.id), { preserveScroll: true });
}

const deadlineForm = useForm({
    due_date: props.order.due_date ?? '',
    due_time: props.order.due_time ?? '',
});

const deliveryForm = useForm({
    delivery_method: props.order.delivery_method ?? 'mail',
    tracking_number: props.order.tracking_number ?? '',
    tracking_url: props.order.tracking_url ?? '',
});

function saveDelivery() {
    deliveryForm.patch(route('orders.update', props.order.id), { preserveScroll: true });
}

function saveDeadline() {
    deadlineForm.patch(route('orders.update', props.order.id), { preserveScroll: true });
}

// DateInput only emits update:modelValue on a meaningful commit (blur or
// picker selection, not per keystroke — see DateInput.vue), so watching it
// is safe to auto-save on. The time <input> uses @change directly instead,
// since its default v-model event ('input') fires more eagerly.
watch(() => deadlineForm.due_date, saveDeadline);

const noteForm = useForm({ body: '' });

function submitNote() {
    noteForm.post(route('orders.notes.store', props.order.id), {
        preserveScroll: true,
        onSuccess: () => noteForm.reset(),
    });
}

const followUpOpen = ref(false);

const customerName = computed(() => props.order.customer?.full_name ?? 'tam');
const balanceDue = computed(() => Math.max(0, Number(props.order.price) - Number(props.order.amount_paid)));

function documentsOfType(type: 'proforma' | 'invoice' | 'storno') {
    return (props.order.sales_documents ?? []).filter((d) => d.type === type);
}
const proformaDocuments = computed(() => documentsOfType('proforma'));
const invoiceDocuments = computed(() => documentsOfType('invoice'));
const stornoDocuments = computed(() => documentsOfType('storno'));
const otherDocuments = computed(() => (props.order.sales_documents ?? []).filter((d) => d.type === 'other'));

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
        document.type === 'proforma'
            ? `Živjo ${customerName.value} 😊 Pošiljam ti predračun za naročilo. Podatke za nakazilo najdeš v priponki.`
            : document.type === 'storno'
              ? `Živjo ${customerName.value}, pošiljam ${typeLabel} ${document.corrects_document?.document_number ?? ''}. Dokument je v priponki.`
              : `Živjo ${customerName.value} 😊 Pošiljam ti račun za naročilo. Hvala!`;

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

const cancellingProformaId = ref<number | null>(null);

async function cancelProforma(document: SalesDocument) {
    if (cancellingProformaId.value !== null) return;
    if (!(await confirm(`Prekličeš predračun ${document.document_number}? Predračuna po tem ne bo več mogoče poslati kot aktivno plačilno zahtevo.`, { danger: true }))) return;
    cancellingProformaId.value = document.id;
    router.post(route('documents.cancel', document.id), {}, {
        preserveScroll: true,
        onFinish: () => {
            cancellingProformaId.value = null;
        },
    });
}

function openReminderModal(document: SalesDocument) {
    sendModal.value = {
        open: true,
        document,
        title: 'Pošlji opomnik za plačilo',
        submitLabel: 'Pošlji opomnik',
        body: `Živjo ${customerName.value} 😊 Samo prijazen opomnik glede plačila za naročilo. Za plačilo je še ${formatMoney(balanceDue.value)}. Če si nakazilo že uredila, sporočilo mirno prezri.`,
    };
}

const sendModalAction = computed(() => {
    if (!sendModal.value.document) return '';
    return sendModal.value.submitLabel === 'Pošlji opomnik'
        ? route('documents.remind', sendModal.value.document.id)
        : route('documents.send', sendModal.value.document.id);
});

const externalDocumentOpen = ref(false);

const canNotifyCustomer = computed(() => props.order.can_notify_customer ?? Boolean(props.order.conversation));
const notifyModalOpen = ref(false);
</script>

<template>
    <Head :title="order.order_number" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('orders.index')" class="text-sm text-neutral-400 hover:text-neutral-600">Naročila</Link>
                <span class="text-neutral-300">/</span>
                <span class="text-sm font-semibold text-neutral-900">{{ order.order_number }}</span>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
            <div class="mb-6 flex flex-wrap items-start justify-between gap-y-3">
                <div>
                    <h1 class="text-2xl font-semibold text-neutral-900">{{ order.title }}</h1>
                    <p class="mt-1 text-sm text-neutral-500">{{ order.order_number }} · Ustvarjeno {{ formatDate(order.created_at) }}</p>
                </div>
                <div class="flex flex-wrap items-center gap-3">
                    <button
                        v-if="isCompleted && !isRefunded && refundedStatusKey"
                        type="button"
                        class="text-xs font-medium text-red-600 underline"
                        @click="refundOrder"
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
                        <Check :size="14" /> Naročilo zaključeno
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
                        <Ban :size="14" /> Naročilo preklicano
                    </span>
                    <button
                        v-else-if="!isCancelled && cancelledStatusKey"
                        type="button"
                        class="flex items-center gap-1.5 rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-100"
                        @click="cancelOrder"
                    >
                        <Ban :size="14" /> Prekliči naročilo
                    </button>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-neutral-800 uppercase">Podrobnosti naročila</h3>
                            <Badge :color="statusMeta.color" :bg="statusMeta.bg">{{ statusMeta.label }}</Badge>
                        </div>
                        <p class="mt-2 text-sm text-neutral-700">{{ order.description || 'Opis ni dodan.' }}</p>

                        <div class="mt-4">
                            <h3 class="text-xs font-medium text-neutral-500">Opombe stranke</h3>
                            <p class="mt-1 text-sm text-neutral-700">{{ order.customer_notes || '—' }}</p>
                        </div>

                        <div class="mt-4 max-w-xs">
                            <label class="block text-xs text-neutral-500">Status naročila</label>
                            <select
                                :value="order.status"
                                class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                                @change="updateStatus(($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="s in orderStatuses" :key="s.key" :value="s.key">{{ s.label }}</option>
                            </select>
                        </div>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Postavke</h3>

                        <div class="mt-3 space-y-3">
                            <div v-for="(item, index) in itemsForm.items" :key="index" class="grid grid-cols-12 items-end gap-2">
                                <div class="col-span-3">
                                    <label class="block text-xs text-neutral-500">Produkt</label>
                                    <select
                                        :value="item.catalog_item_id"
                                        class="mt-1 w-full rounded-md border border-neutral-200 px-2 py-1.5 text-sm outline-none"
                                        @change="onProductSelectChange(index, $event)"
                                    >
                                        <option :value="null">Brez produkta</option>
                                        <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}</option>
                                        <option :value="NEW_PRODUCT">+ Dodaj nov produkt</option>
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
                                <p class="mt-1 rounded-md border border-transparent px-3 py-2 text-sm text-neutral-900">{{ formatMoney(order.price) }}</p>
                            </div>
                            <div v-if="acceptsDeposit">
                                <label class="block text-xs text-neutral-500">Ara</label>
                                <input
                                    v-model="paymentForm.deposit_amount"
                                    type="number"
                                    step="0.01"
                                    class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                                    @change="savePayment"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-neutral-500">Plačan znesek</label>
                                <input
                                    v-model="paymentForm.amount_paid"
                                    type="number"
                                    step="0.01"
                                    class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                                    @change="savePayment"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-neutral-500">Status plačila</label>
                                <select
                                    :value="order.payment_status"
                                    class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                                    @change="updatePayment(($event.target as HTMLSelectElement).value)"
                                >
                                    <option v-for="s in paymentStatuses" :key="s.key" :value="s.key">{{ s.label }}</option>
                                </select>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-neutral-800 uppercase">Dokumenti</h3>
                            <button
                                type="button"
                                class="flex items-center gap-1.5 text-xs font-medium text-neutral-500 hover:text-neutral-700"
                                @click="externalDocumentOpen = true"
                            >
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
                                    <button v-if="order.conversation && doc.status === 'issued'" type="button" class="text-xs font-medium text-[var(--color-accent-500)] hover:underline" @click="openSendModal(doc)">
                                        {{ doc.sent_at ? 'Pošlji znova' : 'Pošlji stranki' }}
                                    </button>
                                    <button v-if="order.conversation && doc.sent_at && doc.status === 'issued'" type="button" class="text-xs font-medium text-neutral-500 hover:underline" @click="openReminderModal(doc)">
                                        Opomnik
                                    </button>
                                    <button v-if="doc.can_be_cancelled" type="button" :disabled="cancellingProformaId === doc.id" class="text-xs font-medium text-neutral-500 hover:text-red-600 hover:underline disabled:opacity-50" @click="cancelProforma(doc)">
                                        Prekliči predračun
                                    </button>
                                    <button v-if="doc.can_be_stornoed" type="button" class="text-xs font-medium text-neutral-500 hover:text-red-600 hover:underline" @click="openStornoModal(doc)">
                                        Storniraj račun
                                    </button>
                                </div>
                            </div>
                            <p v-if="!(order.sales_documents ?? []).length" class="text-sm text-neutral-400">Še ni dokumentov.</p>
                        </div>

                        <div class="mt-4 flex items-center gap-2">
                            <Link
                                :href="route('orders.documents.create', { order: order.id, type: 'proforma' })"
                                class="rounded-md border border-neutral-200 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50"
                            >
                                Izstavi predračun
                            </Link>
                            <Link
                                :href="route('orders.documents.create', { order: order.id, type: 'invoice' })"
                                class="rounded-md border border-neutral-200 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-50"
                            >
                                Izstavi račun
                            </Link>
                        </div>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Opombe</h3>

                        <form class="mt-3 flex gap-2" @submit.prevent="submitNote">
                            <input
                                v-model="noteForm.body"
                                type="text"
                                placeholder="Dodaj opombo o tem naročilu…"
                                class="flex-1 rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                            />
                            <button
                                type="submit"
                                :disabled="noteForm.processing"
                                class="rounded-md bg-[var(--color-ink-900)] px-3 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                            >
                                Dodaj
                            </button>
                        </form>

                        <div class="mt-4 space-y-3">
                            <div v-for="note in order.notes" :key="note.id" class="rounded-lg bg-neutral-50 px-3 py-2.5">
                                <p class="text-sm text-neutral-800">{{ note.body }}</p>
                                <p class="mt-1 text-xs text-neutral-400">
                                    {{ note.user?.name ?? 'Ti' }} · {{ formatDateTime(note.created_at) }}
                                </p>
                            </div>
                            <p v-if="!order.notes?.length" class="text-sm text-neutral-400">Še ni opomb.</p>
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

                    <section v-if="order.conversation" class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-neutral-800 uppercase">Izvorni pogovor</h3>
                            <Link
                                :href="route('inbox.show', order.conversation.id)"
                                class="flex items-center gap-1.5 text-sm font-medium text-[var(--color-accent-600)] hover:underline"
                            >
                                <MessageSquare :size="14" /> Odpri pogovor
                            </Link>
                        </div>
                    </section>
                </div>

                <div class="space-y-5">
                    <CustomerContactCard v-if="order.customer" :customer="order.customer" />

                    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Dostava</h3>
                        <div class="mt-3 flex w-full rounded-md border border-neutral-200 bg-white p-0.5 text-sm font-medium">
                            <button
                                type="button"
                                class="flex flex-1 items-center justify-center gap-1.5 rounded py-2 transition"
                                :class="
                                    deliveryForm.delivery_method === 'mail'
                                        ? 'bg-[var(--color-ink-900)] text-white'
                                        : 'text-neutral-500 hover:text-neutral-700'
                                "
                                @click="
                                    deliveryForm.delivery_method = 'mail';
                                    saveDelivery();
                                "
                            >
                                <Truck :size="14" /> Pošiljanje po pošti
                            </button>
                            <button
                                type="button"
                                class="flex flex-1 items-center justify-center gap-1.5 rounded py-2 transition"
                                :class="
                                    deliveryForm.delivery_method === 'pickup'
                                        ? 'bg-[var(--color-ink-900)] text-white'
                                        : 'text-neutral-500 hover:text-neutral-700'
                                "
                                @click="
                                    deliveryForm.delivery_method = 'pickup';
                                    saveDelivery();
                                "
                            >
                                <Store :size="14" /> Osebni prevzem
                            </button>
                        </div>

                        <div v-if="deliveryForm.delivery_method === 'mail'" class="mt-4 space-y-3">
                            <div>
                                <label class="block text-xs text-neutral-500">Številka za sledenje</label>
                                <input
                                    v-model="deliveryForm.tracking_number"
                                    type="text"
                                    class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                                    @change="saveDelivery"
                                />
                            </div>
                            <div>
                                <label class="block text-xs text-neutral-500">Povezava za sledenje</label>
                                <input
                                    v-model="deliveryForm.tracking_url"
                                    type="url"
                                    placeholder="https://…"
                                    class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                                    @change="saveDelivery"
                                />
                                <p v-if="deliveryForm.errors.tracking_url" class="mt-1 text-xs text-red-500">{{ deliveryForm.errors.tracking_url }}</p>
                            </div>
                            <p v-if="order.shipped_at" class="text-xs text-neutral-500">
                                Poslano {{ formatDate(order.shipped_at, { year: 'numeric' }) }}
                            </p>
                        </div>
                        <a
                            v-if="deliveryForm.delivery_method === 'mail' && deliveryForm.tracking_url"
                            :href="deliveryForm.tracking_url"
                            target="_blank"
                            rel="noopener"
                            class="mt-3 flex w-full items-center justify-center rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                        >
                            Odpri sledenje
                        </a>
                        <button
                            v-if="canNotifyCustomer"
                            type="button"
                            class="mt-3 flex w-full items-center justify-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                            @click="notifyModalOpen = true"
                        >
                            <Send :size="14" /> Obvesti stranko
                        </button>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Rok</h3>
                        <div class="mt-2 space-y-2">
                            <DateInput v-model="deadlineForm.due_date" />
                            <input
                                v-model="deadlineForm.due_time"
                                type="time"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                                @change="saveDeadline"
                            />
                        </div>
                        <button
                            type="button"
                            class="mt-3 flex w-full items-center justify-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                            @click="followUpOpen = true"
                        >
                            <Bell :size="14" /> Nastavi opomnik
                        </button>
                    </section>

                    <section v-if="order.channel" class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Vir</h3>
                        <div class="mt-2 flex items-center gap-2">
                            <ChannelIcon :type="order.channel.type" size="md" />
                            <span class="text-sm text-neutral-700">{{ order.channel.display_name }}</span>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <FollowUpModal
            :show="followUpOpen"
            followable-type="App\Models\Order"
            :followable-id="order.id"
            :default-note="`Opomnik za naročilo: ${order.title}`"
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

        <ExternalDocumentModal :show="externalDocumentOpen" :documentable-id="order.id" @close="externalDocumentOpen = false" />

        <StornoDocumentModal
            :show="stornoModal.open"
            :document-number="stornoModal.document?.document_number ?? ''"
            :action="stornoModal.document ? route('documents.storno', stornoModal.document.id) : ''"
            @close="stornoModal.open = false"
        />

        <NotifyCustomerModal
            :show="notifyModalOpen"
            :order="order"
            :action="route('orders.notify.store', order.id)"
            @close="notifyModalOpen = false"
        />

        <CatalogItemModal v-model:open="quickAddOpen" kind="product" @saved="onProductSaved" />
    </AppLayout>
</template>
