<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import Badge from '@/Components/Badge.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import FollowUpModal from '@/Components/FollowUpModal.vue';
import { formatMoney, formatDate, formatDateTime, formatTime } from '@/lib/format';
import { ORDER_STATUS_ORDER, ORDER_STATUS_META, PAYMENT_STATUS_META } from '@/lib/statuses';
import type { ActivityLogEntry, FollowUp, Order, OrderStatus, PaymentStatus } from '@/types/models';
import { CalendarClock, MessageSquare, Bell, Check } from 'lucide-vue-next';

const props = defineProps<{
    order: Order;
    followUps: FollowUp[];
    activity: ActivityLogEntry[];
}>();

const statusMeta = computed(() => ORDER_STATUS_META[props.order.status]);
const paymentMeta = computed(() => PAYMENT_STATUS_META[props.order.payment_status]);

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
    paymentForm.patch(route('orders.update', props.order.id), { preserveScroll: true });
}

const deadlineForm = useForm({
    due_date: props.order.due_date ?? '',
    due_time: props.order.due_time ?? '',
});

function saveDeadline() {
    deadlineForm.patch(route('orders.update', props.order.id), { preserveScroll: true });
}

const noteForm = useForm({ body: '' });

function submitNote() {
    noteForm.post(route('orders.notes.store', props.order.id), {
        preserveScroll: true,
        onSuccess: () => noteForm.reset(),
    });
}

const followUpOpen = ref(false);
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

        <div class="mx-auto max-w-5xl px-6 py-8">
            <div class="mb-6 flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-neutral-900">{{ order.title }}</h1>
                    <p class="mt-1 text-sm text-neutral-500">{{ order.order_number }} · Ustvarjeno {{ formatDate(order.created_at) }}</p>
                </div>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    @click="followUpOpen = true"
                >
                    <Bell :size="14" /> Nastavi opomnik
                </button>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h2 class="text-sm font-semibold text-neutral-900">Podrobnosti naročila</h2>
                        <p class="mt-2 text-sm text-neutral-700">{{ order.description || 'Opis ni dodan.' }}</p>

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <h3 class="text-xs font-medium text-neutral-500">Opombe stranke</h3>
                                <p class="mt-1 text-sm text-neutral-700">{{ order.customer_notes || '—' }}</p>
                            </div>
                            <div>
                                <h3 class="text-xs font-medium text-neutral-500">Interne opombe</h3>
                                <p class="mt-1 text-sm text-neutral-700">{{ order.internal_notes || '—' }}</p>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h2 class="text-sm font-semibold text-neutral-900">Opombe</h2>

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
                                class="rounded-md bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800 disabled:opacity-50"
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

                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h2 class="text-sm font-semibold text-neutral-900">Časovnica</h2>
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

                    <section v-if="order.conversation" class="rounded-xl border border-neutral-200 bg-white p-5">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-neutral-900">Izvorni pogovor</h2>
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
                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h3 class="text-xs font-semibold text-neutral-500 uppercase">Stranka</h3>
                        <Link
                            :href="route('customers.show', order.customer!.id)"
                            class="mt-3 flex items-center gap-3 rounded-lg hover:bg-neutral-50 -mx-2 px-2 py-1.5"
                        >
                            <Avatar :name="order.customer?.full_name ?? ''" size="md" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-neutral-900">{{ order.customer?.full_name }}</p>
                                <p class="truncate text-xs text-neutral-500">{{ order.customer?.email }}</p>
                            </div>
                        </Link>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h3 class="text-xs font-semibold text-neutral-500 uppercase">Status naročila</h3>
                        <select
                            :value="order.status"
                            class="mt-2 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                            @change="updateStatus(($event.target as HTMLSelectElement).value)"
                        >
                            <option v-for="s in ORDER_STATUS_ORDER" :key="s" :value="s">{{ ORDER_STATUS_META[s as OrderStatus].label }}</option>
                        </select>
                        <button
                            v-if="order.status !== 'cancelled'"
                            type="button"
                            class="mt-2 w-full rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100"
                            @click="cancelOrder"
                        >
                            Prekliči naročilo
                        </button>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h3 class="text-xs font-semibold text-neutral-500 uppercase">Rok</h3>
                        <div class="mt-2 space-y-2">
                            <input
                                v-model="deadlineForm.due_date"
                                type="date"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                            />
                            <input
                                v-model="deadlineForm.due_time"
                                type="time"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                            />
                            <button
                                type="button"
                                class="w-full rounded-md bg-neutral-100 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-200"
                                @click="saveDeadline"
                            >
                                Shrani rok
                            </button>
                        </div>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h3 class="text-xs font-semibold text-neutral-500 uppercase">Plačilo</h3>
                        <div class="mt-2 space-y-2">
                            <label class="block text-xs text-neutral-500">Cena</label>
                            <input
                                v-model="paymentForm.price"
                                type="number"
                                step="0.01"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                            />
                            <label class="block text-xs text-neutral-500">Ara</label>
                            <input
                                v-model="paymentForm.deposit_amount"
                                type="number"
                                step="0.01"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                            />
                            <label class="block text-xs text-neutral-500">Plačan znesek</label>
                            <input
                                v-model="paymentForm.amount_paid"
                                type="number"
                                step="0.01"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                            />
                            <button
                                type="button"
                                class="w-full rounded-md bg-neutral-100 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-200"
                                @click="savePayment"
                            >
                                Shrani plačilo
                            </button>

                            <select
                                :value="order.payment_status"
                                class="mt-2 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                                @change="updatePayment(($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="(meta, key) in PAYMENT_STATUS_META" :key="key" :value="key">{{ meta.label }}</option>
                            </select>
                        </div>
                    </section>

                    <section v-if="order.channel" class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h3 class="text-xs font-semibold text-neutral-500 uppercase">Vir</h3>
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
    </AppLayout>
</template>
