<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import draggable from 'vuedraggable';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import Badge from '@/Components/Badge.vue';
import { List, Plus, CalendarDays, Search } from 'lucide-vue-next';
import { formatMoney, formatDate } from '@/lib/format';
import type { Order, OrderStatus } from '@/types/models';
import type { PageProps } from '@/types';

const props = defineProps<{
    board: Record<OrderStatus, Order[]>;
    filters: Record<string, string>;
}>();

const page = usePage<PageProps>();
const orderStatuses = computed(() => page.props.orderStatuses ?? []);
const paymentStatuses = computed(() => page.props.paymentStatuses ?? []);
const search = ref(props.filters.search ?? '');
const status = ref(props.filters.status ?? '');
const payment = ref(props.filters.payment ?? '');
const due = ref(props.filters.due ?? '');

function applyFilters() {
    router.get(
        route('orders.index', { view: 'kanban' }),
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

function paymentMeta(key: string) {
    return paymentStatuses.value.find((s) => s.key === key) ?? { label: key, color: '#4B5563', bg: '#F1F2F4' };
}

function onChange(status: OrderStatus, event: { added?: { element: Order } }) {
    if (event.added) {
        router.patch(route('orders.update', event.added.element.id), { status }, { preserveScroll: true, preserveState: true });
    }
}
</script>

<template>
    <Head title="Naročila" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Naročila</h1>
        </template>

        <div class="mx-auto flex h-[calc(100vh-3.5rem)] w-full max-w-7xl flex-col px-4 py-4 sm:px-6 sm:py-6">
            <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                <h1 class="text-2xl font-semibold text-neutral-900">Naročila</h1>
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="route('orders.index', { ...filters, view: 'list' })"
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <List :size="14" /> Seznam
                    </Link>
                    <Link
                        :href="route('orders.index', { ...filters, view: 'calendar' })"
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <CalendarDays :size="14" /> Koledar
                    </Link>
                    <Link
                        :href="route('orders.create')"
                        class="flex items-center gap-1.5 rounded-md bg-[var(--color-ink-900)] px-3 py-1.5 text-sm font-medium text-white hover:bg-[var(--color-ink-800)]"
                    >
                        <Plus :size="14" /> Novo naročilo
                    </Link>
                </div>
            </div>

            <div class="mb-4 flex flex-wrap items-center gap-2">
                <div class="relative min-w-48 flex-1">
                    <Search :size="14" class="absolute left-3 top-1/2 -translate-y-1/2 text-neutral-400" />
                    <input v-model="search" type="text" placeholder="Iskanje po naročilih, strankah…" class="w-full rounded-md border border-neutral-200 py-2 pl-9 pr-3 text-sm outline-none focus:border-neutral-400" />
                </div>
                <select v-model="status" class="rounded-md border border-neutral-200 py-2 px-3 text-sm text-neutral-600 outline-none">
                    <option value="">Vsi statusi</option>
                    <option v-for="s in orderStatuses" :key="s.key" :value="s.key">{{ s.label }}</option>
                </select>
                <select v-model="payment" class="rounded-md border border-neutral-200 py-2 px-3 text-sm text-neutral-600 outline-none">
                    <option value="">Vsa plačila</option>
                    <option v-for="s in paymentStatuses" :key="s.key" :value="s.key">{{ s.label }}</option>
                </select>
                <select v-model="due" class="rounded-md border border-neutral-200 py-2 px-3 text-sm text-neutral-600 outline-none">
                    <option value="">Katerikoli rok</option>
                    <option value="today">Rok danes</option>
                    <option value="week">Naslednjih 7 dni</option>
                    <option value="overdue">Zamujeno</option>
                </select>
            </div>

            <div class="flex flex-1 gap-4 overflow-x-auto pb-2">
                <div
                    v-for="status in orderStatuses"
                    :key="status.key"
                    class="flex w-72 shrink-0 flex-col rounded-lg bg-neutral-100"
                >
                    <div class="flex items-center justify-between px-3 py-2.5">
                        <div class="flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: status.color }" />
                            <span class="text-xs font-semibold text-neutral-700">{{ status.label }}</span>
                        </div>
                        <span class="text-xs text-neutral-400">{{ board[status.key]?.length ?? 0 }}</span>
                    </div>

                    <draggable
                        :list="board[status.key]"
                        item-key="id"
                        group="orders"
                        class="flex-1 space-y-2 overflow-y-auto px-2 pb-2"
                        :style="{ minHeight: '4rem' }"
                        @change="(e: any) => onChange(status.key, e)"
                    >
                        <template #item="{ element: order }: { element: Order }">
                            <Link
                                :href="route('orders.show', order.id)"
                                class="block cursor-grab rounded-md border border-neutral-200 bg-white p-3 shadow-sm hover:border-neutral-300"
                            >
                                <div class="flex items-center gap-2">
                                    <Avatar :name="order.customer?.full_name ?? 'Stranka'" size="xs" />
                                    <span class="truncate text-sm font-medium text-neutral-900">{{ order.customer?.full_name }}</span>
                                </div>
                                <p class="mt-1.5 truncate text-sm text-neutral-700">{{ order.title }}</p>
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="text-xs text-neutral-500">{{ formatDate(order.due_date) }}</span>
                                    <span class="text-sm font-semibold text-neutral-900">{{ formatMoney(order.price) }}</span>
                                </div>
                                <Badge class="mt-2" :color="paymentMeta(order.payment_status).color" :bg="paymentMeta(order.payment_status).bg">
                                    {{ paymentMeta(order.payment_status).label }}
                                </Badge>
                            </Link>
                        </template>
                    </draggable>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
