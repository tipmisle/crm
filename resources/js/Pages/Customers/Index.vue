<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import Badge from '@/Components/Badge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import CustomerModal from '@/Components/CustomerModal.vue';
import { Search, Plus, Users, CalendarClock } from 'lucide-vue-next';
import { formatMoney, formatDate, formatTime } from '@/lib/format';
import type { Channel } from '@/types/models';
import type { PageProps } from '@/types';

interface UpcomingAppointment {
    id: number;
    service_name: string;
    appointment_date: string;
    start_time: string;
}

interface CustomerRow {
    id: number;
    full_name: string;
    email: string | null;
    phone: string | null;
    primary_channel: Channel | null;
    last_interaction_at: string | null;
    orders_count?: number;
    lifetime_spend?: number;
    open_orders_count?: number;
    appointments_count?: number;
    appointments_lifetime_spend?: number;
    upcoming_appointment?: UpcomingAppointment | null;
}

const props = defineProps<{
    customers: { data: CustomerRow[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { search?: string };
}>();

const page = usePage<PageProps>();
const ordersEnabled = computed(() => page.props.workspace?.orders_enabled ?? true);
const appointmentsEnabled = computed(() => page.props.workspace?.appointments_enabled ?? false);
const bothEnabled = computed(() => ordersEnabled.value && appointmentsEnabled.value);

const search = ref(props.filters.search ?? '');
const createOpen = ref(false);

let debounce: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('customers.index'), { search: search.value || undefined }, { preserveState: true, replace: true });
    }, 300);
});

function totalValue(customer: CustomerRow): number {
    return (customer.lifetime_spend ?? 0) + (customer.appointments_lifetime_spend ?? 0);
}
</script>

<template>
    <Head title="Stranke" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Stranke</h1>
        </template>

        <div class="mx-auto max-w-5xl space-y-5 px-4 py-6 sm:px-6 sm:py-8">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-neutral-900">Stranke</h1>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-md bg-[var(--color-ink-900)] px-3 py-1.5 text-sm font-medium text-white hover:bg-[var(--color-ink-800)]"
                    @click="createOpen = true"
                >
                    <Plus :size="14" /> Nova stranka
                </button>
            </div>

            <div class="relative">
                <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Iskanje po strankah…"
                    class="w-full max-w-sm rounded-md border border-neutral-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-400"
                />
            </div>

            <div v-if="customers.data.length" class="overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm shadow-neutral-900/[0.04]">
                <table class="w-full text-sm">
                    <thead class="border-b border-neutral-100 bg-neutral-50 text-left text-xs font-medium text-neutral-500">
                        <tr>
                            <th class="px-4 py-2.5">Stranka</th>
                            <th class="px-4 py-2.5">Kontakt</th>
                            <th class="px-4 py-2.5">Zadnji stik</th>

                            <template v-if="!bothEnabled && ordersEnabled">
                                <th class="px-4 py-2.5">Naročila</th>
                                <th class="px-4 py-2.5">Skupna vrednost</th>
                                <th class="px-4 py-2.5">Odprta naročila</th>
                            </template>

                            <template v-else-if="!bothEnabled && appointmentsEnabled">
                                <th class="px-4 py-2.5">Termini</th>
                                <th class="px-4 py-2.5">Naslednji termin</th>
                                <th class="px-4 py-2.5">Skupna vrednost</th>
                            </template>

                            <template v-else-if="bothEnabled">
                                <th class="px-4 py-2.5">Naročila</th>
                                <th class="px-4 py-2.5">Termini</th>
                                <th class="px-4 py-2.5">Skupna vrednost</th>
                            </template>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="customer in customers.data"
                            :key="customer.id"
                            class="cursor-pointer border-b border-neutral-50 last:border-0 hover:bg-neutral-50"
                            @click="router.visit(route('customers.show', customer.id))"
                        >
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2.5">
                                    <Avatar :name="customer.full_name" size="sm" />
                                    <span class="font-medium text-neutral-900">{{ customer.full_name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-neutral-600">{{ customer.email ?? customer.phone ?? '—' }}</td>
                            <td class="px-4 py-3 text-neutral-500">{{ formatDate(customer.last_interaction_at) }}</td>

                            <template v-if="!bothEnabled && ordersEnabled">
                                <td class="px-4 py-3 text-neutral-600">{{ customer.orders_count }}</td>
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ formatMoney(customer.lifetime_spend ?? 0) }}</td>
                                <td class="px-4 py-3">
                                    <Badge v-if="customer.open_orders_count" color="#0E7490" bg="#E0F7FA">
                                        {{ customer.open_orders_count }} odprtih
                                    </Badge>
                                    <span v-else class="text-neutral-400">—</span>
                                </td>
                            </template>

                            <template v-else-if="!bothEnabled && appointmentsEnabled">
                                <td class="px-4 py-3 text-neutral-600">{{ customer.appointments_count }}</td>
                                <td class="px-4 py-3">
                                    <Link
                                        v-if="customer.upcoming_appointment"
                                        :href="route('appointments.show', customer.upcoming_appointment.id)"
                                        class="flex items-center gap-1.5 text-[var(--color-accent-600)] hover:underline"
                                        @click.stop
                                    >
                                        <CalendarClock :size="13" />
                                        {{ formatDate(customer.upcoming_appointment.appointment_date) }}
                                        {{ formatTime(`2000-01-01T${customer.upcoming_appointment.start_time}`) }}
                                    </Link>
                                    <span v-else class="text-neutral-400">—</span>
                                </td>
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ formatMoney(customer.appointments_lifetime_spend ?? 0) }}</td>
                            </template>

                            <template v-else-if="bothEnabled">
                                <td class="px-4 py-3 text-neutral-600">
                                    <Badge v-if="customer.open_orders_count" color="#0E7490" bg="#E0F7FA">
                                        {{ customer.open_orders_count }}/{{ customer.orders_count }}
                                    </Badge>
                                    <span v-else>{{ customer.orders_count }}</span>
                                </td>
                                <td class="px-4 py-3 text-neutral-600">
                                    <span v-if="customer.upcoming_appointment" class="text-[var(--color-accent-600)]">
                                        {{ customer.appointments_count }} · {{ formatDate(customer.upcoming_appointment.appointment_date) }}
                                    </span>
                                    <span v-else>{{ customer.appointments_count }}</span>
                                </td>
                                <td class="px-4 py-3 font-medium text-neutral-900">{{ formatMoney(totalValue(customer)) }}</td>
                            </template>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState v-else title="Ni najdenih strank">
                <template #icon><Users :size="28" /></template>
            </EmptyState>

            <Pagination :links="customers.links" />
        </div>

        <CustomerModal v-model:open="createOpen" />
    </AppLayout>
</template>
