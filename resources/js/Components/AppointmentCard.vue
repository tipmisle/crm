<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Avatar from '@/Components/Avatar.vue';
import Badge from '@/Components/Badge.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import { formatDate, formatMoney, formatTime } from '@/lib/format';
import { APPOINTMENT_STATUS_META, PAYMENT_STATUS_META } from '@/lib/statuses';
import type { Appointment } from '@/types/models';

const props = defineProps<{ appointment: Appointment }>();

const statusMeta = computed(() => APPOINTMENT_STATUS_META[props.appointment.status]);
const paymentMeta = computed(() => PAYMENT_STATUS_META[props.appointment.payment_status]);
</script>

<template>
    <Link
        :href="route('appointments.show', appointment.id)"
        class="flex items-center gap-3 rounded-lg border border-neutral-200 bg-white px-4 py-3 transition hover:border-neutral-300 hover:shadow-sm"
    >
        <Avatar :name="appointment.customer?.full_name ?? 'Stranka'" size="md" />

        <div class="min-w-0 flex-1">
            <div class="flex items-center gap-2">
                <span class="truncate text-sm font-medium text-neutral-900">{{ appointment.service_name }}</span>
                <ChannelIcon v-if="appointment.channel" :type="appointment.channel.type" />
            </div>
            <div class="truncate text-xs text-neutral-500">
                {{ appointment.customer?.full_name }}
                <span> · {{ formatDate(appointment.appointment_date) }}</span>
                <span> · {{ formatTime(`2000-01-01T${appointment.start_time}`) }}</span>
            </div>
        </div>

        <div class="flex shrink-0 flex-col items-end gap-1.5">
            <span v-if="appointment.price !== null" class="text-sm font-semibold text-neutral-900">{{ formatMoney(appointment.price) }}</span>
            <div class="flex gap-1.5">
                <Badge :color="statusMeta.color" :bg="statusMeta.bg">{{ statusMeta.label }}</Badge>
                <Badge :color="paymentMeta.color" :bg="paymentMeta.bg">{{ paymentMeta.label }}</Badge>
            </div>
        </div>
    </Link>
</template>
