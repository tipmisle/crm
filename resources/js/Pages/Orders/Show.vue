<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Badge from '@/Components/Badge.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import FollowUpModal from '@/Components/FollowUpModal.vue';
import SendDocumentModal from '@/Components/SendDocumentModal.vue';
import ExternalDocumentModal from '@/Components/ExternalDocumentModal.vue';
import NotifyCustomerModal from '@/Components/NotifyCustomerModal.vue';
import CustomerContactCard from '@/Components/CustomerContactCard.vue';
import DateInput from '@/Components/DateInput.vue';
import { formatMoney, formatDate, formatDateTime, formatTime, normalizeMoneyInput } from '@/lib/format';
import type { ActivityLogEntry, FollowUp, Order, SalesDocument } from '@/types/models';
import type { PageProps } from '@/types';
import { CalendarClock, MessageSquare, Bell, Check, Ban, Settings, FileText, Paperclip, Send } from 'lucide-vue-next';

const props = defineProps<{
    order: Order;
    followUps: FollowUp[];
    activity: ActivityLogEntry[];
    invoiceSettingsConfigured: boolean;
}>();

const page = usePage<PageProps>();
const orderStatuses = computed(() => page.props.orderStatuses ?? []);
const paymentStatuses = computed(() => page.props.paymentStatuses ?? []);
const acceptsDeposit = computed(() => page.props.workspace?.accepts_deposit ?? true);

const fallbackStatus = { label: props.order.status, color: '#4B5563', bg: '#F1F2F4' };
const statusMeta = computed(() => orderStatuses.value.find((s) => s.key === props.order.status) ?? fallbackStatus);
const paymentMeta = computed(
    () => paymentStatuses.value.find((s) => s.key === props.order.payment_status) ?? { ...fallbackStatus, label: props.order.payment_status },
);

function updateStatus(status: string) {
    router.patch(route('orders.update', props.order.id), { status }, { preserveScroll: true });
}

function cancelOrder() {
    if (!confirm(`Prekličeš naročilo ${props.order.order_number}?`)) return;
    updateStatus('cancelled');
}

function updatePayment(payment_status: string) {
    router.patch(route('orders.update', props.order.id), { payment_status }, { preserveScroll: true });
}

const paymentForm = useForm({
    price: props.order.price,
    deposit_amount: props.order.deposit_amount,
    amount_paid: props.order.amount_paid,
});

function savePayment() {
    paymentForm.price = normalizeMoneyInput(paymentForm.price);
    paymentForm.deposit_amount = normalizeMoneyInput(paymentForm.deposit_amount);
    paymentForm.amount_paid = normalizeMoneyInput(paymentForm.amount_paid);
    paymentForm.patch(route('orders.update', props.order.id), { preserveScroll: true });
}

const deadlineForm = useForm({
    due_date: props.order.due_date ?? '',
    due_time: props.order.due_time ?? '',
});

