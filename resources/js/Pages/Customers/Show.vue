<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import Badge from '@/Components/Badge.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import OrderCard from '@/Components/OrderCard.vue';
import AppointmentCard from '@/Components/AppointmentCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import CustomerContactCard from '@/Components/CustomerContactCard.vue';
import Modal from '@/Components/Modal.vue';
import { formatMoney, formatDate, formatDateTime, relativeTime } from '@/lib/format';
import { CONVERSATION_STATUS_META } from '@/lib/statuses';
import { Plus, Check, CalendarPlus } from 'lucide-vue-next';
import type { ActivityLogEntry, Appointment, Conversation, CustomerIdentity, FollowUp, Order } from '@/types/models';

interface CustomerDetail {
    id: number;
    full_name: string;
    email: string | null;
    phone: string | null;
    address_line: string | null;
    postal_code: string | null;
    city: string | null;
    country: string | null;
    tax_number: string | null;
    notes: string | null;
    tags: string[] | null;
    primary_channel_id: number | null;
    identities: CustomerIdentity[];
    first_contacted_at: string | null;
    last_interaction_at: string | null;
    orders: Order[];
    appointments: Appointment[];
    conversations: Conversation[];
    lifetime_spend: number;
    open_orders_count: number;
    completed_orders_count: number;
    appointments_lifetime_spend: number;
    previous_appointments_count: number;
    no_show_appointments_count: number;
}

const props = defineProps<{
    customer: CustomerDetail;
    followUps: FollowUp[];
    activity: ActivityLogEntry[];
}>();

const page = usePage<PageProps>();
const ordersEnabled = computed(() => page.props.workspace?.orders_enabled ?? true);
const appointmentsEnabled = computed(() => page.props.workspace?.appointments_enabled ?? false);

const lastInteractionLabel = computed(() => {
    const value = props.customer.last_interaction_at;
    if (!value) return '';

    // last_interaction_at is always a past event by definition — clamp any
    // future timestamp (stale/misseeded data, clock skew) to "now" so the
    // relative label never reads in future tense ("čez X dni").
    const date = new Date(value);
    const now = new Date();

    return relativeTime(date > now ? now.toISOString() : value);
});

// Order statuses are workspace-customizable — "open" is determined by the
// is_completed/is_cancelled flags on the shared orderStatuses prop, never
// by a literal status key. See OrderStatus model docs.
const orderStatuses = computed(() => page.props.orderStatuses ?? []);
const closedOrderKeys = computed(
    () => new Set(orderStatuses.value.filter((s) => s.is_completed || s.is_cancelled).map((s) => s.key)),
);
const openOrders = computed(() => props.customer.orders.filter((o) => !closedOrderKeys.value.has(o.status)));
// "Zgodovina naročil" must not repeat what's already shown as current —
// only closed orders appear here.
const closedOrders = computed(() => props.customer.orders.filter((o) => closedOrderKeys.value.has(o.status)));

const upcomingAppointments = computed(() =>
    props.customer.appointments.filter((a) => ['requested', 'confirmed'].includes(a.status)),
);
// "Zgodovina terminov" must not repeat what's already shown as upcoming.
const pastAppointments = computed(() =>
    props.customer.appointments.filter((a) => !['requested', 'confirmed'].includes(a.status)),
);
const confirmingErasure = ref(false);
const eraseForm = useForm({ confirm: false });

function exportCustomerData() {
    window.location.href = route('customers.privacy.export', props.customer.id);
}

function eraseCustomerData() {
    eraseForm.confirm = true;
    eraseForm.post(route('customers.privacy.erase', props.customer.id), {
        preserveScroll: true,
        onSuccess: () => (confirmingErasure.value = false),
    });
}

