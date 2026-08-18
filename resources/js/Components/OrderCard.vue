<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Avatar from '@/Components/Avatar.vue';
import Badge from '@/Components/Badge.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import { formatDate, formatMoney, formatTime } from '@/lib/format';
import type { Order } from '@/types/models';
import type { PageProps } from '@/types';
import { FileText, Receipt, CheckCircle2, XCircle, Undo2, Circle } from 'lucide-vue-next';

const props = defineProps<{ order: Order }>();

const page = usePage<PageProps>();
const fallback = (key: string) => ({ label: key, color: '#4B5563', bg: '#F1F2F4', is_completed: false, is_cancelled: false, is_refunded: false });
const orderStatuses = computed(() => page.props.orderStatuses ?? []);
const statusMeta = computed(() => orderStatuses.value.find((s) => s.key === props.order.status) ?? fallback(props.order.status));
const paymentMeta = computed(
    () => page.props.paymentStatuses?.find((s) => s.key === props.order.payment_status) ?? fallback(props.order.payment_status),
);

const hasProforma = computed(() => props.order.sales_documents?.some((d) => d.type === 'proforma') ?? false);
const hasInvoice = computed(() => props.order.sales_documents?.some((d) => d.type === 'invoice') ?? false);

const isCompleted = computed(() => Boolean(statusMeta.value.is_completed));
const isRefunded = computed(() => Boolean(statusMeta.value.is_refunded));
const isCancelledOrRefunded = computed(() => Boolean(statusMeta.value.is_cancelled || statusMeta.value.is_refunded));
const isClosed = computed(() => isCompleted.value || isCancelledOrRefunded.value);

const completedStatusKey = computed(() => orderStatuses.value.find((s) => s.is_completed)?.key);
const defaultStatusKey = computed(() => orderStatuses.value.find((s) => s.is_default)?.key ?? orderStatuses.value[0]?.key);

function toggleCompleted(event: Event) {
    event.preventDefault();
    event.stopPropagation();

    if (isCancelledOrRefunded.value) return;

    const nextStatus = isCompleted.value ? defaultStatusKey.value : completedStatusKey.value;
    if (!nextStatus) return;

    router.patch(route('orders.update', props.order.id), { status: nextStatus }, { preserveScroll: true });
}
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
                    <span class="truncate text-sm font-medium text-neutral-900" :class="{ 'line-through decoration-neutral-900': isCancelledOrRefunded }">{{ order.title }}</span>
                    <ChannelIcon v-if="order.channel" :type="order.channel.type" />
                </div>
                <div class="truncate text-xs text-neutral-500">
                    {{ order.customer?.full_name }}
                    <span v-if="order.due_date"> · {{ formatDate(order.due_date) }}</span>
                    <span v-if="order.due_time"> · {{ formatTime(`2000-01-01T${order.due_time}`) }}</span>
                </div>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            <div class="flex items-center justify-between gap-2 sm:flex-col sm:items-end sm:justify-start sm:gap-1.5">
                <span class="flex items-center gap-1.5">
                    <span class="text-sm font-semibold text-neutral-900">{{ formatMoney(order.price) }}</span>
                    <FileText v-if="hasProforma" :size="13" class="shrink-0 text-neutral-400" title="Izdan predračun" />
                    <Receipt v-if="hasInvoice" :size="13" class="shrink-0 text-neutral-400" title="Izdan račun" />
                </span>
                <div class="flex flex-wrap gap-1.5 sm:justify-end">
                    <Badge :color="isClosed ? '#9CA3AF' : statusMeta.color" :bg="isClosed ? '#F3F4F6' : statusMeta.bg">{{ statusMeta.label }}</Badge>
                    <Badge :color="isClosed ? '#9CA3AF' : paymentMeta.color" :bg="isClosed ? '#F3F4F6' : paymentMeta.bg">{{ paymentMeta.label }}</Badge>
                </div>
            </div>
            <button
                type="button"
                :disabled="isCancelledOrRefunded"
                :title="isCancelledOrRefunded ? statusMeta.label : isCompleted ? 'Odkljukaj' : 'Označi kot zaključeno'"
                class="shrink-0"
                :class="isCancelledOrRefunded ? 'cursor-default' : 'cursor-pointer'"
                @click="toggleCompleted"
            >
                <Undo2 v-if="isRefunded" :size="18" class="text-red-500" />
                <XCircle v-else-if="isCancelledOrRefunded" :size="18" class="text-red-500" />
                <CheckCircle2 v-else-if="isCompleted" :size="18" class="text-emerald-500" />
                <Circle v-else :size="18" class="text-neutral-300 hover:text-neutral-400" />
            </button>
        </div>
    </Link>
</template>
