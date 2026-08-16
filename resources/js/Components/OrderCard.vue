<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Avatar from '@/Components/Avatar.vue';
import Badge from '@/Components/Badge.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import { formatDate, formatMoney, formatTime } from '@/lib/format';
import type { Order } from '@/types/models';
import type { PageProps } from '@/types';

const props = defineProps<{ order: Order }>();

const page = usePage<PageProps>();
const fallback = (key: string) => ({ label: key, color: '#4B5563', bg: '#F1F2F4' });
const statusMeta = computed(
    () => page.props.orderStatuses?.find((s) => s.key === props.order.status) ?? fallback(props.order.status),
);
const paymentMeta = computed(
    () => page.props.paymentStatuses?.find((s) => s.key === props.order.payment_status) ?? fallback(props.order.payment_status),
);
</script>

<template>
    <Link
        :href="route('orders.show', order.id)"
        class="flex flex-col gap-3 rounded-lg border border-neutral-200 bg-white px-4 py-3 transition hover:border-neutral-300 hover:shadow-sm sm:flex-row sm:items-center"
    >
        <div class="flex min-w-0 items-center gap-3 sm:flex-1">
            <Avatar :name="order.customer?.full_name ?? 'Stranka'" size="md" />

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="truncate text-sm font-medium text-neutral-900">{{ order.title }}</span>
                    <ChannelIcon v-if="order.channel" :type="order.channel.type" />
                </div>
                <div class="truncate text-xs text-neutral-500">
                    {{ order.customer?.full_name }}
                    <span v-if="order.due_date"> · {{ formatDate(order.due_date) }}</span>
                    <span v-if="order.due_time"> · {{ formatTime(`2000-01-01T${order.due_time}`) }}</span>
                </div>
            </div>
        </div>

        <div class="flex shrink-0 items-center justify-between gap-2 sm:flex-col sm:items-end sm:justify-start sm:gap-1.5">
            <span class="text-sm font-semibold text-neutral-900">{{ formatMoney(order.price) }}</span>
            <div class="flex flex-wrap gap-1.5 sm:justify-end">
                <Badge :color="statusMeta.color" :bg="statusMeta.bg">{{ statusMeta.label }}</Badge>
                <Badge :color="paymentMeta.color" :bg="paymentMeta.bg">{{ paymentMeta.label }}</Badge>
            </div>
        </div>
    </Link>
</template>