const deliveryForm = useForm({
    delivery_method: props.order.delivery_method ?? (props.order.tracking_number || props.order.tracking_url || props.order.shipped_at ? 'mail' : null),
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

function documentsOfType(type: 'proforma' | 'invoice') {
    return (props.order.sales_documents ?? []).filter((d) => d.type === type);
}
const proformaDocuments = computed(() => documentsOfType('proforma'));
const invoiceDocuments = computed(() => documentsOfType('invoice'));
const otherDocuments = computed(() => (props.order.sales_documents ?? []).filter((d) => d.type === 'other'));

const sendModal = ref<{ open: boolean; document: SalesDocument | null; title: string; submitLabel: string; body: string }>({
    open: false,
    document: null,
    title: '',
    submitLabel: '',
    body: '',
});

function openSendModal(document: SalesDocument) {
    const typeLabel = document.type === 'proforma' ? 'predračun' : 'račun';
    const body =
        document.type === 'proforma'
            ? `Živjo ${customerName.value} 😊 Pošiljam ti predračun za naročilo. Podatke za nakazilo najdeš v priponki.`
            : `Živjo ${customerName.value} 😊 Pošiljam ti račun za naročilo. Hvala!`;

    sendModal.value = {
        open: true,
        document,
        title: document.sent_at ? `Pošlji ${typeLabel} znova` : `Pošlji ${typeLabel} stranki`,
        submitLabel: 'Pošlji',
        body,
    };
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
            <div class="mb-6 flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-neutral-900">{{ order.title }}</h1>
                    <p class="mt-1 text-sm text-neutral-500">{{ order.order_number }} · Ustvarjeno {{ formatDate(order.created_at) }}</p>
                </div>
                <div class="flex items-stretch gap-2">
                    <Link
                        :href="route('settings.statuses.edit')"
                        title="Nastavitve statusov"
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <Settings :size="14" />
                    </Link>
                    <button
                        v-if="order.status !== 'cancelled'"
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

                        <div class="mt-4 flex items-end justify-between gap-4">
                            <div class="max-w-xs flex-1">
                                <label class="block text-xs text-neutral-500">Status naročila</label>
                                <select
                                    :value="order.status"
                                    class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                                    @change="updateStatus(($event.target as HTMLSelectElement).value)"
                                >
                                    <option v-for="s in orderStatuses" :key="s.key" :value="s.key">{{ s.label }}</option>
                                </select>
                            </div>

                            <button
                                v-if="canNotifyCustomer"
                                type="button"
                                class="flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                                @click="notifyModalOpen = true"
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
                                <input
                                    v-model="paymentForm.price"
                                    type="number"
                                    step="0.01"
                                    class="mt-1 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                                    @change="savePayment"
                                />
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
                            <div v-for="doc in [...proformaDocuments, ...invoiceDocuments, ...otherDocuments]" :key="doc.id" class="flex items-center justify-between rounded-lg bg-neutral-50 px-3 py-2.5">
                                <div class="flex items-center gap-2.5">
                                    <FileText :size="16" class="text-neutral-400" />
                                    <div>
                                        <p class="text-sm font-medium text-neutral-800">
                                            {{ doc.type === 'proforma' ? 'Predračun' : doc.type === 'invoice' ? 'Račun' : 'Drugo' }}
                                            {{ doc.document_number ?? doc.external_document_number }}
                                            <span v-if="doc.source === 'external'" class="ml-1 text-xs font-normal text-neutral-400">(zunanji)</span>
                                        </p>
                                        <p class="text-xs text-neutral-400">
                                            Izdano {{ formatDate(doc.issued_at) }}
                                            <span v-if="doc.sent_at"> · Poslano {{ formatDate(doc.sent_at) }}</span>
                                            <span v-else> · Ni še poslano</span>
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-3">
                                    <a :href="route('documents.download', doc.id)" target="_blank" class="text-xs font-medium text-[var(--color-accent-500)] hover:underline">
                                        Odpri
                                    </a>
                                    <button v-if="order.conversation" type="button" class="text-xs font-medium text-[var(--color-accent-500)] hover:underline" @click="openSendModal(doc)">
                                        {{ doc.sent_at ? 'Pošlji znova' : 'Pošlji stranki' }}
                                    </button>
                                    <button v-if="order.conversation && doc.sent_at" type="button" class="text-xs font-medium text-neutral-500 hover:underline" @click="openReminderModal(doc)">
                                        Opomnik
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

                            <div v-for="followUp in followUps" :key="`f-${followUp.id}`" class="flex gap-2.5 text-sm">
                                <Bell :size="13" class="mt-0.5 shrink-0 text-amber-500" />
                                <div>
                                    <p class="text-neutral-700">{{ followUp.note }}</p>
                                    <p class="text-xs text-neutral-400">
                                        Opomnik {{ followUp.completed_at ? 'zaključen' : 'zapade' }} {{ formatDateTime(followUp.due_at) }}
                                    </p>
                                </div>
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
                        <div class="mt-3 space-y-2">
                            <label class="flex items-center gap-2 text-sm text-neutral-700">
                                <input
                                    v-model="deliveryForm.delivery_method"
                                    type="radio"
                                    value="mail"
                                    class="accent-[var(--color-ink-950)]"
                                    @change="saveDelivery"
                                />
                                Pošiljanje po pošti
                            </label>
                            <label class="flex items-center gap-2 text-sm text-neutral-700">
                                <input
                                    v-model="deliveryForm.delivery_method"
                                    type="radio"
                                    value="pickup"
                                    class="accent-[var(--color-ink-950)]"
                                    @change="saveDelivery"
                                />
                                Osebni prevzem
                            </label>
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

        <NotifyCustomerModal
            :show="notifyModalOpen"
            :order="order"
            :action="route('orders.notify.store', order.id)"
            @close="notifyModalOpen = false"
        />

    </AppLayout>
</template>
