<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import Modal from '@/Components/Modal.vue';
import { useConfirm } from '@/composables/useConfirm';
import { GripVertical, Plus, Trash2 } from 'lucide-vue-next';

interface StatusRow {
    id: number;
    key: string;
    label: string;
    color: string;
    bg: string;
    sort_order: number;
    is_default: boolean;
    is_completed?: boolean;
    is_cancelled?: boolean;
    is_no_show?: boolean;
    is_refunded?: boolean;
    is_deposit_default?: boolean;
    is_outstanding?: boolean;
    is_paid?: boolean;
    in_use: boolean;
}

const props = defineProps<{
    orderStatuses: StatusRow[];
    paymentStatuses: StatusRow[];
    appointmentStatuses: StatusRow[];
}>();

const { confirm } = useConfirm();

const orderList = ref<StatusRow[]>([...props.orderStatuses]);
const paymentList = ref<StatusRow[]>([...props.paymentStatuses]);
const appointmentList = ref<StatusRow[]>([...props.appointmentStatuses]);

function reorderOrder() {
    router.post(
        route('settings.statuses.order.reorder'),
        { ids: orderList.value.map((s) => s.id) },
        { preserveScroll: true, preserveState: true },
    );
}

function reorderPayment() {
    router.post(
        route('settings.statuses.payment.reorder'),
        { ids: paymentList.value.map((s) => s.id) },
        { preserveScroll: true, preserveState: true },
    );
}

function reorderAppointment() {
    router.post(
        route('settings.statuses.appointment.reorder'),
        { ids: appointmentList.value.map((s) => s.id) },
        { preserveScroll: true, preserveState: true },
    );
}

function updateOrderStatus(status: StatusRow, data: Partial<StatusRow>) {
    router.patch(route('settings.statuses.order.update', status.id), data, { preserveScroll: true });
}

function updatePaymentStatus(status: StatusRow, data: Partial<StatusRow>) {
    router.patch(route('settings.statuses.payment.update', status.id), data, { preserveScroll: true });
}

function updateAppointmentStatus(status: StatusRow, data: Partial<StatusRow>) {
    router.patch(route('settings.statuses.appointment.update', status.id), data, { preserveScroll: true });
}

// A workspace must always have exactly one order status filling each of
// these 4 fixed roles, and one payment status filling each of these 4 — see
// Settings\OrderStatusController/PaymentStatusController::destroy(), which
// rejects deleting whichever status currently holds a role. Every row gets
// a "vloga" dropdown showing which of these fixed roles (if any) it holds;
// picking a different role on it PATCHes that flag => true, which the
// backend moves off whatever status held it before (see
// OrderStatusController::update()).
const ORDER_ROLES: { flag: 'is_default' | 'is_completed' | 'is_cancelled' | 'is_refunded'; label: string }[] = [
    { flag: 'is_default', label: 'privzet' },
    { flag: 'is_completed', label: 'zaključeno' },
    { flag: 'is_cancelled', label: 'preklicano' },
    { flag: 'is_refunded', label: 'vračilo' },
];

const PAYMENT_ROLES: { flag: 'is_default' | 'is_deposit_default' | 'is_paid' | 'is_refunded'; label: string }[] = [
    { flag: 'is_default', label: 'privzet' },
    { flag: 'is_deposit_default', label: 'ara' },
    { flag: 'is_paid', label: 'plačano' },
    { flag: 'is_refunded', label: 'vračilo' },
];

const APPOINTMENT_ROLES: { flag: 'is_default' | 'is_completed' | 'is_cancelled' | 'is_no_show' | 'is_refunded'; label: string }[] = [
    { flag: 'is_default', label: 'privzet' },
    { flag: 'is_completed', label: 'zaključeno' },
    { flag: 'is_cancelled', label: 'preklicano' },
    { flag: 'is_no_show', label: 'ni se zglasil/a' },
    { flag: 'is_refunded', label: 'vračilo' },
];

function orderRole(status: StatusRow) {
    return ORDER_ROLES.find((role) => status[role.flag]) ?? null;
}

function paymentRole(status: StatusRow) {
    return PAYMENT_ROLES.find((role) => status[role.flag]) ?? null;
}

function appointmentRole(status: StatusRow) {
    return APPOINTMENT_ROLES.find((role) => status[role.flag]) ?? null;
}

function isOrderStatusProtected(status: StatusRow) {
    return orderRole(status) !== null;
}

function isPaymentStatusProtected(status: StatusRow) {
    return paymentRole(status) !== null;
}

