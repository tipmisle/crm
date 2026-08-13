<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionCard from '@/Components/SectionCard.vue';
import OrderCard from '@/Components/OrderCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import { CalendarClock, Check, PartyPopper } from 'lucide-vue-next';
import { formatDate, formatDateTime, isPastDue } from '@/lib/format';
import type { FollowUp, Order } from '@/types/models';

interface AttentionItem {
    key: string;
    label: string;
    count: number;
    href: string;
}

defineProps<{
    attention: AttentionItem[];
    todaysOrders: Order[];
    followUps: (FollowUp & { is_overdue: boolean; href: string })[];
    upcoming: Order[];
}>();

const today = new Date();

function completeFollowUp(id: number) {
    router.patch(route('follow-ups.complete', id), {}, { preserveScroll: true });
}
</script>

<template>
    <Head title="Today" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Today</h1>
        </template>

        <div class="mx-auto max-w-5xl space-y-6 px-6 py-8">
            <div>
                <h1 class="text-2xl font-semibold text-neutral-900">
                    {{ today.toLocaleDateString('en-US', { weekday: 'long', month: 'long', day: 'numeric' }) }}
                </h1>
                <p class="text-sm text-neutral-500">Here's what needs your attention today.</p>
            </div>

            <SectionCard v-if="attention.length" title="Needs your attention">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <Link
                        v-for="item in attention"
                        :key="item.key"
                        :href="item.href"
                        class="flex items-center justify-between rounded-lg border border-neutral-200 bg-neutral-50 px-4 py-3 transition hover:border-[var(--color-accent-300)] hover:bg-[var(--color-accent-50)]"
                    >
                        <span class="text-sm font-medium text-neutral-800">{{ item.label }}</span>
                        <span
                            class="flex h-6 min-w-6 items-center justify-center rounded-full bg-[var(--color-accent-500)] px-1.5 text-xs font-semibold text-white"
                        >
                            {{ item.count }}
                        </span>
                    </Link>
                </div>
            </SectionCard>

            <SectionCard v-else title="Needs your attention">
                <EmptyState title="You're all caught up" description="Nothing urgent needs your attention right now.">
                    <template #icon>
                        <PartyPopper :size="28" />
                    </template>
                </EmptyState>
            </SectionCard>

            <SectionCard title="Today's orders" :subtitle="`${todaysOrders.length} due today`">
                <div v-if="todaysOrders.length" class="space-y-2">
                    <OrderCard v-for="order in todaysOrders" :key="order.id" :order="order" />
                </div>
                <EmptyState v-else title="No orders due today" description="Enjoy the breathing room.">
                    <template #icon>
                        <CalendarClock :size="28" />
                    </template>
                </EmptyState>
            </SectionCard>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <SectionCard title="Follow-ups">
                    <div v-if="followUps.length" class="space-y-2">
                        <div
                            v-for="followUp in followUps"
                            :key="followUp.id"
                            class="flex items-start gap-2.5 rounded-lg border border-neutral-100 px-3 py-2.5 hover:border-neutral-200 hover:bg-neutral-50"
                        >
                            <button
                                type="button"
                                class="mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full border border-neutral-300 text-transparent hover:border-neutral-400 hover:text-neutral-400"
                                title="Mark as done"
                                @click="completeFollowUp(followUp.id)"
                            >
                                <Check :size="10" />
                            </button>
                            <Link :href="followUp.href" class="min-w-0 flex-1">
                                <p class="text-sm text-neutral-800">{{ followUp.note }}</p>
                                <p class="text-xs" :class="followUp.is_overdue ? 'text-red-500' : 'text-neutral-500'">
                                    {{ followUp.is_overdue ? 'Overdue' : 'Due' }} {{ formatDateTime(followUp.due_at) }}
                                </p>
                            </Link>
                        </div>
                    </div>
                    <EmptyState v-else title="No follow-ups scheduled" />
                </SectionCard>

                <SectionCard title="Upcoming" subtitle="Next 7 days">
                    <div v-if="upcoming.length" class="space-y-2">
                        <Link
                            v-for="order in upcoming"
                            :key="order.id"
                            :href="route('orders.show', order.id)"
                            class="flex items-center justify-between rounded-lg border border-neutral-100 px-3 py-2.5 hover:border-neutral-200 hover:bg-neutral-50"
                        >
                            <div class="min-w-0">
                                <p class="truncate text-sm font-medium text-neutral-800">{{ order.title }}</p>
                                <p class="truncate text-xs text-neutral-500">{{ order.customer?.full_name }}</p>
                            </div>
                            <span
                                class="shrink-0 text-xs font-medium"
                                :class="isPastDue(order.due_date) ? 'text-red-500' : 'text-neutral-500'"
                            >
                                {{ formatDate(order.due_date) }}
                            </span>
                        </Link>
                    </div>
                    <EmptyState v-else title="Nothing coming up" description="No deadlines in the next week." />
                </SectionCard>
            </div>
        </div>
    </AppLayout>
</template>
