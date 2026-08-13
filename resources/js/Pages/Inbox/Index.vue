<script setup lang="ts">
import { ref, computed, nextTick, watch } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import Badge from '@/Components/Badge.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import MessageBubble from '@/Components/MessageBubble.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FollowUpModal from '@/Components/FollowUpModal.vue';
import Modal from '@/Components/Modal.vue';
import { relativeTime, formatMoney, formatDate } from '@/lib/format';
import { CONVERSATION_STATUS_META } from '@/lib/statuses';
import { Send, Plus, Bell, StickyNote, UserRound, Inbox as InboxIcon } from 'lucide-vue-next';
import type { Channel, ConversationStatus } from '@/types/models';

interface ConversationListItem {
    id: number;
    customer_id: number | null;
    display_name: string;
    channel: Channel;
    status: ConversationStatus;
    last_message_preview: string | null;
    last_message_at: string | null;
    unread_count: number;
}

interface CustomerContext {
    id: number;
    full_name: string;
    email: string | null;
    phone: string | null;
    notes: string | null;
    identities: { channel_type: string; username: string | null }[];
    total_orders_count: number;
    lifetime_spend: number;
    open_orders_count: number;
    current_open_order: { id: number; order_number: string; title: string } | null;
    last_order_date: string | null;
}

interface ConversationDetail {
    id: number;
    status: ConversationStatus;
    channel: Channel;
    customer_display_name: string | null;
    customer_username: string | null;
    messages: { id: number; sender_type: string; body: string; sent_at: string }[];
    customer: CustomerContext | null;
}

const props = defineProps<{
    conversations: ConversationListItem[];
    conversation: ConversationDetail | null;
}>();

const messageForm = useForm({ body: '' });
const threadEl = ref<HTMLElement | null>(null);

function scrollToBottom() {
    nextTick(() => {
        threadEl.value?.scrollTo({ top: threadEl.value.scrollHeight });
    });
}

watch(() => props.conversation?.id, scrollToBottom, { immediate: true });

function sendMessage() {
    if (!props.conversation || !messageForm.body.trim()) return;

    messageForm.post(route('inbox.messages.store', props.conversation.id), {
        preserveScroll: true,
        onSuccess: () => {
            messageForm.reset();
            scrollToBottom();
        },
    });
}

function updateStatus(status: string) {
    if (!props.conversation) return;
    router.patch(route('inbox.update', props.conversation.id), { status }, { preserveScroll: true });
}

function createCustomer() {
    if (!props.conversation) return;
    router.post(route('inbox.create-customer', props.conversation.id));
}

const followUpOpen = ref(false);
const noteModalOpen = ref(false);
const noteForm = useForm({ note: '' });

function submitNote() {
    if (!props.conversation) return;
    noteForm.post(route('inbox.notes.store', props.conversation.id), {
        onSuccess: () => {
            noteForm.reset();
            noteModalOpen.value = false;
        },
    });
}

const followableId = computed(() => props.conversation?.customer?.id ?? props.conversation?.id ?? 0);
const followableType = computed(() =>
    props.conversation?.customer ? 'App\\Models\\Customer' : 'App\\Models\\Conversation',
);
</script>

