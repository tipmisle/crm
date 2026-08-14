<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import OrderCard from '@/Components/OrderCard.vue';
import AppointmentCard from '@/Components/AppointmentCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { CalendarClock, Check, PartyPopper } from 'lucide-vue-next';
import { formatDate, formatDateTime, formatTime, isPastDue } from '@/lib/format';
import type { Appointment, FollowUp, Order } from '@/types/models';

interface AttentionItem {
    key: string;
    label: string;
    count: number;
    href: string;
}

const props = defineProps<{
    attention: AttentionItem[];
    todaysOrders: Order[];
    todaysAppointments: Appointment[];
    followUps: (FollowUp & { is_overdue: boolean; href: string })[];
    upcoming: Order[];
    upcomingAppointments: Appointment[];
}>();

const page = usePage<PageProps>();
const ordersEnabled = computed(() => page.props.workspace?.orders_enabled ?? true);
const appointmentsEnabled = computed(() => page.props.workspace?.appointments_enabled ?? false);

interface UpcomingItem {
    key: string;
    href: string;
    title: string;
    subtitle: string;
    date: string;
    isPast: boolean;
}

const upcomingCombined = computed<UpcomingItem[]>(() => {
    const orders: UpcomingItem[] = props.upcoming.map((order) => ({
        key: `order-${order.id}`,
        href: route('orders.show', order.id),
        title: order.title,
        subtitle: order.customer?.full_name ?? '',
        date: order.due_date ?? '',
        isPast: isPastDue(order.due_date),
    }));

    const appointments: UpcomingItem[] = props.upcomingAppointments.map((appointment) => ({
        key: `appointment-${appointment.id}`,
        href: route('appointments.show', appointment.id),
        title: `${appointment.service_name} · ${formatTime(`2000-01-01T${appointment.start_time}`)}`,
        subtitle: appointment.customer?.full_name ?? '',
        date: appointment.appointment_date,
        isPast: false,
    }));

    return [...orders, ...appointments].sort((a, b) => a.date.localeCompare(b.date));
});

const today = new Date();

function completeFollowUp(id: number) {
    router.patch(route('follow-ups.complete', id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Danes" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Danes</h1>
        </template>

        <div class="mx-auto max-w-5xl space-y-6 px-6 py-8">
            <div>
                <h1 class="text-2xl font-semibold text-neutral-900">
                    {{ today.toLocaleDateString('sl-SI', { weekday: 'long', month: 'long', day: 'numeric' }) }}
                </h1>
                <p class="text-sm text-neutral-500">Tole danes potrebuje tvojo pozornost.</p>
            </div>

            <SectionCard v-if="attention.length" title="Potrebuje pozornost">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Link
                        v-for="item in attention"
                        :key="item.key"
                        :href="item.href"
                        class="flex items-center justify-between rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 transition hover:border-[var(--color-accent-300)] hover:bg-[var(--color-accent-50)]"
                    >
                        <span class="text-sm font-medium text-neutral-800">{{ item.label }}</span>
                        <span
                            class="flex h-6 min-w-6 items-center justify-center rounded-full bg-[var(--color-accent-500)] px-1.5 text-xs font-semibold text-white"
                        >
                            {{ item.count }}
                        </span>
                    </Link>
                </div>
            </SectionCard>

            <SectionCard v-else title="Potrebuje pozornost">
                <EmptyState title="Vse je urejeno" description="Trenutno ni ničesar nujnega, kar bi potrebovalo tvojo pozornost.">
                    <template #icon>
                        <PartyPopper :size="28" />
                    </template>
                </EmptyState>
            </SectionCard>

            <SectionCard v-if="ordersEnabled" title="Današnja naročila" :subtitle="`Danes zapade: ${todaysOrders.length}`">
                <div v-if="todaysOrders.length" class="space-y-2">
                    <OrderCard v-for="order in todaysOrders" :key="order.id" :order="order" />
                </div>
                <EmptyState v-else title="Danes ne zapade nobeno naročilo" description="Uživaj v mirnem dnevu.">
                    <template #icon>
                        <CalendarClock :size="28" />
                    </template>
                </EmptyState>
            </SectionCard>

            <SectionCard v-if="appointmentsEnabled" title="Današnji termini" :subtitle="`Danes: ${todaysAppointments.length}`">
                <div v-if="todaysAppointments.length" class="space-y-2">
                    <AppointmentCard v-for="appointment in todaysAppointments" :key="appointment.id" :appointment="appointment" />
                </div>
                <EmptyState v-else title="Danes ni terminov" description="Uživaj v mirnem dnevu.">
                    <template #icon>
                        <CalendarClock :size="28" />
                    </template>
                </EmptyState>
            </SectionCard>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <SectionCard title="Opomniki">
                    <div v-if="followUps.length" class="space-y-2">
                        <div
                            v-for="followUp in followUps"
                            :key="followUp.id"
                            class="flex items-start gap-2.5 rounded-lg border border-neutral-100 px-3 py-2.5 hover:border-neutral-200 hover:bg-neutral-50"
                        >
                            <button
                                type="button"
                                class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-neutral-300 text-transparent hover:border-neutral-400 hover:text-neutral-400"
                                title="Označi kot opravljeno"
                                @click="completeFollowUp(followUp.id)"
                            >
                                <Check :size="10" />
                            </button>
                            <Link :href="followUp.href" class="min-w-0 flex-1">
                                <p class="text-sm text-neutral-800">{{ followUp.note }}</p>
                                <p class="text-xs" :class="followUp.is_overdue ? 'text-red-500' : 'text-neutral-500'">
                                    {{ followUp.is_overdue ? 'Zamuja' : 'Zapade' }} {{ formatDateTime(followUp.due_at) }}
                                </p>
                            </Link>
                        </div>
                    </div>
                    <EmptyState v-else title="Ni načrtovanih opomnikov" />
                </SectionCard>

                <SectionCard title="Prihajajoče" subtitle="Naslednjih 7 dni">
                    <div v-if="upcomingCombined.length" class="space-y-2">
                        <Link
                            v-for="item in upcomingCombined"
                            :key="item.key"
                            :href="item.href"
                            class="flex items-center justify-between rounded-lg border border-neutral-100 px-3 py-2.5 hover:border-neutral-200 hover:bg-neutral-50"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-neutral-800">{{ item.title }}</p>
                                <p class="truncate text-xs text-neutral-500">{{ item.subtitle }}</p>
                            </div>
                            <span class="shrink-0 text-xs font-medium" :class="item.isPast ? 'text-red-500' : 'text-neutral-500'">
                                {{ formatDate(item.date) }}
                            </span>
                        </Link>
                    </div>
                    <EmptyState v-else title="Nič ni napovedano" description="V naslednjem tednu ni rokov." />
                </SectionCard>
            </div>
        </div>
    </AppLayout>
</template>
