<script setup lang="ts">
import { ref, watch } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import Badge from '@/Components/Badge.vue';
import EmptyState from '@/Components/EmptyState.vue';
import Pagination from '@/Components/Pagination.vue';
import { Search, Plus, Users } from 'lucide-vue-next';
import { formatMoney, formatDate } from '@/lib/format';
import type { Channel } from '@/types/models';

interface CustomerRow {
    id: number;
    full_name: string;
    email: string | null;
    phone: string | null;
    primary_channel: Channel | null;
    last_interaction_at: string | null;
    orders_count: number;
    lifetime_spend: number;
    open_orders_count: number;
}

const props = defineProps<{
    customers: { data: CustomerRow[]; links: { url: string | null; label: string; active: boolean }[] };
    filters: { search?: string };
}>();

const search = ref(props.filters.search ?? '');

let debounce: ReturnType<typeof setTimeout>;
watch(search, () => {
    clearTimeout(debounce);
    debounce = setTimeout(() => {
        router.get(route('customers.index'), { search: search.value || undefined }, { preserveState: true, replace: true });
    }, 300);
});
</script>

<template>
    <Head title="Customers" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Customers</h1>
        </template>

        <div class="mx-auto max-w-5xl space-y-5 px-6 py-8">
            <div class="flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-neutral-900">Customers</h1>
                <Link
                    :href="route('customers.create')"
                    class="flex items-center gap-1.5 rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-800"
                >
                    <Plus :size="14" /> New customer
                </Link>
            </div>

            <div class="relative">
                <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
                <input
                    v-model="search"
                    type="text"
                    placeholder="Search customers…"
                    class="w-full max-w-sm rounded-md border border-neutral-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-400"
                />
            </div>

            <div v-if="customers.data.length" class="overflow-hidden rounded-xl border border-neutral-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="border-b border-neutral-100 bg-neutral-50 text-left text-xs font-medium text-neutral-500">
                        <tr>
                            <th class="px-4 py-2.5">Customer</th>
                            <th class="px-4 py-2.5">Contact</th>
                            <th class="px-4 py-2.5">Last interaction</th>
                            <th class="px-4 py-2.5">Orders</th>
                            <th class="px-4 py-2.5">Lifetime value</th>
                            <th class="px-4 py-2.5">Open orders</th>
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
                            <td class="px-4 py-3 text-neutral-600">{{ customer.orders_count }}</td>
                            <td class="px-4 py-3 font-medium text-neutral-900">{{ formatMoney(customer.lifetime_spend) }}</td>
                            <td class="px-4 py-3">
                                <Badge v-if="customer.open_orders_count" color="#0E7490" bg="#E0F7FA">
                                    {{ customer.open_orders_count }} open
                                </Badge>
                                <span v-else class="text-neutral-400">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <EmptyState v-else title="No customers found">
                <template #icon><Users :size="28" /></template>
            </EmptyState>

            <Pagination :links="customers.links" />
        </div>
    </AppLayout>
</template>
