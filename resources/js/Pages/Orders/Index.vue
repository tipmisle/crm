<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import OrderCard from '@/Components/OrderCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { Search, Plus, LayoutGrid, List, CalendarDays } from 'lucide-vue-next';
import { ORDER_STATUS_ORDER, ORDER_STATUS_META, PAYMENT_STATUS_META } from '@/lib/statuses';
import type { Order, OrderStatus, PaymentStatus } from '@/types/models';

const props = defineProps<{
    orders: { data: Order[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { search?: string; status?: string; payment?: string; due?: string };
}>();

const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const payment = ref(props.filters.payment ?? '');
const due = ref(props.filters.due ?? '');

function applyFilters() {
    router.get(
        route('orders.index'),
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
    <Head title="Naročila" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Naročila</h1>
        </template>

        <div class="mx-auto max-w-5xl space-y-5 px-6 py-8">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-neutral-900">Naročila</h1>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('orders.index', { view: 'kanban' })"
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <LayoutGrid :size="14" /> Tabla
                    </Link>
                    <Link
                        :href="route('orders.index', { view: 'calendar' })"
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <CalendarDays :size="14" /> Koledar
                    </Link>
                    <Link
                        :href="route('orders.create')"
                        class="flex items-center gap-1.5 rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-800"
                    >
                        <Plus :size="14" /> Novo naročilo
                    </Link>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative flex-1 min-w-48">
                    <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
                    <input
                        v-model="search"
                        type="text"
                        placeholder="Iskanje po naročilih, strankah…"
                        class="w-full rounded-md border border-neutral-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-400"
                    />
                </div>

                <select v-model="status" class="rounded-md border border-neutral-200 py-2 px-3 text-sm text-neutral-600 outline-none">
                    <option value="">Vsi statusi</option>
                    <option v-for="s in ORDER_STATUS_ORDER" :key="s" :value="s">{{ ORDER_STATUS_META[s as OrderStatus].label }}</option>
                </select>

                <select v-model="payment" class="rounded-md border border-neutral-200 py-2 px-3 text-sm text-neutral-600 outline-none">
                    <option value="">Vsa plačila</option>
                    <option v-for="(meta, key) in PAYMENT_STATUS_META" :key="key" :value="key">{{ meta.label }}</option>
                </select>

                <select v-model="due" class="rounded-md border border-neutral-200 py-2 px-3 text-sm text-neutral-600 outline-none">
                    <option value="">Katerikoli rok</option>
                    <option value="today">Rok danes</option>
                    <option value="week">Naslednjih 7 dni</option>
                    <option value="overdue">Zamujeno</option>
                </select>
            </div>

            <div v-if="orders.data.length" class="space-y-2">
                <OrderCard v-for="order in orders.data" :key="order.id" :order="order" />
            </div>
            <EmptyState v-else title="Ni najdenih naročil" description="Poskusi prilagoditi filtre ali ustvari novo naročilo.">
                <template #icon><List :size="28" /></template>
            </EmptyState>

            <Pagination :links="orders.links" />
        </div>
    </AppLayout>
</template>
