<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import Badge from '@/Components/Badge.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import OrderCard from '@/Components/OrderCard.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FollowUpModal from '@/Components/FollowUpModal.vue';
import { formatMoney, formatDate, formatDateTime, relativeTime } from '@/lib/format';
import { CONVERSATION_STATUS_META } from '@/lib/statuses';
import { Bell, Plus, Pencil } from 'lucide-vue-next';
import type { ActivityLogEntry, Conversation, CustomerIdentity, FollowUp, Order } from '@/types/models';

interface CustomerDetail {
    id: number;
    full_name: string;
    email: string | null;
    phone: string | null;
    notes: string | null;
    tags: string[] | null;
    identities: CustomerIdentity[];
    first_contacted_at: string | null;
    last_interaction_at: string | null;
    orders: Order[];
    conversations: Conversation[];
    lifetime_spend: number;
    open_orders_count: number;
    completed_orders_count: number;
}

const props = defineProps<{
    customer: CustomerDetail;
    followUps: FollowUp[];
    activity: ActivityLogEntry[];
}>();

const editing = ref(false);
const editForm = useForm({
    full_name: props.customer.full_name,
    email: props.customer.email ?? '',
    phone: props.customer.phone ?? '',
    notes: props.customer.notes ?? '',
});

function saveEdit() {
    editForm.patch(route('customers.update', props.customer.id), {
        onSuccess: () => (editing.value = false),
    });
}

const openOrders = computed(() => props.customer.orders.filter((o) => !['completed', 'cancelled'].includes(o.status)));
const followUpOpen = ref(false);
</script>

