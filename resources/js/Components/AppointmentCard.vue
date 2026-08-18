<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import Avatar from '@/Components/Avatar.vue';
import Badge from '@/Components/Badge.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import { formatDate, formatMoney, formatTime } from '@/lib/format';
import type { Appointment } from '@/types/models';
import type { PageProps } from '@/types';
import { CheckCircle2, XCircle, Undo2, Circle } from 'lucide-vue-next';

const props = defineProps<{ appointment: Appointment }>();

const page = usePage<PageProps>();
const fallback = (key: string) => ({ label: key, color: '#4B5563', bg: '#F1F2F4', is_completed: false, is_cancelled: false, is_no_show: false, is_refunded: false });
const appointmentStatuses = computed(() => page.props.appointmentStatuses ?? []);
const statusMeta = computed(() => appointmentStatuses.value.find((s) => s.key === props.appointment.status) ?? fallback(props.appointment.status));
const paymentMeta = computed(
    () => page.props.paymentStatuses?.find((s) => s.key === props.appointment.payment_status) ?? fallback(props.appointment.payment_status),
);

const isCompleted = computed(() => Boolean(statusMeta.value.is_completed));
const isRefunded = computed(() => Boolean(statusMeta.value.is_refunded));
const isTerminal = computed(() => Boolean(statusMeta.value.is_cancelled || statusMeta.value.is_no_show || statusMeta.value.is_refunded));
const isClosed = computed(() => isCompleted.value || isTerminal.value);

const completedStatusKey = computed(() => appointmentStatuses.value.find((s) => s.is_completed)?.key);
const defaultStatusKey = computed(() => appointmentStatuses.value.find((s) => s.is_default)?.key ?? appointmentStatuses.value[0]?.key);

function toggleCompleted(event: Event) {
    event.preventDefault();
    event.stopPropagation();

    if (isTerminal.value) return;

    const nextStatus = isCompleted.value ? defaultStatusKey.value : completedStatusKey.value;
    if (!nextStatus) return;

    router.patch(route('appointments.update', props.appointment.id), { status: nextStatus }, { preserveScroll: true });
}
</script>

<template>
    <Link
        :href="route('appointments.show', appointment.id)"
        class="flex flex-col gap-3 rounded-lg border border-neutral-200 bg-white px-4 py-3 transition hover:border-neutral-300 hover:shadow-sm sm:flex-row sm:items-center"
    >
        <div class="flex min-w-0 items-center gap-3 sm:flex-1">
            <Avatar :name="appointment.customer?.full_name ?? 'Stranka'" size="md" />

            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="truncate text-sm font-medium text-neutral-900" :class="{ 'line-through decoration-neutral-900': isTerminal }">{{ appointment.service_name }}</span>
                    <ChannelIcon v-if="appointment.channel" :type="appointment.channel.type" />
                </div>
                <div class="truncate text-xs text-neutral-500">
                    {{ appointment.customer?.full_name }}
                    <span> · {{ formatDate(appointment.appointment_date) }}</span>
                    <span> · {{ formatTime(`2000-01-01T${appointment.start_time}`) }}</span>
                </div>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            <div class="flex items-center justify-between gap-2 sm:flex-col sm:items-end sm:justify-start sm:gap-1.5">
                <span v-if="appointment.price !== null" class="text-sm font-semibold text-neutral-900">{{ formatMoney(appointment.price) }}</span>
                <div class="flex flex-wrap gap-1.5 sm:justify-end">
                    <Badge :color="isClosed ? '#9CA3AF' : statusMeta.color" :bg="isClosed ? '#F3F4F6' : statusMeta.bg">{{ statusMeta.label }}</Badge>
                    <Badge :color="isClosed ? '#9CA3AF' : paymentMeta.color" :bg="isClosed ? '#F3F4F6' : paymentMeta.bg">{{ paymentMeta.label }}</Badge>
                </div>
            </div>
            <button
                type="button"
                :disabled="isTerminal"
                :title="isTerminal ? statusMeta.label : isCompleted ? 'Odkljukaj' : 'Označi kot zaključeno'"
                class="shrink-0"
                :class="isTerminal ? 'cursor-default' : 'cursor-pointer'"
                @click="toggleCompleted"
            >
                <Undo2 v-if="isRefunded" :size="18" class="text-red-500" />
                <XCircle v-else-if="isTerminal" :size="18" class="text-red-500" />
                <CheckCircle2 v-else-if="isCompleted" :size="18" class="text-emerald-500" />
                <Circle v-else :size="18" class="text-neutral-300 hover:text-neutral-400" />
            </button>
        </div>
    </Link>
</template>