function isAppointmentStatusProtected(status: StatusRow) {
    return appointmentRole(status) !== null;
}

function moveOrderRole(status: StatusRow, flag: string) {
    if (!flag) return;
    updateOrderStatus(status, { [flag]: true } as Partial<StatusRow>);
}

function movePaymentRole(status: StatusRow, flag: string) {
    if (!flag) return;
    updatePaymentStatus(status, { [flag]: true } as Partial<StatusRow>);
}

function moveAppointmentRole(status: StatusRow, flag: string) {
    if (!flag) return;
    updateAppointmentStatus(status, { [flag]: true } as Partial<StatusRow>);
}

function setOutstanding(status: StatusRow, checked: boolean) {
    updatePaymentStatus(status, { is_outstanding: checked });
}

const reassign = ref<{ type: 'order' | 'payment' | 'appointment'; status: StatusRow; reassignTo: string; processing: boolean; error: string } | null>(null);

const reassignOptions = computed(() => {
    if (!reassign.value) return [];
    const list = reassign.value.type === 'order' ? orderList.value : reassign.value.type === 'payment' ? paymentList.value : appointmentList.value;
    return list.filter((s) => s.id !== reassign.value!.status.id);
});

async function deleteOrderStatus(status: StatusRow) {
    if (isOrderStatusProtected(status)) return;
    if (status.in_use) {
        reassign.value = { type: 'order', status, reassignTo: '', processing: false, error: '' };
        return;
    }
    if (!(await confirm(`Izbrišeš status naročila "${status.label}"?`, { danger: true }))) return;
    router.delete(route('settings.statuses.order.destroy', status.id), {
        preserveScroll: true,
        onSuccess: () => {
            orderList.value = orderList.value.filter((s) => s.id !== status.id);
        },
    });
}

async function deletePaymentStatus(status: StatusRow) {
    if (isPaymentStatusProtected(status)) return;
    if (status.in_use) {
        reassign.value = { type: 'payment', status, reassignTo: '', processing: false, error: '' };
        return;
    }
    if (!(await confirm(`Izbrišeš status plačila "${status.label}"?`, { danger: true }))) return;
    router.delete(route('settings.statuses.payment.destroy', status.id), {
        preserveScroll: true,
        onSuccess: () => {
            paymentList.value = paymentList.value.filter((s) => s.id !== status.id);
        },
    });
}

async function deleteAppointmentStatus(status: StatusRow) {
    if (isAppointmentStatusProtected(status)) return;
    if (status.in_use) {
        reassign.value = { type: 'appointment', status, reassignTo: '', processing: false, error: '' };
        return;
    }
    if (!(await confirm(`Izbrišeš status termina "${status.label}"?`, { danger: true }))) return;
    router.delete(route('settings.statuses.appointment.destroy', status.id), {
        preserveScroll: true,
        onSuccess: () => {
            appointmentList.value = appointmentList.value.filter((s) => s.id !== status.id);
        },
    });
}

function confirmReassignAndDelete() {
    if (!reassign.value || !reassign.value.reassignTo) return;

    const { type, status, reassignTo } = reassign.value;
    const routeName =
        type === 'order' ? 'settings.statuses.order.destroy' : type === 'payment' ? 'settings.statuses.payment.destroy' : 'settings.statuses.appointment.destroy';

    reassign.value.processing = true;

    router.delete(route(routeName, status.id), {
        data: { reassign_to: reassignTo },
        preserveScroll: true,
        onSuccess: () => {
            if (type === 'order') {
                orderList.value = orderList.value.filter((s) => s.id !== status.id);
            } else if (type === 'payment') {
                paymentList.value = paymentList.value.filter((s) => s.id !== status.id);
            } else {
                appointmentList.value = appointmentList.value.filter((s) => s.id !== status.id);
            }
            reassign.value = null;
        },
        onError: (errors) => {
            if (reassign.value) {
                reassign.value.processing = false;
                reassign.value.error = Object.values(errors)[0] as string ?? 'Prišlo je do napake.';
            }
        },
    });
}

const newOrderForm = useForm({ label: '', color: '#4B5563', bg: '#F1F2F4' });
const newPaymentForm = useForm({ label: '', color: '#4B5563', bg: '#F1F2F4' });
const newAppointmentForm = useForm({ label: '', color: '#4B5563', bg: '#F1F2F4' });

