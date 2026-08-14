<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { ChevronLeft, ChevronRight, List, LayoutGrid, Plus, CalendarDays } from 'lucide-vue-next';
import {
    startOfMonth,
    endOfMonth,
    startOfWeek,
    endOfWeek,
    eachDayOfInterval,
    addMonths,
    subMonths,
    format,
    parseISO,
    isSameMonth,
    isToday,
} from 'date-fns';
import { sl } from 'date-fns/locale';
import { formatMoney } from '@/lib/format';
import { ORDER_STATUS_META } from '@/lib/statuses';
import type { Order } from '@/types/models';

const props = defineProps<{
    ordersByDate: Record<string, Order[]>;
    month: string;
    filters: { search?: string; status?: string; payment?: string };
}>();

const monthStart = computed(() => startOfMonth(parseISO(`${props.month}-01`)));
const gridStart = computed(() => startOfWeek(monthStart.value, { weekStartsOn: 1 }));
const gridEnd = computed(() => endOfWeek(endOfMonth(monthStart.value), { weekStartsOn: 1 }));

const days = computed(() => eachDayOfInterval({ start: gridStart.value, end: gridEnd.value }));
const weekCount = computed(() => days.value.length / 7);
const weekdayLabels = ['Pon', 'Tor', 'Sre', 'Čet', 'Pet', 'Sob', 'Ned'];

function ordersFor(day: Date): Order[] {
    return props.ordersByDate[format(day, 'yyyy-MM-dd')] ?? [];
}

function goToMonth(date: Date) {
    router.get(route('orders.index', { view: 'calendar', month: format(date, 'yyyy-MM') }), {}, { preserveState: true });
}

function goToday() {
    goToMonth(new Date());
}
</script>

<template>
    <Head title="Naročila" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Naročila</h1>
        </template>

        <div class="flex h-[calc(100vh-3.5rem)] flex-col px-6 py-6">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-neutral-900">Naročila</h1>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('orders.index')"
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <List :size="14" /> Seznam
                    </Link>
                    <Link
                        :href="route('orders.index', { view: 'kanban' })"
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <LayoutGrid :size="14" /> Tabla
                    </Link>
                    <Link
                        :href="route('orders.create')"
                        class="flex items-center gap-1.5 rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-800"
                    >
                        <Plus :size="14" /> Novo naročilo
                    </Link>
                </div>
            </div>

            <div class="mb-4 flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-md border border-neutral-200 p-1.5 text-neutral-500 hover:bg-neutral-50"
                        @click="goToMonth(subMonths(monthStart, 1))"
                    >
                        <ChevronLeft :size="16" />
                    </button>
                    <h2 class="w-40 text-center text-sm font-semibold text-neutral-900 capitalize">
                        {{ format(monthStart, 'LLLL yyyy', { locale: sl }) }}
                    </h2>
                    <button
                        type="button"
                        class="rounded-md border border-neutral-200 p-1.5 text-neutral-500 hover:bg-neutral-50"
                        @click="goToMonth(addMonths(monthStart, 1))"
                    >
                        <ChevronRight :size="16" />
                    </button>
                    <button
                        type="button"
                        class="ml-1 rounded-md border border-neutral-200 px-3 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                        @click="goToday"
                    >
                        Danes
                    </button>
                </div>
            </div>

            <div v-if="Object.keys(ordersByDate).length === 0" class="flex-1">
                <EmptyState title="Ni naročil s rokom v tem mesecu" description="Naročila brez določenega roka se v koledarju ne prikažejo.">
                    <template #icon><CalendarDays :size="28" /></template>
                </EmptyState>
            </div>

            <div
                v-else
                class="grid flex-1 grid-cols-7 gap-px overflow-hidden rounded-lg border border-neutral-200 bg-neutral-200"
                :style="{ gridTemplateRows: `auto repeat(${weekCount}, 1fr)` }"
            >
                <div
                    v-for="label in weekdayLabels"
                    :key="label"
                    class="bg-neutral-50 px-2 py-1.5 text-center text-xs font-medium text-neutral-500"
                >
                    {{ label }}
                </div>

                <div
                    v-for="day in days"
                    :key="day.toISOString()"
                    class="flex min-h-0 flex-col gap-1 overflow-hidden bg-white p-1.5"
                    :class="!isSameMonth(day, monthStart) && 'bg-neutral-50/60'"
                >
                    <span
                        class="text-xs font-medium"
                        :class="[
                            isSameMonth(day, monthStart) ? 'text-neutral-700' : 'text-neutral-350 opacity-50',
                            isToday(day) && 'flex h-5 w-5 items-center justify-center rounded-full bg-neutral-900 text-white',
                        ]"
                    >
                        {{ format(day, 'd') }}
                    </span>

                    <div class="flex-1 space-y-1 overflow-y-auto">
                        <Link
                            v-for="order in ordersFor(day).slice(0, 3)"
                            :key="order.id"
                            :href="route('orders.show', order.id)"
                            class="block truncate rounded px-1.5 py-0.5 text-[11px] font-medium hover:opacity-80"
                            :style="{ color: ORDER_STATUS_META[order.status].color, backgroundColor: ORDER_STATUS_META[order.status].bg }"
                            :title="`${order.title} · ${order.customer?.full_name ?? ''} · ${formatMoney(order.price)}`"
                        >
                            {{ order.due_time?.slice(0, 5) }} {{ order.title }}
                        </Link>
                        <span v-if="ordersFor(day).length > 3" class="block px-1.5 text-[11px] text-neutral-400">
                            +{{ ordersFor(day).length - 3 }} več
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
