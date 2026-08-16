<script setup lang="ts">
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
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
    is_deposit_default?: boolean;
    is_outstanding?: boolean;
    in_use: boolean;
}

const props = defineProps<{
    orderStatuses: StatusRow[];
    paymentStatuses: StatusRow[];
}>();

const orderList = ref<StatusRow[]>([...props.orderStatuses]);
const paymentList = ref<StatusRow[]>([...props.paymentStatuses]);

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

function updateOrderStatus(status: StatusRow, data: Partial<StatusRow>) {
    router.patch(route('settings.statuses.order.update', status.id), data, { preserveScroll: true });
}

function updatePaymentStatus(status: StatusRow, data: Partial<StatusRow>) {
    router.patch(route('settings.statuses.payment.update', status.id), data, { preserveScroll: true });
}

function deleteOrderStatus(status: StatusRow) {
    if (!confirm(`Izbrišeš status naročila "${status.label}"?`)) return;
    router.delete(route('settings.statuses.order.destroy', status.id), {
        preserveScroll: true,
        onSuccess: () => {
            orderList.value = orderList.value.filter((s) => s.id !== status.id);
        },
    });
}

function deletePaymentStatus(status: StatusRow) {
    if (!confirm(`Izbrišeš status plačila "${status.label}"?`)) return;
    router.delete(route('settings.statuses.payment.destroy', status.id), {
        preserveScroll: true,
        onSuccess: () => {
            paymentList.value = paymentList.value.filter((s) => s.id !== status.id);
        },
    });
}

const newOrderForm = useForm({ label: '', color: '#4B5563', bg: '#F1F2F4' });
const newPaymentForm = useForm({ label: '', color: '#4B5563', bg: '#F1F2F4' });

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
</script>

<template>
    <Head title="Nastavitve · Statusi" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Statusi naročil in plačil</h1>
        </template>

        <div class="mx-auto max-w-2xl space-y-6 px-4 py-6 sm:px-6 sm:py-8">
            <SectionCard
                title="Statusi naročil"
                subtitle="Poimenuj, prebarvaj, dodaj ali odstrani statuse, ki jih uporabljaš za naročila"
            >
                <draggable v-model="orderList" item-key="id" handle=".drag-handle" class="space-y-2" @change="reorderOrder">
                    <template #item="{ element: status }: { element: StatusRow }">
                        <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2.5">
                            <GripVertical :size="14" class="drag-handle shrink-0 cursor-grab text-neutral-300" />
                            <input
                                type="color"
                                :value="status.bg"
                                class="h-7 w-7 shrink-0 cursor-pointer rounded border border-neutral-200"
                                @change="updateOrderStatus(status, { bg: ($event.target as HTMLInputElement).value })"
                            />
                            <input
                                :value="status.label"
                                type="text"
                                class="min-w-0 flex-1 rounded-md border border-neutral-200 px-2 py-1.5 text-sm outline-none focus:border-neutral-400"
                                @change="updateOrderStatus(status, { label: ($event.target as HTMLInputElement).value })"
                            />
                            <label class="flex shrink-0 items-center gap-1 text-xs text-neutral-500" title="Privzet status za nova naročila">
                                <input
                                    type="radio"
                                    name="order-default"
                                    :checked="status.is_default"
                                    @change="updateOrderStatus(status, { is_default: true })"
                                />
                                privzet
                            </label>
                            <label class="flex shrink-0 items-center gap-1 text-xs text-neutral-500" title="Šteje kot zaključeno naročilo">
                                <input
                                    type="checkbox"
                                    :checked="status.is_completed"
                                    @change="updateOrderStatus(status, { is_completed: ($event.target as HTMLInputElement).checked })"
                                />
                                zaključeno
                            </label>
                            <label class="flex shrink-0 items-center gap-1 text-xs text-neutral-500" title="Šteje kot preklicano naročilo">
                                <input
                                    type="checkbox"
                                    :checked="status.is_cancelled"
                                    @change="updateOrderStatus(status, { is_cancelled: ($event.target as HTMLInputElement).checked })"
                                />
                                preklicano
                            </label>
                            <button
                                type="button"
                                :disabled="status.in_use"
                                :title="status.in_use ? 'Status je v uporabi, zato ga ni mogoče izbrisati' : 'Izbriši status'"
                                class="shrink-0 rounded-md p-1.5 text-neutral-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-neutral-400"
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
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50 disabled:opacity-50"
                    >
                        <Plus :size="14" /> Dodaj
                    </button>
                </form>
            </SectionCard>

            <SectionCard
                title="Statusi plačil"
                subtitle="Skupni seznam za naročila in termine — npr. če ne sprejemaš are, preprosto ne označi nobenega kot 'privzet ob ari'"
            >
                <draggable v-model="paymentList" item-key="id" handle=".drag-handle" class="space-y-2" @change="reorderPayment">
                    <template #item="{ element: status }: { element: StatusRow }">
                        <div class="flex items-center gap-2 rounded-lg border border-neutral-200 px-3 py-2.5">
                            <GripVertical :size="14" class="drag-handle shrink-0 cursor-grab text-neutral-300" />
                            <input
                                type="color"
                                :value="status.bg"
                                class="h-7 w-7 shrink-0 cursor-pointer rounded border border-neutral-200"
                                @change="updatePaymentStatus(status, { bg: ($event.target as HTMLInputElement).value })"
                            />
                            <input
                                :value="status.label"
                                type="text"
                                class="min-w-0 flex-1 rounded-md border border-neutral-200 px-2 py-1.5 text-sm outline-none focus:border-neutral-400"
                                @change="updatePaymentStatus(status, { label: ($event.target as HTMLInputElement).value })"
                            />
                            <label class="flex shrink-0 items-center gap-1 text-xs text-neutral-500" title="Privzet status za nova naročila/termine">
                                <input
                                    type="radio"
                                    name="payment-default"
                                    :checked="status.is_default"
                                    @change="updatePaymentStatus(status, { is_default: true })"
                                />
                                privzet
                            </label>
                            <label class="flex shrink-0 items-center gap-1 text-xs text-neutral-500" title="Privzet status, ko je plačana ara">
                                <input
                                    type="radio"
                                    name="payment-deposit-default"
                                    :checked="status.is_deposit_default"
                                    @change="updatePaymentStatus(status, { is_deposit_default: true })"
                                />
                                privzet ob ari
                            </label>
                            <label class="flex shrink-0 items-center gap-1 text-xs text-neutral-500" title="Šteje kot neporavnano plačilo">
                                <input
                                    type="checkbox"
                                    :checked="status.is_outstanding"
                                    @change="updatePaymentStatus(status, { is_outstanding: ($event.target as HTMLInputElement).checked })"
                                />
                                neporavnano
                            </label>
                            <button
                                type="button"
                                :disabled="status.in_use"
                                :title="status.in_use ? 'Status je v uporabi, zato ga ni mogoče izbrisati' : 'Izbriši status'"
                                class="shrink-0 rounded-md p-1.5 text-neutral-400 hover:bg-red-50 hover:text-red-600 disabled:cursor-not-allowed disabled:opacity-30 disabled:hover:bg-transparent disabled:hover:text-neutral-400"
                                @click="deletePaymentStatus(status)"
                            >
                                <Trash2 :size="14" />
                            </button>
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
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50 disabled:opacity-50"
                    >
                        <Plus :size="14" /> Dodaj
                    </button>
                </form>
            </SectionCard>
        </div>
    </AppLayout>
</template>
