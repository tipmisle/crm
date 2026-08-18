<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import type { PageProps } from '@/types';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import OrderCard from '@/Components/OrderCard.vue';
import AppointmentCard from '@/Components/AppointmentCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import StatCard from '@/Components/Charts/StatCard.vue';
import Avatar from '@/Components/Avatar.vue';
import MiniCalendar from '@/Components/MiniCalendar.vue';
import { CalendarClock, Check, PartyPopper, CalendarPlus, MessageCircle, AlertCircle } from 'lucide-vue-next';
import { googleCalendarUrl } from '@/lib/calendar';
import { formatDate, formatDateTime, formatMoney, formatTime, isPastDue } from '@/lib/format';
import type { Appointment, FollowUp, Order } from '@/types/models';

interface AttentionItem {
    key: string;
    label: string;
    count: number;
    href: string;
}

interface UnansweredConversation {
    id: number;
    display_name: string;
    last_message_preview: string | null;
    last_message_at: string | null;
    unread_count: number;
    href: string;
}

const props = defineProps<{
    attention: AttentionItem[];
    todaysOrders: Order[];
    todaysAppointments: Appointment[];
    followUps: (FollowUp & { is_overdue: boolean; href: string })[];
    unansweredConversations: UnansweredConversation[];
    upcoming: Order[];
    upcomingAppointments: Appointment[];
    stats: {
        revenue: { value: number; delta: number | null };
        inquiries: { value: number; delta: number | null };
        bookings: { value: number; delta: number | null };
        conversionRate: { value: number | null; delta: number | null };
    };
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

const markedDates = computed<string[]>(() => {
    const dates = new Set<string>();
    for (const order of [...props.todaysOrders, ...props.upcoming]) {
        if (order.due_date) dates.add(order.due_date);
    }
    for (const appointment of [...props.todaysAppointments, ...props.upcomingAppointments]) {
        dates.add(appointment.appointment_date);
    }
    for (const followUp of props.followUps) {
        dates.add(followUp.due_at.slice(0, 10));
    }
    return [...dates];
});

// With both modules enabled, offer both destinations explicitly rather than
// silently picking one — clicking through must never hide the other module's
// calendar. A single-module workspace goes straight to its own calendar.
const calendarLinks = computed(() => {
    const links: { label: string; href: string }[] = [];
    if (ordersEnabled.value) links.push({ label: 'Naročila', href: route('orders.index', { view: 'calendar' }) });
    if (appointmentsEnabled.value) links.push({ label: 'Termini', href: route('appointments.index', { view: 'calendar' }) });
    return links;
});

const today = new Date();
const firstName = computed(() => page.props.auth.user.name.split(' ')[0]);
const todayLabel = computed(() =>
    `Danes je ${today.toLocaleDateString('sl-SI', { day: 'numeric', month: 'long', year: 'numeric' })}.`,
);

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

        <div class="mx-auto grid max-w-7xl grid-cols-1 gap-6 px-4 py-6 sm:px-6 sm:py-8 lg:grid-cols-[1fr_280px] lg:items-start">
        <div class="min-w-0 space-y-6">
            <div
                class="rounded-xl bg-gradient-to-br from-[var(--color-ink-900)] to-[var(--color-ink-950)] px-5 py-6 text-white sm:px-8 sm:py-8"
            >
                <div class="flex items-center gap-3">
                    <Avatar :name="page.props.auth.user.name" :src="page.props.auth.user.avatar_url" size="lg" />
                    <div>
                        <h1 class="text-2xl font-semibold sm:text-3xl">
                            Živjo, {{ firstName }}! <span aria-hidden="true">👋</span>
                        </h1>
                        <p class="mt-1.5 text-sm text-white/80 sm:text-base">{{ todayLabel }}</p>
                    </div>
                </div>
            </div>

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <StatCard
                    label="Prihodki danes"
                    :value="formatMoney(stats.revenue.value)"
                    :delta="stats.revenue.delta"
                    compare-label="glede na včeraj"
                />
                <StatCard
                    label="Nova povpraševanja"
                    :value="String(stats.inquiries.value)"
                    :delta="stats.inquiries.delta"
                    compare-label="glede na včeraj"
                />
                <StatCard
                    label="Število naročil"
                    :value="String(stats.bookings.value)"
                    :delta="stats.bookings.delta"
                    compare-label="glede na včeraj"
                />
                <StatCard
                    label="Stopnja konverzije"
                    :value="stats.conversionRate.value !== null ? `${stats.conversionRate.value}%` : '—'"
                    :delta="stats.conversionRate.delta"
                    compare-label="glede na včeraj"
                />
            </div>

            <SectionCard v-if="attention.length" title="Potrebuje pozornost">
                <template #icon>
                    <AlertCircle :size="18" class="shrink-0 text-red-500" />
                </template>
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
                            <a
                                :href="googleCalendarUrl(followUp.note, followUp.due_at)"
                                target="_blank"
                                rel="noopener"
                                title="Dodaj v Google Koledar"
                                class="mt-0.5 shrink-0 text-neutral-300 hover:text-neutral-500"
                            >
                                <CalendarPlus :size="15" />
                            </a>
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

        <aside class="space-y-4 lg:sticky lg:top-6">
            <MiniCalendar :marked-dates="markedDates" :calendar-links="calendarLinks" />

            <div class="rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04] p-4">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-sm font-semibold text-neutral-900">Neodgovorjena sporočila</h2>
                    <Link :href="route('inbox.index')" class="text-xs font-medium text-[var(--color-accent-600)] hover:text-[var(--color-accent-700)]">
                        Vsa
                    </Link>
                </div>

                <div v-if="unansweredConversations.length" class="space-y-1">
                    <Link
                        v-for="conversation in unansweredConversations"
                        :key="conversation.id"
                        :href="conversation.href"
                        class="block rounded-lg px-2 py-2 hover:bg-neutral-50"
                    >
                        <div class="flex items-center gap-2">
                            <p class="min-w-0 flex-1 truncate text-sm font-medium text-neutral-800">{{ conversation.display_name }}</p>
                            <span
                                class="ml-auto flex h-4.5 min-w-4.5 shrink-0 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white"
                            >
                                {{ conversation.unread_count }}
                            </span>
                        </div>
                        <p class="truncate text-xs text-neutral-500">{{ conversation.last_message_preview ?? '—' }}</p>
                    </Link>
                </div>
                <EmptyState v-else title="Vse rešeno" description="Ni neodgovorjenih sporočil.">
                    <template #icon><MessageCircle :size="24" /></template>
                </EmptyState>
            </div>
        </aside>
        </div>
    </AppLayout>
</template>