<template>
    <Head title="Inbox" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Inbox</h1>
        </template>

        <div class="flex h-[calc(100vh-3.5rem)]">
            <div class="w-80 shrink-0 overflow-y-auto border-r border-neutral-200 bg-white">
                <Link
                    v-for="c in conversations"
                    :key="c.id"
                    :href="route('inbox.show', c.id)"
                    class="flex gap-3 border-b border-neutral-100 px-4 py-3 hover:bg-neutral-50"
                    :class="conversation?.id === c.id ? 'bg-[var(--color-accent-50)]' : ''"
                >
                    <div class="relative shrink-0">
                        <Avatar :name="c.display_name" size="md" />
                        <span class="absolute -bottom-0.5 -right-0.5">
                            <ChannelIcon :type="c.channel.type" />
                        </span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-1">
                            <span class="truncate text-sm font-medium text-neutral-900">{{ c.display_name }}</span>
                            <span class="shrink-0 text-[11px] text-neutral-400">{{ relativeTime(c.last_message_at) }}</span>
                        </div>
                        <p class="truncate text-xs text-neutral-500">{{ c.last_message_preview }}</p>
                        <div class="mt-1 flex items-center gap-1.5">
                            <Badge :color="CONVERSATION_STATUS_META[c.status].color" :bg="CONVERSATION_STATUS_META[c.status].bg">
                                {{ CONVERSATION_STATUS_META[c.status].label }}
                            </Badge>
                            <span v-if="c.unread_count" class="flex h-4 min-w-4 items-center justify-center rounded-full bg-[var(--color-accent-500)] px-1 text-[10px] font-semibold text-white">
                                {{ c.unread_count }}
                            </span>
                        </div>
                    </div>
                </Link>

                <EmptyState v-if="!conversations.length" title="No conversations yet" />
            </div>

            <div class="flex flex-1 flex-col">
                <template v-if="conversation">
                    <div class="flex items-center justify-between border-b border-neutral-200 bg-white px-5 py-3">
                        <div class="flex items-center gap-2">
                            <ChannelIcon :type="conversation.channel.type" size="md" />
                            <span class="text-sm font-medium text-neutral-900">
                                {{ conversation.customer?.full_name ?? conversation.customer_display_name }}
                            </span>
                            <span class="text-xs text-neutral-400">{{ conversation.customer_username }}</span>
                        </div>

                        <select
                            :value="conversation.status"
                            class="rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 outline-none"
                            @change="updateStatus(($event.target as HTMLSelectElement).value)"
                        >
                            <option v-for="(meta, key) in CONVERSATION_STATUS_META" :key="key" :value="key">{{ meta.label }}</option>
                        </select>
                    </div>

                    <div ref="threadEl" class="flex-1 space-y-3 overflow-y-auto px-5 py-5">
                        <MessageBubble v-for="m in conversation.messages" :key="m.id" :message="(m as any)" />
                    </div>

                    <form class="flex items-center gap-2 border-t border-neutral-200 bg-white p-4" @submit.prevent="sendMessage">
                        <input
                            v-model="messageForm.body"
                            type="text"
                            placeholder="Type a reply…"
                            class="flex-1 rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                        <button
                            type="submit"
                            :disabled="messageForm.processing || !messageForm.body.trim()"
                            class="flex items-center gap-1.5 rounded-md bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800 disabled:opacity-50"
                        >
                            <Send :size="14" /> Send
                        </button>
                    </form>
                </template>

                <EmptyState v-else title="Select a conversation" description="Choose a conversation from the list to see the thread.">
                    <template #icon><InboxIcon :size="28" /></template>
                </EmptyState>
            </div>

            <div v-if="conversation" class="w-80 shrink-0 overflow-y-auto border-l border-neutral-200 bg-white p-5">
                <template v-if="conversation.customer">
                    <div class="flex items-center gap-3">
                        <Avatar :name="conversation.customer.full_name" size="lg" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-neutral-900">{{ conversation.customer.full_name }}</p>
                            <p class="truncate text-xs text-neutral-500">{{ conversation.customer_username }}</p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-1.5 text-sm">
                        <p class="text-neutral-700"><span class="text-neutral-400">Email: </span>{{ conversation.customer.email ?? '—' }}</p>
                        <p class="text-neutral-700"><span class="text-neutral-400">Phone: </span>{{ conversation.customer.phone ?? '—' }}</p>
                    </div>

                    <div v-if="conversation.customer.notes" class="mt-3 rounded-md bg-neutral-50 p-2.5 text-xs text-neutral-600">
                        {{ conversation.customer.notes }}
                    </div>

                    <div class="mt-5 rounded-lg border border-neutral-200 p-3">
                        <h3 class="text-xs font-semibold text-neutral-500 uppercase">Business info</h3>
                        <div class="mt-2 space-y-1.5 text-sm text-neutral-700">
                            <p>{{ conversation.customer.total_orders_count }} previous orders</p>
                            <p>{{ formatMoney(conversation.customer.lifetime_spend) }} lifetime spend</p>
                            <p v-if="conversation.customer.current_open_order">
                                Open order:
                                <Link :href="route('orders.show', conversation.customer.current_open_order.id)" class="text-[var(--color-accent-600)] hover:underline">
                                    {{ conversation.customer.current_open_order.title }}
                                </Link>
                            </p>
                            <p v-if="conversation.customer.last_order_date">Last order {{ formatDate(conversation.customer.last_order_date) }}</p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        <Link
                            :href="route('orders.create', { customer_id: conversation.customer.id, conversation_id: conversation.id })"
                            class="flex items-center justify-center gap-1.5 rounded-md bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800"
                        >
                            <Plus :size="14" /> Create order
                        </Link>
                        <button
                            type="button"
                            class="flex w-full items-center justify-center gap-1.5 rounded-md border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                            @click="noteModalOpen = true"
                        >
                            <StickyNote :size="14" /> Add note
                        </button>
                        <button
                            type="button"
                            class="flex w-full items-center justify-center gap-1.5 rounded-md border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                            @click="followUpOpen = true"
                        >
                            <Bell :size="14" /> Set follow-up
                        </button>
                        <Link
                            :href="route('customers.show', conversation.customer.id)"
                            class="flex w-full items-center justify-center gap-1.5 rounded-md border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                        >
                            <UserRound :size="14" /> View customer
                        </Link>
                    </div>
                </template>

                <template v-else>
                    <div class="flex items-center gap-3">
                        <Avatar :name="conversation.customer_display_name ?? 'Unknown'" size="lg" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-neutral-900">{{ conversation.customer_display_name }}</p>
                            <p class="truncate text-xs text-neutral-500">{{ conversation.customer_username }}</p>
                        </div>
                    </div>

                    <Badge class="mt-3" color="#B45309" bg="#FEF3C7">Potential customer</Badge>

                    <p class="mt-3 text-sm text-neutral-500">
                        This person isn't a customer yet. Create a customer record to start tracking their orders and history.
                    </p>

                    <button
                        type="button"
                        class="mt-4 flex w-full items-center justify-center gap-1.5 rounded-md bg-neutral-900 px-3 py-2 text-sm font-medium text-white hover:bg-neutral-800"
                        @click="createCustomer"
                    >
                        <Plus :size="14" /> Create customer
                    </button>

                    <Link
                        :href="route('orders.create', { conversation_id: conversation.id })"
                        class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-md border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <Plus :size="14" /> Create order
                    </Link>
                </template>
            </div>
        </div>

        <FollowUpModal
            v-if="conversation"
            :show="followUpOpen"
            :followable-type="followableType"
            :followable-id="followableId"
            :default-note="`Follow up with ${conversation.customer?.full_name ?? conversation.customer_display_name}`"
            @close="followUpOpen = false"
        />

        <Modal :show="noteModalOpen" max-width="sm" @close="noteModalOpen = false">
            <form class="p-6" @submit.prevent="submitNote">
                <h2 class="text-base font-semibold text-neutral-900">Add a note</h2>
                <textarea
                    v-model="noteForm.note"
                    rows="4"
                    placeholder="Add a note about this customer…"
                    class="mt-3 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                />
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100" @click="noteModalOpen = false">
                        Cancel
                    </button>
                    <button
                        type="submit"
                        :disabled="noteForm.processing"
                        class="rounded-md bg-neutral-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-neutral-800 disabled:opacity-50"
                    >
                        Save note
                    </button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
