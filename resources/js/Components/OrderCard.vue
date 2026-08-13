<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Avatar from '@/Components/Avatar.vue';
import Badge from '@/Components/Badge.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import { formatMoney, formatTime } from '@/lib/format';
import { ORDER_STATUS_META, PAYMENT_STATUS_META } from '@/lib/statuses';
import type { Order } from '@/types/models';

const props = defineProps<{ order: Order }>();

const statusMeta = computed(() => ORDER_STATUS_META[props.order.status]);
const paymentMeta = computed(() => PAYMENT_STATUS_META[props.order.payment_status]);
</script>

<template>
    <Link
        :href="route('orders.show', order.id)"
        class="flex items-center gap-3 rounded-lg border border-neutral-200 bg-white px-4 py-3 transition hover:border-neutral-300 hover:shadow-sm"
    >
        <Avatar :name="order.customer?.full_name ?? 'Customer'" size="md" />

        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <span class="truncate text-sm font-medium text-neutral-900">{{ order.title }}</span>
                <ChannelIcon v-if="order.channel" :type="order.channel.type" />
            </div>
            <div class="truncate text-xs text-neutral-500">
                {{ order.customer?.full_name }}
                <span v-if="order.due_time"> · {{ formatTime(`2000-01-01T${order.due_time}`) }}</span>
            </div>
        </div>

        <div class="flex shrink-0 flex-col items-end gap-1.5">
            <span class="text-sm font-semibold text-neutral-900">{{ formatMoney(order.price) }}</span>
            <div class="flex gap-1.5">
                <Badge :color="statusMeta.color" :bg="statusMeta.bg">{{ statusMeta.label }}</Badge>
                <Badge :color="paymentMeta.color" :bg="paymentMeta.bg">{{ paymentMeta.label }}</Badge>
            </div>
        </div>
    </Link>
</template>