<template>
    <Head :title="customer.full_name" />

    <AppLayout>
        <template #header>
            <div class="flex items-center gap-2">
                <Link :href="route('customers.index')" class="text-sm text-neutral-400 hover:text-neutral-600">Customers</Link>
                <span class="text-neutral-300">/</span>
                <span class="text-sm font-semibold text-neutral-900">{{ customer.full_name }}</span>
            </div>
        </template>

        <div class="mx-auto max-w-5xl px-6 py-8">
            <div class="mb-6 flex items-start justify-between">
                <div class="flex items-center gap-4">
                    <Avatar :name="customer.full_name" size="lg" />
                    <div>
                        <h1 class="text-2xl font-semibold text-neutral-900">{{ customer.full_name }}</h1>
                        <p class="text-sm text-neutral-500">
                            Customer since {{ formatDate(customer.first_contacted_at) }} · Last interaction {{ relativeTime(customer.last_interaction_at) }}
                        </p>
                    </div>
                </div>
                <button
                    type="button"
                    class="flex items-center gap-1.5 rounded-md border border-neutral-200 px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    @click="followUpOpen = true"
                >
                    <Bell :size="14" /> Set follow-up
                </button>
            </div>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <div class="space-y-6 lg:col-span-2">
                    <section v-if="openOrders.length" class="rounded-xl border border-neutral-200 bg-white p-5">
                        <div class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-neutral-900">Current orders</h2>
                            <Link
                                :href="route('orders.create', { customer_id: customer.id })"
                                class="flex items-center gap-1 text-xs font-medium text-[var(--color-accent-600)] hover:underline"
                            >
                                <Plus :size="12" /> New order
                            </Link>
                        </div>
                        <div class="mt-3 space-y-2">
                            <OrderCard v-for="order in openOrders" :key="order.id" :order="order" />
                        </div>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h2 class="text-sm font-semibold text-neutral-900">Order history</h2>
                        <div v-if="customer.orders.length" class="mt-3 space-y-2">
                            <OrderCard v-for="order in customer.orders" :key="order.id" :order="order" />
                        </div>
                        <EmptyState v-else title="No orders yet" description="Create the first order for this customer.">
                            <template #action>
                                <Link
                                    :href="route('orders.create', { customer_id: customer.id })"
                                    class="mt-2 flex items-center gap-1.5 rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-800"
                                >
                                    <Plus :size="14" /> New order
                                </Link>
                            </template>
                        </EmptyState>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h2 class="text-sm font-semibold text-neutral-900">Conversation history</h2>
                        <div v-if="customer.conversations.length" class="mt-3 space-y-2">
                            <Link
                                v-for="c in customer.conversations"
                                :key="c.id"
                                :href="route('inbox.show', c.id)"
                                class="flex items-center gap-3 rounded-lg border border-neutral-100 px-3 py-2.5 hover:border-neutral-200 hover:bg-neutral-50"
                            >
                                <ChannelIcon v-if="c.channel" :type="c.channel.type" />
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm text-neutral-800">{{ c.last_message_preview }}</p>
                                    <p class="text-xs text-neutral-400">{{ relativeTime(c.last_message_at) }}</p>
                                </div>
                                <Badge :color="CONVERSATION_STATUS_META[c.status].color" :bg="CONVERSATION_STATUS_META[c.status].bg">
                                    {{ CONVERSATION_STATUS_META[c.status].label }}
                                </Badge>
                            </Link>
                        </div>
                        <EmptyState v-else title="No conversations yet" />
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h2 class="text-sm font-semibold text-neutral-900">Activity timeline</h2>
                        <div v-if="activity.length" class="mt-3 space-y-3">
                            <div v-for="entry in activity" :key="entry.id" class="flex gap-2.5 text-sm">
                                <span class="mt-1.5 h-1.5 w-1.5 shrink-0 rounded-full bg-neutral-300" />
                                <div>
                                    <p class="text-neutral-700">{{ entry.description }}</p>
                                    <p class="text-xs text-neutral-400">{{ formatDateTime(entry.created_at) }}</p>
                                </div>
                            </div>
                        </div>
                        <EmptyState v-else title="No activity yet" />
                    </section>
                </div>

                <div class="space-y-5">
                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xs font-semibold text-neutral-500 uppercase">Contact</h3>
                            <button type="button" class="text-neutral-400 hover:text-neutral-600" @click="editing = !editing">
                                <Pencil :size="14" />
                            </button>
                        </div>

                        <template v-if="!editing">
                            <div class="mt-2 space-y-1.5 text-sm">
                                <p class="text-neutral-700"><span class="text-neutral-400">Email: </span>{{ customer.email ?? '—' }}</p>
                                <p class="text-neutral-700"><span class="text-neutral-400">Phone: </span>{{ customer.phone ?? '—' }}</p>
                            </div>
                            <p v-if="customer.notes" class="mt-3 rounded-md bg-neutral-50 p-2.5 text-xs text-neutral-600">{{ customer.notes }}</p>
                        </template>
                        <form v-else class="mt-2 space-y-2" @submit.prevent="saveEdit">
                            <input v-model="editForm.full_name" type="text" placeholder="Full name" class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" />
                            <input v-model="editForm.email" type="email" placeholder="Email" class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" />
                            <input v-model="editForm.phone" type="text" placeholder="Phone" class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" />
                            <textarea v-model="editForm.notes" rows="3" placeholder="Notes" class="w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none" />
                            <button type="submit" class="w-full rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-800">Save</button>
                        </form>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h3 class="text-xs font-semibold text-neutral-500 uppercase">Social profiles</h3>
                        <div class="mt-2 space-y-2">
                            <div v-for="identity in customer.identities" :key="identity.id" class="flex items-center gap-2">
                                <ChannelIcon :type="identity.channel_type" />
                                <span class="text-sm text-neutral-700">{{ identity.username ?? identity.display_name }}</span>
                            </div>
                            <p v-if="!customer.identities.length" class="text-sm text-neutral-400">No linked profiles.</p>
                        </div>
                    </section>

                    <section class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h3 class="text-xs font-semibold text-neutral-500 uppercase">Lifetime value</h3>
                        <p class="mt-2 text-2xl font-semibold text-neutral-900">{{ formatMoney(customer.lifetime_spend) }}</p>
                        <div class="mt-3 grid grid-cols-2 gap-2 text-center">
                            <div class="rounded-md bg-neutral-50 py-2">
                                <p class="text-lg font-semibold text-neutral-900">{{ customer.open_orders_count }}</p>
                                <p class="text-xs text-neutral-500">Open</p>
                            </div>
                            <div class="rounded-md bg-neutral-50 py-2">
                                <p class="text-lg font-semibold text-neutral-900">{{ customer.completed_orders_count }}</p>
                                <p class="text-xs text-neutral-500">Completed</p>
                            </div>
                        </div>
                    </section>

                    <section v-if="followUps.length" class="rounded-xl border border-neutral-200 bg-white p-5">
                        <h3 class="text-xs font-semibold text-neutral-500 uppercase">Follow-ups</h3>
                        <div class="mt-2 space-y-2">
                            <div v-for="f in followUps" :key="f.id" class="text-sm">
                                <p class="text-neutral-700">{{ f.note }}</p>
                                <p class="text-xs text-neutral-400">Due {{ formatDateTime(f.due_at) }}</p>
                            </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <FollowUpModal
            :show="followUpOpen"
            followable-type="App\Models\Customer"
            :followable-id="customer.id"
            :default-note="`Follow up with ${customer.full_name}`"
            @close="followUpOpen = false"
        />
    </AppLayout>
</template>
