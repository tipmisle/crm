<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppointmentCard from '@/Components/AppointmentCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { Search, Plus, CalendarDays, List, Download } from 'lucide-vue-next';
import { format } from 'date-fns';
import type { Appointment } from '@/types/models';
import type { PageProps } from '@/types';

const props = defineProps<{
    appointments: { data: Appointment[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { search?: string; status?: string; payment?: string; due?: string };
}>();

const page = usePage<PageProps>();
const paymentStatuses = computed(() => page.props.paymentStatuses ?? []);
const appointmentStatuses = computed(() => page.props.appointmentStatuses ?? []);
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const payment = ref(props.filters.payment ?? '');
const due = ref(props.filters.due ?? '');
const calendarHref = computed(() =>
    route('appointments.index', { ...props.filters, view: 'calendar', week: format(new Date(), 'yyyy-MM-dd') }),
);
const exportHref = computed(() =>
    route('appointments.export', {
        search: search.value || undefined,
        status: status.value || undefined,
        payment: payment.value || undefined,
        due: due.value || undefined,
    }),
);

function applyFilters() {
    router.get(
        route('appointments.index', { view: 'list' }),
        {
            search: search.value || undefined,
            status: status.value || undefined,
            payment: payment.value || undefined,
            due: due.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

let debounce: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 300);
});
watch([status, payment, due], applyFilters);
</script>

<template>
    <Head title="Termini" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Termini</h1>
        </template>

        <div class="mx-auto max-w-5xl space-y-5 px-4 py-4 sm:px-6 sm:py-6">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-2xl font-semibold text-neutral-900">Termini</h1>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="calendarHref"
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <CalendarDays :size="14" /> Koledar
                    </Link>
                    <a
                        :href="exportHref"
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <Download :size="14" /> Izvozi CSV
                    </a>
                    <Link
                        :href="route('appointments.create')"
                        class="flex items-center gap-1.5 rounded-md bg-[var(--color-ink-900)] px-3 py-1.5 text-sm font-medium text-white hover:bg-[var(--color-ink-800)]"
                    >
                        <Plus :size="14" /> Nov termin
                    </Link>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-48">
                    <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Iskanje po terminih, strankah, storitvah…"
                        class="w-full rounded-md border border-neutral-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-400"
                    />
                </div>

                <select v-model="status" class="rounded-md border border-neutral-200 py-2 px-3 text-sm text-neutral-600 outline-none">
                    <option value="">Vsi statusi</option>
                    <option v-for="s in appointmentStatuses" :key="s.key" :value="s.key">{{ s.label }}</option>
                </select>

                <select v-model="payment" class="rounded-md border border-neutral-200 py-2 px-3 text-sm text-neutral-600 outline-none">
                    <option value="">Vsa plačila</option>
                    <option v-for="s in paymentStatuses" :key="s.key" :value="s.key">{{ s.label }}</option>
                </select>

                <select v-model="due" class="rounded-md border border-neutral-200 py-2 px-3 text-sm text-neutral-600 outline-none">
                    <option value="">Katerikoli termin</option>
                    <option value="today">Termin danes</option>
                    <option value="week">Naslednjih 7 dni</option>
                    <option value="overdue">Zamujeno</option>
                </select>
            </div>

            <div v-if="appointments.data.length" class="space-y-2">
                <AppointmentCard v-for="appointment in appointments.data" :key="appointment.id" :appointment="appointment" />
            </div>
            <EmptyState v-else title="Ni najdenih terminov" description="Poskusi prilagoditi filtre ali rezerviraj nov termin.">
                <template #icon><List :size="28" /></template>
            </EmptyState>

            <Pagination v-if="appointments.data.length" :links="appointments.links" />
        </div>
    </AppLayout>
</template>
