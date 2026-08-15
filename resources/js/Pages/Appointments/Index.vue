<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppointmentCard from '@/Components/AppointmentCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { Search, Plus, CalendarDays, List } from 'lucide-vue-next';
import type { Appointment } from '@/types/models';

const props = defineProps<{
    appointments: { data: Appointment[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { search?: string; filter?: string };
}>();

const search = ref(props.filters.search ?? '');
const filter = ref(props.filters.filter ?? '');

function applyFilters() {
    router.get(
        route('appointments.index', { view: 'list' }),
        {
            search: search.value || undefined,
            filter: filter.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

let debounce: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(applyFilters, 300);
});
watch(filter, applyFilters);

const filterOptions = [
    { value: '', label: 'Vsi termini' },
    { value: 'today', label: 'Danes' },
    { value: 'upcoming', label: 'Prihajajoči' },
    { value: 'completed', label: 'Zaključeni' },
    { value: 'cancelled', label: 'Preklicani' },
    { value: 'no_show', label: 'Ni se zglasil/a' },
];
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
                        :href="route('appointments.index', { view: 'calendar' })"
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <CalendarDays :size="14" /> Koledar
                    </Link>
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

                <select v-model="filter" class="rounded-md border border-neutral-200 py-2 px-3 text-sm text-neutral-600 outline-none">
                    <option v-for="opt in filterOptions" :key="opt.value" :value="opt.value">{{ opt.label }}</option>
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
