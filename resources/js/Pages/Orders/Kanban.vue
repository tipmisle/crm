<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import draggable from 'vuedraggable';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import Badge from '@/Components/Badge.vue';
import { List, Plus } from 'lucide-vue-next';
import { formatMoney, formatDate } from '@/lib/format';
import { ORDER_STATUS_ORDER, ORDER_STATUS_META, PAYMENT_STATUS_META } from '@/lib/statuses';
import type { Order, OrderStatus } from '@/types/models';

const props = defineProps<{
    board: Record<OrderStatus, Order[]>;
    filters: Record<string, string>;
}>();

function onChange(status: OrderStatus, event: { added?: { element: Order } }) {
    if (event.added) {
        router.patch(route('orders.update', event.added.element.id), { status }, { preserveScroll: true, preserveState: true });
    }
}
</script>

<template>
    <Head title="Orders" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Orders</h1>
        </template>

        <div class="flex h-[calc(100vh-3.5rem)] flex-col px-6 py-6">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-2xl font-semibold text-neutral-900">Orders</h1>
                <div class="flex items-center gap-2">
                    <Link
                        :href="route('orders.index')"
                        class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <List :size="14" /> List
                    </Link>
                    <Link
                        :href="route('orders.create')"
                        class="flex items-center gap-1.5 rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-800"
                    >
                        <Plus :size="14" /> New order
                    </Link>
                </div>
            </div>

            <div class="flex flex-1 gap-4 overflow-x-auto pb-2">
                <div
                    v-for="status in ORDER_STATUS_ORDER"
                    :key="status"
                    class="flex w-72 shrink-0 flex-col rounded-lg bg-neutral-100"
                >
                    <div class="flex items-center justify-between px-3 py-2.5">
                        <div class="flex items-center gap-1.5">
                            <span class="h-2 w-2 rounded-full" :style="{ backgroundColor: ORDER_STATUS_META[status].color }" />
                            <span class="text-xs font-semibold text-neutral-700">{{ ORDER_STATUS_META[status].label }}</span>
                        </div>
                        <span class="text-xs text-neutral-400">{{ board[status]?.length ?? 0 }}</span>
                    </div>

                    <draggable
                        :list="board[status]"
                        item-key="id"
                        group="orders"
                        class="flex-1 space-y-2 overflow-y-auto px-2 pb-2"
                        :style="{ minHeight: '4rem' }"
                        @change="(e: any) => onChange(status, e)"
                    >
                        <template #item="{ element: order }: { element: Order }">
                            <Link
                                :href="route('orders.show', order.id)"
                                class="block cursor-grab rounded-md border border-neutral-200 bg-white p-3 shadow-sm hover:border-neutral-300"
                            >
                                <div class="flex items-center gap-2">
                                    <Avatar :name="order.customer?.full_name ?? 'Customer'" size="xs" />
                                    <span class="truncate text-sm font-medium text-neutral-900">{{ order.customer?.full_name }}</span>
                                </div>
                                <p class="mt-1.5 truncate text-sm text-neutral-700">{{ order.title }}</p>
                                <div class="mt-2 flex items-center justify-between">
                                    <span class="text-xs text-neutral-500">{{ formatDate(order.due_date) }}</span>
                                    <span class="text-sm font-semibold text-neutral-900">{{ formatMoney(order.price) }}</span>
                                </div>
                                <Badge class="mt-2" :color="PAYMENT_STATUS_META[order.payment_status].color" :bg="PAYMENT_STATUS_META[order.payment_status].bg">
                                    {{ PAYMENT_STATUS_META[order.payment_status].label }}
                                </Badge>
                            </Link>
                        </template>
                    </draggable>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