function completeFollowUp(id: number) {
    router.patch(route('follow-ups.complete', id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head :title="customer.full_name" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('customers.index')" class="text-sm text-neutral-400 hover:text-neutral-600">Stranke</Link>
                <span class="text-neutral-300">/</span>
                <span class="text-sm font-semibold text-neutral-900">{{ customer.full_name }}</span>
            </div>
        </template>

        <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 sm:py-8">
            <div class="mb-6 flex items-start justify-between">
                <div class="flex items-center gap-4">
                    <Avatar :name="customer.full_name" size="lg" />
                    <div>
                        <h1 class="text-2xl font-semibold text-neutral-900">{{ customer.full_name }}</h1>
                        <p class="text-sm text-neutral-500">
                            Stranka od {{ formatDate(customer.first_contacted_at) }} · Zadnji stik {{ lastInteractionLabel }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Link
                        v-if="ordersEnabled"
                        :href="route('orders.create', { customer_id: customer.id })"
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <Plus :size="14" /> Novo naročilo
                    </Link>
                    <Link
                        v-if="appointmentsEnabled"
                        :href="route('appointments.create', { customer_id: customer.id })"
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <CalendarPlus :size="14" /> Nov termin
                    </Link>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <section v-if="ordersEnabled && openOrders.length" class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Trenutna naročila</h3>
                        <div class="mt-3 space-y-2">
                            <OrderCard v-for="order in openOrders" :key="order.id" :order="order" />
                        </div>
                    </section>

                    <section id="order-history" v-if="ordersEnabled && (closedOrders.length || !customer.orders.length)" class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Zgodovina naročil</h3>
                        <div v-if="closedOrders.length" class="mt-3 space-y-2">
                            <OrderCard v-for="order in closedOrders" :key="order.id" :order="order" />
                        </div>
                        <EmptyState v-else title="Še ni naročil" description="Ustvari prvo naročilo za to stranko.">
                            <template #action>
                                <Link
                                    :href="route('orders.create', { customer_id: customer.id })"
                                    class="mt-2 flex items-center gap-1.5 rounded-md bg-[var(--color-ink-900)] px-3 py-1.5 text-sm font-medium text-white hover:bg-[var(--color-ink-800)]"
                                >
                                    <Plus :size="14" /> Novo naročilo
                                </Link>
                            </template>
                        </EmptyState>
                    </section>

                    <section v-if="appointmentsEnabled && upcomingAppointments.length" class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-neutral-800 uppercase">Prihajajoči termini</h3>
                            <Link
                                :href="route('appointments.create', { customer_id: customer.id })"
                                class="flex items-center gap-1 text-xs font-medium text-[var(--color-accent-600)] hover:underline"
                            >
                                <Plus :size="12" /> Nov termin
                            </Link>
                        </div>
                        <div class="mt-3 space-y-2">
                            <AppointmentCard v-for="appointment in upcomingAppointments" :key="appointment.id" :appointment="appointment" />
                        </div>
                    </section>

                    <section id="appointment-history" v-if="appointmentsEnabled && (pastAppointments.length || !customer.appointments.length)" class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Zgodovina terminov</h3>
                        <div v-if="pastAppointments.length" class="mt-3 space-y-2">
                            <AppointmentCard v-for="appointment in pastAppointments" :key="appointment.id" :appointment="appointment" />
                        </div>
                        <EmptyState v-else title="Še ni terminov" description="Rezerviraj prvi termin za to stranko.">
                            <template #action>
                                <Link
                                    :href="route('appointments.create', { customer_id: customer.id })"
                                    class="mt-2 flex items-center gap-1.5 rounded-md bg-[var(--color-ink-900)] px-3 py-1.5 text-sm font-medium text-white hover:bg-[var(--color-ink-800)]"
                                >
                                    <Plus :size="14" /> Nov termin
                                </Link>
                            </template>
                        </EmptyState>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Zgodovina pogovorov</h3>
                        <div v-if="customer.conversations.length" class="mt-3 space-y-2">
                            <Link
                                v-for="c in customer.conversations"
                                :key="c.id"
                                :href="route('inbox.show', c.id)"
                                class="flex items-center gap-3 rounded-lg border border-neutral-100 px-3 py-2.5 hover:border-neutral-200 hover:bg-neutral-50"
                            >
                                <ChannelIcon v-if="c.channel" :type="c.channel.type" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-neutral-800">{{ c.last_message_preview }}</p>
                                    <p class="text-xs text-neutral-400">{{ relativeTime(c.last_message_at) }}</p>
                                </div>
                                <Badge :color="CONVERSATION_STATUS_META[c.status].color" :bg="CONVERSATION_STATUS_META[c.status].bg">
                                    {{ CONVERSATION_STATUS_META[c.status].label }}
                                </Badge>
                            </Link>
                        </div>
                        <EmptyState v-else title="Še ni pogovorov" />
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Časovnica aktivnosti</h3>
                        <div v-if="activity.length" class="mt-3 space-y-3">
                            <div v-for="entry in activity" :key="entry.id" class="flex gap-2.5 text-sm">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-neutral-300" />
                                <div>
                                    <p class="text-neutral-700">{{ entry.description }}</p>
                                    <p class="text-xs text-neutral-400">{{ formatDateTime(entry.created_at) }}</p>
                                </div>
                            </div>
                        </div>
                        <EmptyState v-else title="Še ni aktivnosti" />
                    </section>
                </div>

                <div class="space-y-5">
                    <CustomerContactCard :customer="customer" title="Kontakt" :link-to-customer="false" show-name show-notes />

                    <section class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Družbeni profili</h3>
                        <div class="mt-2 space-y-2">
                            <div v-for="identity in customer.identities" :key="identity.id" class="flex items-center gap-2">
                                <ChannelIcon :type="identity.channel_type" />
                                <span class="text-sm text-neutral-700">{{ identity.username ?? identity.display_name }}</span>
                            </div>
                            <p v-if="!customer.identities.length" class="text-sm text-neutral-400">Ni povezanih profilov.</p>
                        </div>
                    </section>

                    <section v-if="ordersEnabled" class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Skupna vrednost naročil</h3>
                        <p class="mt-2 text-2xl font-semibold text-neutral-900">{{ formatMoney(customer.lifetime_spend) }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-center">
                            <div class="rounded-md bg-neutral-50 py-2">
                                <p class="text-lg font-semibold text-neutral-900">{{ customer.open_orders_count }}</p>
                                <p class="text-xs text-neutral-500">Odprta</p>
                            </div>
                            <div class="rounded-md bg-neutral-50 py-2">
                                <p class="text-lg font-semibold text-neutral-900">{{ customer.completed_orders_count }}</p>
                                <p class="text-xs text-neutral-500">Zaključena</p>
                            </div>
                        </div>
                    </section>

                    <section v-if="appointmentsEnabled" class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Skupna vrednost terminov</h3>
                        <p class="mt-2 text-2xl font-semibold text-neutral-900">{{ formatMoney(customer.appointments_lifetime_spend) }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-center">
                            <div class="rounded-md bg-neutral-50 py-2">
                                <p class="text-lg font-semibold text-neutral-900">{{ customer.previous_appointments_count }}</p>
                                <p class="text-xs text-neutral-500">Opravljeni</p>
                            </div>
                            <div class="rounded-md bg-neutral-50 py-2">
                                <p class="text-lg font-semibold text-neutral-900">{{ customer.no_show_appointments_count }}</p>
                                <p class="text-xs text-neutral-500">Ni se zglasil/a</p>
                            </div>
                        </div>
                    </section>

                    <section v-if="followUps.length" class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-5">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Opomniki</h3>
                        <div class="mt-2 space-y-2">
                            <div v-for="f in followUps" :key="f.id" class="flex items-start gap-2 text-sm">
                                <button
                                    v-if="!f.completed_at"
                                    type="button"
                                    class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-neutral-300 text-neutral-400 hover:border-emerald-500 hover:text-emerald-600"
                                    title="Označi kot zaključeno"
                                    @click="completeFollowUp(f.id)"
                                >
                                    <Check :size="10" />
                                </button>
                                <div class="min-w-0 flex-1">
                                    <p class="text-neutral-700" :class="f.completed_at && 'line-through text-neutral-400'">{{ f.note }}</p>
                                    <p class="text-xs text-neutral-400">
                                        {{ f.completed_at ? 'Zaključeno' : 'Rok' }} {{ formatDateTime(f.completed_at ?? f.due_at) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="rounded-xl border border-dashed border-neutral-200 bg-white p-4">
                        <h3 class="text-xs font-semibold text-neutral-800 uppercase">Podatki stranke</h3>
                        <div class="mt-2 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-md border border-neutral-200 px-2.5 py-1 text-xs font-medium text-neutral-500 hover:bg-neutral-50"
                                @click="exportCustomerData"
                            >
                                Izvozi podatke stranke
                            </button>
                            <button
                                type="button"
                                class="rounded-md border border-neutral-200 px-2.5 py-1 text-xs font-medium text-neutral-500 hover:bg-red-50 hover:text-red-600"
                                @click="confirmingErasure = true"
                            >
                                Izbriši / anonimiziraj podatke stranke
                            </button>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <Modal :show="confirmingErasure" max-width="sm" @close="confirmingErasure = false">
            <div class="p-6">
                <h2 class="text-base font-semibold text-neutral-900">Izbriši podatke stranke?</h2>
                <p class="mt-1 text-sm text-neutral-500">
                    Ime, kontaktni podatki, opombe, sporočila in profili te stranke bodo trajno izbrisani ali
                    anonimizirani. Naročila in termini ostanejo shranjeni brez osebnih podatkov stranke. Tega dejanja
                    ni mogoče razveljaviti.
                </p>
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100" @click="confirmingErasure = false">
                        Prekliči
                    </button>
                    <button
                        type="button"
                        :disabled="eraseForm.processing"
                        class="rounded-md bg-red-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-700 disabled:opacity-50"
                        @click="eraseCustomerData"
                    >
                        Izbriši podatke
                    </button>
                </div>
            </div>
        </Modal>
    </AppLayout>
</template>
