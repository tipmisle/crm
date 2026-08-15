<script setup lang="ts">
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { ChevronLeft, ChevronRight, List, Plus, CalendarDays } from 'lucide-vue-next';
import { addDays, addWeeks, subWeeks, format, parseISO, isToday } from 'date-fns';
import { sl } from 'date-fns/locale';
import { formatMoney } from '@/lib/format';
import { APPOINTMENT_STATUS_META } from '@/lib/statuses';
import type { Appointment } from '@/types/models';

const props = defineProps<{
    appointmentsByDate: Record<string, Appointment[]>;
    weekStart: string;
    filters: { search?: string; filter?: string };
}>();

const weekStartDate = computed(() => parseISO(props.weekStart));
const days = computed(() => Array.from({ length: 7 }, (_, i) => addDays(weekStartDate.value, i)));

function appointmentsFor(day: Date): Appointment[] {
    return (props.appointmentsByDate[format(day, 'yyyy-MM-dd')] ?? []).slice().sort((a, b) => a.start_time.localeCompare(b.start_time));
}

function goToWeek(date: Date) {
    router.get(route('appointments.index', { view: 'calendar', week: format(date, 'yyyy-MM-dd') }), {}, { preserveState: true });
}

function goToday() {
    goToWeek(new Date());
}

const totalAppointments = computed(() =>
    Object.values(props.appointmentsByDate).reduce((sum, list) => sum + list.length, 0),
);
</script>

<template>
    <Head title="Termini" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Termini</h1>
        </template>

        <div class="mx-auto flex h-[calc(100vh-3.5rem)] w-full max-w-7xl flex-col px-4 py-4 sm:px-6 sm:py-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-2xl font-semibold text-neutral-900">Termini</h1>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('appointments.index', { view: 'list' })"
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <List :size="14" /> Seznam
                    </Link>
                    <Link
                        :href="route('appointments.create')"
                        class="flex items-center gap-1.5 rounded-md bg-[var(--color-ink-900)] px-3 py-1.5 text-sm font-medium text-white hover:bg-[var(--color-ink-800)]"
                    >
                        <Plus :size="14" /> Nov termin
                    </Link>
                </div>
            </div>

            <div class="mb-4 flex items-center gap-2">
                <button
                    type="button"
                    class="rounded-md border border-neutral-200 p-1.5 text-neutral-500 hover:bg-neutral-50"
                    @click="goToWeek(subWeeks(weekStartDate, 1))"
                >
                    <ChevronLeft :size="16" />
                </button>
                <h2 class="w-56 text-center text-sm font-semibold text-neutral-900 capitalize">
                    {{ format(weekStartDate, 'd. MMM', { locale: sl }) }} – {{ format(addDays(weekStartDate, 6), 'd. MMM yyyy', { locale: sl }) }}
                </h2>
                <button
                    type="button"
                    class="rounded-md border border-neutral-200 p-1.5 text-neutral-500 hover:bg-neutral-50"
                    @click="goToWeek(addWeeks(weekStartDate, 1))"
                >
                    <ChevronRight :size="16" />
                </button>
                <button
                    type="button"
                    class="ml-1 rounded-md border border-neutral-200 px-3 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-50"
                    @click="goToday"
                >
                    Ta teden
                </button>
            </div>

            <div v-if="totalAppointments === 0" class="flex-1">
                <EmptyState title="Ni terminov v tem tednu" description="Rezerviraj termin iz pogovora ali ročno.">
                    <template #icon><CalendarDays :size="28" /></template>
                </EmptyState>
            </div>

            <div v-else class="grid flex-1 grid-cols-7 gap-3 overflow-y-auto pb-2">
                <div v-for="day in days" :key="day.toISOString()" class="flex min-h-0 flex-col rounded-lg border border-neutral-200 bg-white">
                    <div class="flex items-center justify-between border-b border-neutral-100 px-3 py-2">
                        <span class="text-xs font-medium text-neutral-500 capitalize">{{ format(day, 'EEE', { locale: sl }) }}</span>
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-full text-xs font-semibold"
                            :class="isToday(day) ? 'bg-[var(--color-ink-900)] text-white' : 'text-neutral-700'"
                        >
                            {{ format(day, 'd') }}
                        </span>
                    </div>

                    <div class="flex-1 space-y-1.5 overflow-y-auto p-2">
                        <Link
                            v-for="appointment in appointmentsFor(day)"
                            :key="appointment.id"
                            :href="route('appointments.show', appointment.id)"
                            class="block rounded-md px-2 py-1.5 text-xs hover:opacity-80"
                            :style="{ color: APPOINTMENT_STATUS_META[appointment.status].color, backgroundColor: APPOINTMENT_STATUS_META[appointment.status].bg }"
                        >
                            <p class="font-semibold">{{ appointment.start_time.slice(0, 5) }}</p>
                            <p class="truncate">{{ appointment.service_name }}</p>
                            <p class="truncate opacity-80">{{ appointment.customer?.full_name }}</p>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