function addOrderStatus() {
    if (!newOrderForm.label.trim()) return;
    newOrderForm.post(route('settings.statuses.order.store'), {
        preserveScroll: true,
        onSuccess: () => {
            newOrderForm.reset();
            router.reload({ only: ['orderStatuses'], onSuccess: (page: any) => (orderList.value = [...page.props.orderStatuses]) });
        },
    });
}

function addPaymentStatus() {
    if (!newPaymentForm.label.trim()) return;
    newPaymentForm.post(route('settings.statuses.payment.store'), {
        preserveScroll: true,
        onSuccess: () => {
            newPaymentForm.reset();
            router.reload({ only: ['paymentStatuses'], onSuccess: (page: any) => (paymentList.value = [...page.props.paymentStatuses]) });
        },
    });
}

function addAppointmentStatus() {
    if (!newAppointmentForm.label.trim()) return;
    newAppointmentForm.post(route('settings.statuses.appointment.store'), {
        preserveScroll: true,
        onSuccess: () => {
            newAppointmentForm.reset();
            router.reload({ only: ['appointmentStatuses'], onSuccess: (page: any) => (appointmentList.value = [...page.props.appointmentStatuses]) });
        },
    });
}
</script>

<template>
    <Head title="Nastavitve · Statusi" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Statusi naročil, plačil in terminov</h1>
        </template>

        <div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <SectionCard
                    title="Statusi naročil"
                    subtitle="Poimenuj, prebarvaj, dodaj ali odstrani statuse, ki jih uporabljaš za naročila"
                >
                    <draggable
                        v-model="orderList"
                        item-key="id"
                        handle=".drag-handle"
                        class="divide-y divide-neutral-100 overflow-hidden rounded-lg border border-neutral-200"
                        @change="reorderOrder"
                    >
                        <template #item="{ element: status }: { element: StatusRow }">
                            <div class="group flex items-center gap-2.5 px-3 py-2.5 transition-colors hover:bg-neutral-50">
                                <GripVertical :size="14" class="drag-handle shrink-0 cursor-grab text-neutral-300 group-hover:text-neutral-400" />
                                <input
                                    type="color"
                                    :value="status.bg"
                                    class="color-swatch h-6 w-6 shrink-0 cursor-pointer rounded-full"
                                    @change="updateOrderStatus(status, { bg: ($event.target as HTMLInputElement).value })"
                                />
                                <input
                                    :value="status.label"
                                    type="text"
                                    class="min-w-0 flex-1 rounded-md border border-transparent bg-transparent px-2 py-1.5 text-sm font-medium text-neutral-800 outline-none transition-colors hover:border-neutral-200 focus:border-neutral-300 focus:bg-white"
                                    @change="updateOrderStatus(status, { label: ($event.target as HTMLInputElement).value })"
                                />
                                <select
                                    :value="orderRole(status)?.flag ?? ''"
                                    title="Vloga tega statusa"
                                    class="shrink-0 rounded-full border-none bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-500 outline-none"
                                    @change="moveOrderRole(status, ($event.target as HTMLSelectElement).value)"
                                >
                                    <option value="" disabled>brez vloge</option>
                                    <option v-for="role in ORDER_ROLES" :key="role.flag" :value="role.flag">{{ role.label }}</option>
                                </select>
                                <button
                                    type="button"
                                    :disabled="isOrderStatusProtected(status)"
                                    :title="
                                        isOrderStatusProtected(status)
                                            ? 'Ta status je obvezen (privzet, zaključeno, preklicano ali vračilo) — najprej premakni oznako na drug status'
                                            : status.in_use
                                              ? 'Status je v uporabi — izberi, kam prestaviti obstoječa naročila'
                                              : 'Izbriši status'
                                    "
                                    class="shrink-0 rounded-md p-1.5 text-neutral-400 transition-colors hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-neutral-400"
                                    @click="deleteOrderStatus(status)"
                                >
                                    <Trash2 :size="14" />
                                </button>
                            </div>
                        </template>
                    </draggable>

                    <form class="mt-3 flex items-center gap-2" @submit.prevent="addOrderStatus">
                        <input
                            v-model="newOrderForm.label"
                            type="text"
                            placeholder="Nov status naročila…"
                            class="flex-1 rounded-md border border-neutral-200 px-3 py-1.5 text-sm outline-none focus:border-neutral-400"
                        />
                        <button
                            type="submit"
                            :disabled="newOrderForm.processing"
                            class="flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 transition-colors hover:border-[var(--color-accent-300)] hover:bg-[var(--color-accent-50)] hover:text-[var(--color-accent-700)] disabled:opacity-50"
                        >
                            <Plus :size="14" /> Dodaj
                        </button>
                    </form>
                </SectionCard>

                <SectionCard
                    title="Statusi terminov"
                    subtitle="Poimenuj, prebarvaj, dodaj ali odstrani statuse, ki jih uporabljaš za termine"
                >
                    <draggable
                        v-model="appointmentList"
                        item-key="id"
                        handle=".drag-handle"
                        class="divide-y divide-neutral-100 overflow-hidden rounded-lg border border-neutral-200"
                        @change="reorderAppointment"
                    >
                        <template #item="{ element: status }: { element: StatusRow }">
                            <div class="group flex items-center gap-2.5 px-3 py-2.5 transition-colors hover:bg-neutral-50">
                                <GripVertical :size="14" class="drag-handle shrink-0 cursor-grab text-neutral-300 group-hover:text-neutral-400" />
                                <input
                                    type="color"
                                    :value="status.bg"
                                    class="color-swatch h-6 w-6 shrink-0 cursor-pointer rounded-full"
                                    @change="updateAppointmentStatus(status, { bg: ($event.target as HTMLInputElement).value })"
                                />
                                <input
                                    :value="status.label"
                                    type="text"
                                    class="min-w-0 flex-1 rounded-md border border-transparent bg-transparent px-2 py-1.5 text-sm font-medium text-neutral-800 outline-none transition-colors hover:border-neutral-200 focus:border-neutral-300 focus:bg-white"
                                    @change="updateAppointmentStatus(status, { label: ($event.target as HTMLInputElement).value })"
                                />
                                <select
                                    :value="appointmentRole(status)?.flag ?? ''"
                                    title="Vloga tega statusa"
                                    class="shrink-0 rounded-full border-none bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-500 outline-none"
                                    @change="moveAppointmentRole(status, ($event.target as HTMLSelectElement).value)"
                                >
                                    <option value="" disabled>brez vloge</option>
                                    <option v-for="role in APPOINTMENT_ROLES" :key="role.flag" :value="role.flag">{{ role.label }}</option>
                                </select>
                                <button
                                    type="button"
                                    :disabled="isAppointmentStatusProtected(status)"
                                    :title="
                                        isAppointmentStatusProtected(status)
                                            ? 'Ta status je obvezen (privzet, zaključeno, preklicano, ni se zglasil/a ali vračilo) — najprej premakni oznako na drug status'
                                            : status.in_use
                                              ? 'Status je v uporabi — izberi, kam prestaviti obstoječe termine'
                                              : 'Izbriši status'
                                    "
                                    class="shrink-0 rounded-md p-1.5 text-neutral-400 transition-colors hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-neutral-400"
                                    @click="deleteAppointmentStatus(status)"
                                >
                                    <Trash2 :size="14" />
                                </button>
                            </div>
                        </template>
                    </draggable>

                    <form class="mt-3 flex items-center gap-2" @submit.prevent="addAppointmentStatus">
                        <input
                            v-model="newAppointmentForm.label"
                            type="text"
                            placeholder="Nov status termina…"
                            class="flex-1 rounded-md border border-neutral-200 px-3 py-1.5 text-sm outline-none focus:border-neutral-400"
                        />
                        <button
                            type="submit"
                            :disabled="newAppointmentForm.processing"
                            class="flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 transition-colors hover:border-[var(--color-accent-300)] hover:bg-[var(--color-accent-50)] hover:text-[var(--color-accent-700)] disabled:opacity-50"
                        >
                            <Plus :size="14" /> Dodaj
                        </button>
                    </form>
                </SectionCard>

                <SectionCard
                    title="Statusi plačil"
                    subtitle="Skupni seznam za naročila in termine"
                >
                <draggable
                    v-model="paymentList"
                    item-key="id"
                    handle=".drag-handle"
                    class="divide-y divide-neutral-100 overflow-hidden rounded-lg border border-neutral-200"
                    @change="reorderPayment"
                >
                    <template #item="{ element: status }: { element: StatusRow }">
                        <div class="group px-3 py-2.5 transition-colors hover:bg-neutral-50">
                            <div class="flex items-center gap-1.5">
                                <GripVertical :size="14" class="drag-handle shrink-0 cursor-grab text-neutral-300 group-hover:text-neutral-400" />
                                <input
                                    type="color"
                                    :value="status.bg"
                                    class="color-swatch h-6 w-6 shrink-0 cursor-pointer rounded-full"
                                    @change="updatePaymentStatus(status, { bg: ($event.target as HTMLInputElement).value })"
                                />
                                <input
                                    :value="status.label"
                                    type="text"
                                    class="min-w-0 flex-1 rounded-md border border-transparent bg-transparent px-2 py-1.5 text-sm font-medium text-neutral-800 outline-none transition-colors hover:border-neutral-200 focus:border-neutral-300 focus:bg-white"
                                    @change="updatePaymentStatus(status, { label: ($event.target as HTMLInputElement).value })"
                                />
                                <select
                                    :value="paymentRole(status)?.flag ?? ''"
                                    title="Vloga tega statusa"
                                    class="shrink-0 rounded-full border-none bg-neutral-100 px-2.5 py-1 text-xs font-medium text-neutral-500 outline-none"
                                    @change="movePaymentRole(status, ($event.target as HTMLSelectElement).value)"
                                >
                                    <option value="" disabled>brez vloge</option>
                                    <option v-for="role in PAYMENT_ROLES" :key="role.flag" :value="role.flag">{{ role.label }}</option>
                                </select>
                                <button
                                    type="button"
                                    :disabled="isPaymentStatusProtected(status)"
                                    :title="
                                        isPaymentStatusProtected(status)
                                            ? 'Ta status je obvezen (neplačano, ara, plačano ali vračilo) — najprej premakni oznako na drug status'
                                            : status.in_use
                                              ? 'Status je v uporabi — izberi, kam prestaviti obstoječe zapise'
                                              : 'Izbriši status'
                                    "
                                    class="shrink-0 rounded-md p-1.5 text-neutral-400 transition-colors hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-neutral-400"
                                    @click="deletePaymentStatus(status)"
                                >
                                    <Trash2 :size="14" />
                                </button>
                            </div>
                            <div class="ml-[3.375rem] mt-1.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-neutral-500">
                                <label class="flex items-center gap-1.5">
                                    <input
                                        type="checkbox"
                                        :checked="status.is_outstanding"
                                        @change="setOutstanding(status, ($event.target as HTMLInputElement).checked)"
                                    />
                                    Šteje kot neplačano
                                </label>
                            </div>
                        </div>
                    </template>
                </draggable>

                <form class="mt-3 flex items-center gap-2" @submit.prevent="addPaymentStatus">
                    <input
                        v-model="newPaymentForm.label"
                        type="text"
                        placeholder="Nov status plačila…"
                        class="flex-1 rounded-md border border-neutral-200 px-3 py-1.5 text-sm outline-none focus:border-neutral-400"
                    />
                    <button
                        type="submit"
                        :disabled="newPaymentForm.processing"
                        class="flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 transition-colors hover:border-[var(--color-accent-300)] hover:bg-[var(--color-accent-50)] hover:text-[var(--color-accent-700)] disabled:opacity-50"
                    >
                        <Plus :size="14" /> Dodaj
                    </button>
                </form>
            </SectionCard>
            </div>
        </div>

        <Modal :show="reassign !== null" max-width="sm" @close="reassign = null">
            <div v-if="reassign" class="p-6">
                <h2 class="text-base font-semibold text-neutral-900">Status je v uporabi</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    Status "{{ reassign.status.label }}" trenutno uporablja vsaj eno
                    {{ reassign.type === 'order' ? 'naročilo' : reassign.type === 'appointment' ? 'termin' : 'naročilo ali termin' }}. Izberi status, na katerega naj se ti prestavijo, nato bo "{{ reassign.status.label }}" izbrisan.
                </p>

                <select
                    v-model="reassign.reassignTo"
                    class="mt-4 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                >
                    <option value="" disabled>Izberi status…</option>
                    <option v-for="option in reassignOptions" :key="option.id" :value="option.key">{{ option.label }}</option>
                </select>

                <p v-if="reassign.error" class="mt-2 text-xs text-red-500">{{ reassign.error }}</p>

                <div class="mt-5 flex justify-end gap-2">
                    <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100" @click="reassign = null">
                        Prekliči
                    </button>
                    <button
                        type="button"
                        :disabled="!reassign.reassignTo || reassign.processing"
                        class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        @click="confirmReassignAndDelete"
                    >
                        Prestavi in izbriši
                    </button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>

<style scoped>
/* Browsers render an internal border/padding on the <input type="color">
   swatch itself, on top of our own border — strip it so only ours shows. */
.color-swatch::-webkit-color-swatch-wrapper {
    padding: 0;
}
.color-swatch::-webkit-color-swatch {
    border: none;
    border-radius: 9999px;
}
.color-swatch::-moz-color-swatch {
    border: none;
    border-radius: 9999px;
}
</style>
