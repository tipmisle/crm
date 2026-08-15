<script setup lang="ts">
import { ref, computed, nextTick, watch } from 'vue';
import { Head, Link, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import type { PageProps } from '@/types';
import AppLayout from '@/Layouts/AppLayout.vue';
import Avatar from '@/Components/Avatar.vue';
import Badge from '@/Components/Badge.vue';
import ChannelIcon from '@/Components/ChannelIcon.vue';
import MessageBubble from '@/Components/MessageBubble.vue';
import EmptyState from '@/Components/EmptyState.vue';
import FollowUpModal from '@/Components/FollowUpModal.vue';
import Modal from '@/Components/Modal.vue';
import { relativeTime, formatMoney, formatDate, formatTime } from '@/lib/format';
import { CONVERSATION_STATUS_META } from '@/lib/statuses';
import {
    Send,
    Plus,
    Bell,
    StickyNote,
    UserRound,
    Inbox as InboxIcon,
    ExternalLink,
    Paperclip,
    Loader2,
    X,
    CalendarPlus,
    ArrowLeft,
    Info,
} from 'lucide-vue-next';
import type { Channel, ConversationStatus, Message } from '@/types/models';

interface ConversationListItem {
    id: number;
    customer_id: number | null;
    display_name: string;
    avatar_url: string | null;
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
    previous_appointments_count: number;
    appointments_lifetime_spend: number;
    no_show_appointments_count: number;
    upcoming_appointment: { id: number; service_name: string; appointment_date: string; start_time: string } | null;
    last_appointment: { id: number; service_name: string; appointment_date: string } | null;
}

interface ConversationDetail {
    id: number;
    status: ConversationStatus;
    channel: Channel;
    customer_display_name: string | null;
    customer_username: string | null;
    avatar_url: string | null;
    messages: Message[];
    customer: CustomerContext | null;
    can_reply: boolean;
    reply_unavailable_reason: string | null;
    open_in_platform_url: string | null;
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

// New messages arrive via a webhook in the background. A Reverb broadcast
// (see App\Events\InboxMessageReceived) tells any open Inbox tab in this
// workspace to refresh — no payload is trusted from the socket itself, it
// only triggers a normal authorized Inertia reload of the same data.
let previousMessageCount = props.conversation?.messages.length ?? 0;

function refreshInbox() {
    router.reload({
        only: ['conversations', 'conversation'],
        onFinish: () => {
            const currentCount = props.conversation?.messages.length ?? 0;

            if (currentCount > previousMessageCount) {
                scrollToBottom();
            }

            previousMessageCount = currentCount;
        },
    });
}

const page = usePage<PageProps>();
const workspaceId = computed(() => page.props.workspace?.id);
const ordersEnabled = computed(() => page.props.workspace?.orders_enabled ?? true);
const appointmentsEnabled = computed(() => page.props.workspace?.appointments_enabled ?? false);

useEcho(`workspace.${workspaceId.value}.inbox`, 'message.received', refreshInbox);

// Slow fallback in case the websocket connection drops silently (e.g. sleep/
// network change) — Echo reconnects automatically, but this is a cheap safety
// net so the Inbox never goes stale for long even if it doesn't.
usePoll(30000, { only: ['conversations', 'conversation'] });

// A picked attachment is only *staged* (previewed next to the input) until
// the user clicks "Pošlji" — sending itself uses a separate form/processing
// state from the text reply, so it never blocks typing/sending text.
const attachmentInput = ref<HTMLInputElement | null>(null);
const attachmentForm = useForm<{ attachment: File | null }>({ attachment: null });
const stagedAttachmentPreviewUrl = ref<string | null>(null);
const stagedAttachmentIsImage = ref(false);
const pendingAttachmentPreviewUrl = ref<string | null>(null);
const pendingAttachmentIsImage = ref(false);

function pickAttachment() {
    attachmentInput.value?.click();
}

function onAttachmentSelected(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (!file) return;

    attachmentForm.attachment = file;
    stagedAttachmentIsImage.value = file.type.startsWith('image/');
    if (stagedAttachmentPreviewUrl.value) URL.revokeObjectURL(stagedAttachmentPreviewUrl.value);
    stagedAttachmentPreviewUrl.value = stagedAttachmentIsImage.value ? URL.createObjectURL(file) : null;
}

function clearStagedAttachment() {
    attachmentForm.attachment = null;
    if (attachmentInput.value) attachmentInput.value.value = '';
    if (stagedAttachmentPreviewUrl.value) URL.revokeObjectURL(stagedAttachmentPreviewUrl.value);
    stagedAttachmentPreviewUrl.value = null;
}

function sendAttachment() {
    if (!props.conversation || !attachmentForm.attachment) return;

    pendingAttachmentIsImage.value = stagedAttachmentIsImage.value;
    pendingAttachmentPreviewUrl.value = stagedAttachmentPreviewUrl.value;
    stagedAttachmentPreviewUrl.value = null; // ownership moves to the pending (in-thread) preview
    scrollToBottom();

    attachmentForm.post(route('inbox.messages.store', props.conversation.id), {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            attachmentForm.reset();
            if (attachmentInput.value) attachmentInput.value.value = '';
            if (pendingAttachmentPreviewUrl.value) URL.revokeObjectURL(pendingAttachmentPreviewUrl.value);
            pendingAttachmentPreviewUrl.value = null;
            scrollToBottom();
        },
    });
}

function sendMessage() {
    if (!props.conversation || (!messageForm.body.trim() && !attachmentForm.attachment)) return;

    if (attachmentForm.attachment) sendAttachment();

    if (messageForm.body.trim()) {
        messageForm.post(route('inbox.messages.store', props.conversation.id), {
            preserveScroll: true,
            onSuccess: () => {
                messageForm.reset();
                scrollToBottom();
            },
        });
    }
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

// Below lg, the three panes (list / thread / customer info) become three
// separate full-width screens — a conversation list, a chat, and an info
// drawer — the way a mobile messaging app works, instead of three squeezed
// columns. customerPanelOpen only matters below lg; at lg+ the info panel
// is always visible as its own column regardless of this flag.
const customerPanelOpen = ref(false);
watch(() => props.conversation?.id, () => (customerPanelOpen.value = false));

const followableId = computed(() => props.conversation?.customer?.id ?? props.conversation?.id ?? 0);
const followableType = computed(() =>
    props.conversation?.customer ? 'App\\Models\\Customer' : 'App\\Models\\Conversation',
);
</script>

<template>
    <Head title="Prejeta pošta" />

    <AppLayout>
        <template #header>
            <h1 class="text-sm font-semibold text-neutral-900">Prejeta pošta</h1>
        </template>

        <div class="flex h-[calc(100vh-3.5rem)]">
            <div
                class="w-full shrink-0 overflow-y-auto border-r border-neutral-200 bg-white lg:w-80"
                :class="conversation ? 'hidden lg:block' : 'block'"
            >
                <Link
                    v-for="c in conversations"
                    :key="c.id"
                    :href="route('inbox.show', c.id)"
                    class="flex gap-3 border-b border-neutral-100 px-4 py-3 hover:bg-neutral-50"
                    :class="conversation?.id === c.id ? 'bg-[var(--color-accent-50)]' : ''"
                >
                    <div class="relative shrink-0">
                        <Avatar :name="c.display_name" :src="c.avatar_url" size="md" />
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
                            <span v-if="c.unread_count" class="flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-semibold text-white">
                                {{ c.unread_count }}
                            </span>
                        </div>
                    </div>
                </Link>

                <EmptyState v-if="!conversations.length" title="Ni še pogovorov" />
            </div>

            <div class="flex-1 flex-col" :class="conversation ? 'flex' : 'hidden lg:flex'">
                <template v-if="conversation">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-neutral-200 bg-white px-3 py-3 sm:px-5">
                        <div class="flex min-w-0 items-center gap-2">
                            <Link
                                :href="route('inbox.index')"
                                class="-ml-1 shrink-0 rounded-md p-1 text-neutral-500 hover:bg-neutral-100 lg:hidden"
                            >
                                <ArrowLeft :size="18" />
                            </Link>
                            <ChannelIcon :type="conversation.channel.type" size="md" />
                            <span class="truncate text-sm font-medium text-neutral-900">
                                {{ conversation.customer?.full_name ?? conversation.customer_display_name }}
                            </span>
                            <span class="hidden shrink-0 text-xs text-neutral-400 sm:inline">{{ conversation.customer_username }}</span>
                            <a
                                v-if="conversation.open_in_platform_url"
                                :href="conversation.open_in_platform_url"
                                target="_blank"
                                rel="noopener"
                                title="Odpri v izvorni platformi"
                                class="hidden shrink-0 text-neutral-300 hover:text-neutral-500 sm:inline"
                            >
                                <ExternalLink :size="13" />
                            </a>
                        </div>

                        <div class="ml-auto flex shrink-0 items-center gap-2">
                            <select
                                :value="conversation.status"
                                class="rounded-md border border-neutral-200 px-2.5 py-1.5 text-xs font-medium text-neutral-600 outline-none"
                                @change="updateStatus(($event.target as HTMLSelectElement).value)"
                            >
                                <option v-for="(meta, key) in CONVERSATION_STATUS_META" :key="key" :value="key">{{ meta.label }}</option>
                            </select>
                            <button
                                type="button"
                                class="rounded-md p-1.5 text-neutral-500 hover:bg-neutral-100 lg:hidden"
                                title="Podrobnosti o stranki"
                                @click="customerPanelOpen = true"
                            >
                                <Info :size="18" />
                            </button>
                        </div>
                    </div>

                    <div ref="threadEl" class="flex-1 space-y-3 overflow-y-auto px-3 py-4 sm:px-5 sm:py-5">
                        <MessageBubble v-for="m in conversation.messages" :key="m.id" :message="m" />

                        <div v-if="attachmentForm.processing" class="flex justify-end">
                            <div class="max-w-md space-y-2 rounded-2xl bg-[var(--color-accent-500)]/90 px-4 py-2.5">
                                <img
                                    v-if="pendingAttachmentIsImage && pendingAttachmentPreviewUrl"
                                    :src="pendingAttachmentPreviewUrl"
                                    class="max-h-64 rounded-lg opacity-70"
                                />
                                <p v-else class="text-sm text-white/90">📎 {{ attachmentForm.attachment?.name }}</p>
                                <p class="flex items-center gap-1.5 text-[11px] text-white/80">
                                    <Loader2 :size="11" class="animate-spin" /> Pošiljam …
                                </p>
                            </div>
                        </div>
                    </div>

                    <form
                        v-if="conversation.can_reply"
                        class="border-t border-neutral-200 bg-white p-4"
                        @submit.prevent="sendMessage"
                    >
                        <div
                            v-if="attachmentForm.attachment && !attachmentForm.processing"
                            class="mb-2 flex items-center gap-2 rounded-md bg-neutral-50 px-2.5 py-1.5 text-xs text-neutral-600"
                        >
                            <img v-if="stagedAttachmentPreviewUrl" :src="stagedAttachmentPreviewUrl" class="h-8 w-8 rounded object-cover" />
                            <span class="flex-1 truncate">{{ attachmentForm.attachment.name }}</span>
                            <button type="button" class="text-neutral-400 hover:text-neutral-700" @click="clearStagedAttachment">
                                <X :size="14" />
                            </button>
                        </div>

                        <div class="flex items-center gap-2">
                        <input
                            ref="attachmentInput"
                            type="file"
                            accept="image/*,video/*,.pdf,.doc,.docx"
                            class="hidden"
                            @change="onAttachmentSelected"
                        />
                        <button
                            type="button"
                            title="Priloži datoteko"
                            class="flex shrink-0 items-center justify-center rounded-md border border-neutral-200 p-2 text-neutral-500 hover:bg-neutral-50"
                            @click="pickAttachment"
                        >
                            <Paperclip :size="16" />
                        </button>
                        <input
                            v-model="messageForm.body"
                            type="text"
                            placeholder="Napiši odgovor …"
                            class="flex-1 rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                        />
                        <button
                            type="submit"
                            :disabled="messageForm.processing || (!messageForm.body.trim() && !attachmentForm.attachment)"
                            class="flex min-w-24 items-center justify-center gap-1.5 rounded-md bg-[var(--color-ink-900)] px-3 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                        >
                            <Loader2 v-if="messageForm.processing" :size="14" class="animate-spin" />
                            <Send v-else :size="14" />
                            {{ messageForm.processing ? 'Pošiljam …' : 'Pošlji' }}
                        </button>
                        </div>
                    </form>
                    <div v-else class="flex items-center justify-between gap-3 border-t border-neutral-200 bg-neutral-50 p-4">
                        <p class="text-sm text-neutral-500">
                            {{ conversation.reply_unavailable_reason ?? 'Odgovor trenutno ni na voljo.' }}
                        </p>
                        <a
                            v-if="conversation.open_in_platform_url"
                            :href="conversation.open_in_platform_url"
                            target="_blank"
                            rel="noopener"
                            class="flex shrink-0 items-center gap-1.5 rounded-md border border-neutral-200 bg-white px-3 py-1.5 text-xs font-medium text-neutral-600 hover:bg-neutral-100"
                        >
                            <ExternalLink :size="12" /> Odpri v izvorni platformi
                        </a>
                    </div>
                </template>

                <EmptyState v-else title="Izberi pogovor" description="Izberi pogovor s seznama, da vidiš potek sporočil.">
                    <template #icon><InboxIcon :size="28" /></template>
                </EmptyState>
            </div>

            <!-- Below lg this is a full-screen drawer over the chat (closed by
                 default); at lg+ it's the permanent third column. -->
            <Transition
                enter-active-class="transition duration-150 ease-out"
                enter-from-class="opacity-0"
                leave-active-class="transition duration-100 ease-in"
                leave-to-class="opacity-0"
            >
                <div v-if="conversation && customerPanelOpen" class="fixed inset-0 z-40 bg-[var(--color-ink-900)]/50 lg:hidden" @click="customerPanelOpen = false" />
            </Transition>

            <div
                v-if="conversation"
                class="w-80 shrink-0 overflow-y-auto border-l border-neutral-200 bg-white p-5 transition-transform duration-200 ease-out lg:static lg:translate-x-0"
                :class="
                    customerPanelOpen
                        ? 'fixed inset-y-0 right-0 z-40 max-w-[85vw] translate-x-0'
                        : 'fixed inset-y-0 right-0 z-40 max-w-[85vw] translate-x-full lg:translate-x-0'
                "
            >
                <button
                    type="button"
                    class="mb-3 flex items-center gap-1.5 rounded-md px-1 py-1 text-sm font-medium text-neutral-500 hover:text-neutral-700 lg:hidden"
                    @click="customerPanelOpen = false"
                >
                    <X :size="16" /> Zapri
                </button>

                <template v-if="conversation.customer">
                    <div class="flex items-center gap-3">
                        <Avatar :name="conversation.customer.full_name" :src="conversation.avatar_url" size="lg" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-neutral-900">{{ conversation.customer.full_name }}</p>
                            <p class="truncate text-xs text-neutral-500">{{ conversation.customer_username }}</p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-1.5 text-sm">
                        <p class="text-neutral-700"><span class="text-neutral-400">E-pošta: </span>{{ conversation.customer.email ?? '—' }}</p>
                        <p class="text-neutral-700"><span class="text-neutral-400">Telefon: </span>{{ conversation.customer.phone ?? '—' }}</p>
                    </div>

                    <div v-if="conversation.customer.notes" class="mt-3 rounded-md bg-neutral-50 p-2.5 text-xs text-neutral-600">
                        {{ conversation.customer.notes }}
                    </div>

                    <div v-if="ordersEnabled" class="mt-5 rounded-lg border border-neutral-200 p-3">
                        <h3 class="text-xs font-semibold text-neutral-500 uppercase">Poslovni podatki</h3>
                        <div class="mt-2 space-y-1.5 text-sm text-neutral-700">
                            <p>Prejšnjih naročil: {{ conversation.customer.total_orders_count }}</p>
                            <p>Skupno porabljeno: {{ formatMoney(conversation.customer.lifetime_spend) }}</p>
                            <p v-if="conversation.customer.current_open_order">
                                Odprto naročilo:
                                <Link :href="route('orders.show', conversation.customer.current_open_order.id)" class="text-[var(--color-accent-600)] hover:underline">
                                    {{ conversation.customer.current_open_order.title }}
                                </Link>
                            </p>
                            <p v-if="conversation.customer.last_order_date">Zadnje naročilo {{ formatDate(conversation.customer.last_order_date) }}</p>
                        </div>
                    </div>

                    <div v-if="appointmentsEnabled" class="mt-5 rounded-lg border border-neutral-200 p-3">
                        <h3 class="text-xs font-semibold text-neutral-500 uppercase">Termini</h3>
                        <div class="mt-2 space-y-1.5 text-sm text-neutral-700">
                            <p>Prejšnjih terminov: {{ conversation.customer.previous_appointments_count }}</p>
                            <p>Skupno porabljeno: {{ formatMoney(conversation.customer.appointments_lifetime_spend) }}</p>
                            <p v-if="conversation.customer.no_show_appointments_count">Ni se zglasil/a: {{ conversation.customer.no_show_appointments_count }}×</p>
                            <p v-if="conversation.customer.upcoming_appointment">
                                Naslednji termin:
                                <Link :href="route('appointments.show', conversation.customer.upcoming_appointment.id)" class="text-[var(--color-accent-600)] hover:underline">
                                    {{ conversation.customer.upcoming_appointment.service_name }}
                                </Link>
                                — {{ formatDate(conversation.customer.upcoming_appointment.appointment_date) }} ·
                                {{ formatTime(`2000-01-01T${conversation.customer.upcoming_appointment.start_time}`) }}
                            </p>
                            <p v-if="conversation.customer.last_appointment">
                                Zadnji obisk: {{ formatDate(conversation.customer.last_appointment.appointment_date) }}
                            </p>
                        </div>
                    </div>

                    <div class="mt-4 space-y-2">
                        <Link
                            v-if="ordersEnabled"
                            :href="route('orders.create', { customer_id: conversation.customer.id, conversation_id: conversation.id })"
                            class="flex items-center justify-center gap-1.5 rounded-md bg-[var(--color-ink-900)] px-3 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)]"
                        >
                            <Plus :size="14" /> Ustvari naročilo
                        </Link>
                        <Link
                            v-if="appointmentsEnabled"
                            :href="route('appointments.create', { customer_id: conversation.customer.id, conversation_id: conversation.id })"
                            class="flex items-center justify-center gap-1.5 rounded-md px-3 py-2 text-sm font-medium text-white hover:opacity-90"
                            :class="ordersEnabled ? 'bg-neutral-700' : 'bg-[var(--color-ink-900)] hover:bg-[var(--color-ink-800)]'"
                        >
                            <CalendarPlus :size="14" /> Rezerviraj termin
                        </Link>
                        <button
                            type="button"
                            class="flex w-full items-center justify-center gap-1.5 rounded-md border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                            @click="noteModalOpen = true"
                        >
                            <StickyNote :size="14" /> Dodaj opombo
                        </button>
                        <button
                            type="button"
                            class="flex w-full items-center justify-center gap-1.5 rounded-md border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                            @click="followUpOpen = true"
                        >
                            <Bell :size="14" /> Nastavi opomnik
                        </button>
                        <Link
                            :href="route('customers.show', conversation.customer.id)"
                            class="flex w-full items-center justify-center gap-1.5 rounded-md border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                        >
                            <UserRound :size="14" /> Ogled stranke
                        </Link>
                    </div>
                </template>

                <template v-else>
                    <div class="flex items-center gap-3">
                        <Avatar :name="conversation.customer_display_name ?? 'Neznano'" :src="conversation.avatar_url" size="lg" />
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-neutral-900">{{ conversation.customer_display_name }}</p>
                            <p class="truncate text-xs text-neutral-500">{{ conversation.customer_username }}</p>
                        </div>
                    </div>

                    <Badge class="mt-3" color="#B45309" bg="#FEF3C7">Morebitna stranka</Badge>

                    <p class="mt-3 text-sm text-neutral-500">
                        Ta oseba še ni stranka. Ustvari zapis stranke, da začneš slediti njenim naročilom in zgodovini.
                    </p>

                    <button
                        type="button"
                        class="mt-4 flex w-full items-center justify-center gap-1.5 rounded-md bg-[var(--color-ink-900)] px-3 py-2 text-sm font-medium text-white hover:bg-[var(--color-ink-800)]"
                        @click="createCustomer"
                    >
                        <Plus :size="14" /> Ustvari stranko
                    </button>

                    <Link
                        v-if="ordersEnabled"
                        :href="route('orders.create', { conversation_id: conversation.id })"
                        class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-md border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <Plus :size="14" /> Ustvari naročilo
                    </Link>
                    <Link
                        v-if="appointmentsEnabled"
                        :href="route('appointments.create', { conversation_id: conversation.id })"
                        class="mt-2 flex w-full items-center justify-center gap-1.5 rounded-md border border-neutral-200 px-3 py-2 text-sm font-medium text-neutral-600 hover:bg-neutral-50"
                    >
                        <CalendarPlus :size="14" /> Rezerviraj termin
                    </Link>
                </template>
            </div>
        </div>

        <FollowUpModal
            v-if="conversation"
            :show="followUpOpen"
            :followable-type="followableType"
            :followable-id="followableId"
            :default-note="`Opomnik za ${conversation.customer?.full_name ?? conversation.customer_display_name}`"
            @close="followUpOpen = false"
        />

        <Modal :show="noteModalOpen" max-width="sm" @close="noteModalOpen = false">
            <form class="p-6" @submit.prevent="submitNote">
                <h2 class="text-base font-semibold text-neutral-900">Dodaj opombo</h2>
                <textarea
                    v-model="noteForm.note"
                    rows="4"
                    placeholder="Dodaj opombo o tej stranki …"
                    class="mt-3 w-full rounded-md border border-neutral-200 px-3 py-2 text-sm outline-none focus:border-neutral-400"
                />
                <div class="mt-4 flex justify-end gap-2">
                    <button type="button" class="rounded-md px-3 py-1.5 text-sm font-medium text-neutral-600 hover:bg-neutral-100" @click="noteModalOpen = false">
                        Prekliči
                    </button>
                    <button
                        type="submit"
                        :disabled="noteForm.processing"
                        class="rounded-md bg-[var(--color-ink-900)] px-3 py-1.5 text-sm font-medium text-white hover:bg-[var(--color-ink-800)] disabled:opacity-50"
                    >
                        Shrani opombo
                    </button>
                </div>
            </form>
        </Modal>
    </AppLayout>
</template>
