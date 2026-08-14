<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import FollowUpModal from '@/Components/FollowUpModal.vue';
import { formatMoney, formatDate, formatDateTime } from '@/lib/format';
import { APPOINTMENT_STATUS_ORDER, APPOINTMENT_STATUS_META, PAYMENT_STATUS_META } from '@/lib/statuses';
import type { ActivityLogEntry, Appointment, AppointmentStatus, FollowUp } from '@/types/models';
import { MessageSquare, Bell } from 'lucide-vue-next';

const props = defineProps<{
    appointment: Appointment;
    followUps: FollowUp[];
    activity: ActivityLogEntry[];
}>();

const statusMeta = computed(() => APPOINTMENT_STATUS_META[props.appointment.status]);
const paymentMeta = computed(() => PAYMENT_STATUS_META[props.appointment.payment_status]);

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

function markNoShow() {
    if (!confirm(`Označiš, da se stranka ni zglasila za ${props.appointment.appointment_number}?`)) return;
    updateStatus('no_show');
}

function updatePayment(payment_status: string) {
    router.patch(route('appointments.update', props.appointment.id), { payment_status }, { preserveScroll: true });
}

const paymentForm = useForm({
    price: props.appointment.price ?? '',
    deposit_amount: props.appointment.deposit_amount,
    amount_paid: props.appointment.amount_paid,
});

function savePayment() {
    paymentForm.patch(route('appointments.update', props.appointment.id), { preserveScroll: true });
}

const scheduleForm = useForm({
    appointment_date: props.appointment.appointment_date,
    start_time: props.appointment.start_time,
    duration_minutes: props.appointment.duration_minutes,
});

function saveSchedule() {
    scheduleForm.patch(route('appointments.update', props.appointment.id), { preserveScroll: true });
}

const followUpOpen = ref(false);
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

        <div class="mx-auto max-w-5xl px-6 py-8">
            <div class="mb-6 flex items-start justify-between">
                <div>
                    <h1 class="text-2xl font-semibold text-neutral-900">{{ appointment.service_name }}</h1>
                    <p class="mt-1 text-sm text-neutral-500">
                        {{ appointment.appointment_number }} · {{ formatDate(appointment.appointment_date) }} ob {{ appointment.start_time.slice(0, 5) }}
                    </p>
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
                        <h2 class="text-sm font-semibold text-neutral-900">Podrobnosti termina</h2>
                        <p class="mt-2 text-sm text-neutral-700">{{ appointment.description || 'Opis ni dodan.' }}</p>

                        <div class="mt-4 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <h3 class="text-xs font-medium text-neutral-500">Opombe stranke</h3>
                                <p class="mt-1 text-sm text-neutral-700">{{ appointment.customer_notes || '—' }}</p>
                            </div>
                            <div>
                                <h3 class="text-xs font-medium text-neutral-500">Interne opombe</h3>
                                <p class="mt-1 text-sm text-neutral-700">{{ appointment.internal_notes || '—' }}</p>
                            </div>
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

                    <section v-if="appointment.conversation" class="rounded-xl border border-neutral-200 bg-white p-5">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-neutral-900">Izvorni pogovor</h2>
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
                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h3 class="text-xs font-semibold text-neutral-500 uppercase">Stranka</h3>
                        <Link
                            :href="route('customers.show', appointment.customer!.id)"
                            class="mt-3 flex items-center gap-3 rounded-lg hover:bg-neutral-50 -mx-2 px-2 py-1.5"
                        >
                            <Avatar :name="appointment.customer?.full_name ?? ''" size="md" />
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-neutral-900">{{ appointment.customer?.full_name }}</p>
                                <p class="truncate text-xs text-neutral-500">{{ appointment.customer?.email }}</p>
                            </div>
                        </Link>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h3 class="text-xs font-semibold text-neutral-500 uppercase">Status termina</h3>
                        <select
                            :value="appointment.status"
                            class="mt-2 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                            @change="updateStatus(($event.target as HTMLSelectElement).value)"
                        >
                            <option v-for="s in APPOINTMENT_STATUS_ORDER" :key="s" :value="s">
                                {{ APPOINTMENT_STATUS_META[s as AppointmentStatus].label }}
                            </option>
                        </select>
                        <div v-if="!['cancelled', 'no_show', 'completed'].includes(appointment.status)" class="mt-2 grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                class="rounded-md border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-100"
                                @click="cancelAppointment"
                            >
                                Prekliči
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-neutral-200 bg-neutral-50 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-100"
                                @click="markNoShow"
                            >
                                Ni se zglasil/a
                            </button>
                        </div>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h3 class="text-xs font-semibold text-neutral-500 uppercase">Termin</h3>
                        <div class="mt-2 space-y-2">
                            <input
                                v-model="scheduleForm.appointment_date"
                                type="date"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                            />
                            <input
                                v-model="scheduleForm.start_time"
                                type="time"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                            />
                            <select
                                v-model.number="scheduleForm.duration_minutes"
                                class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                            >
                                <option v-for="d in [15, 30, 45, 60, 75, 90, 120, 150, 180, 240]" :key="d" :value="d">{{ d }} min</option>
                            </select>
                            <button
                                type="button"
                                class="w-full rounded-md bg-neutral-100 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-200"
                                @click="saveSchedule"
                            >
                                Shrani termin
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
                            <p class="text-xs text-neutral-500">Preostanek: {{ formatMoney(remainingBalance) }}</p>
                            <button
                                type="button"
                                class="w-full rounded-md bg-neutral-100 px-3 py-1.5 text-xs font-medium text-neutral-700 hover:bg-neutral-200"
                                @click="savePayment"
                            >
                                Shrani plačilo
                            </button>

                            <select
                                :value="appointment.payment_status"
                                class="mt-2 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none"
                                @change="updatePayment(($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="(meta, key) in PAYMENT_STATUS_META" :key="key" :value="key">{{ meta.label }}</option>
                            </select>
                        </div>
                    </section>

                    <section v-if="appointment.channel" class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h3 class="text-xs font-semibold text-neutral-500 uppercase">Vir</h3>
                        <div class="mt-2 flex items-center gap-2">
                            <ChannelIcon :type="appointment.channel.type" size="md" />
                            <span class="text-sm text-neutral-700">{{ appointment.channel.display_name }}</span>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <FollowUpModal
            :show="followUpOpen"
            followable-type="App\Models\Appointment"
            :followable-id="appointment.id"
            :default-note="`Opomnik za termin: ${appointment.service_name}`"
            @close="followUpOpen = false"
        />
    </AppLayout>
</template>
