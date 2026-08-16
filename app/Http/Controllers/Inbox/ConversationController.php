<?php

namespace App\Http\Controllers\Inbox;

use App\Enums\ConversationStatus;
use App\Enums\MessageSenderType;
use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\Message;
use App\Services\Messaging\DTOs\OutboundAttachment;
use App\Services\Messaging\MessagingProviderInterface;
use App\Services\Messaging\MessagingProviderManager;
use App\Services\Messaging\OutboundMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function __construct(
        private readonly MessagingProviderManager $providers,
        private readonly OutboundMessageService $outboundMessages,
    ) {}

    public function index(): Response
    {
        return Inertia::render('Inbox/Index', [
            'conversations' => $this->conversationList(),
            'conversation' => null,
        ]);
    }

    public function show(Conversation $conversation): Response
    {
        $conversation->load(['channel', 'customer.orders', 'customer.appointments', 'customer.identities', 'messages.senderUser']);

        if ($conversation->unread_count > 0) {
            $conversation->update(['unread_count' => 0]);
        }

        return Inertia::render('Inbox/Index', [
            'conversations' => $this->conversationList(),
            'conversation' => $this->presentConversation($conversation),
        ]);
    }

    public function sendMessage(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate([
            'body' => 'nullable|string|max:5000',
            'attachment' => 'nullable|file|max:15360|mimes:jpg,jpeg,png,gif,webp,mp4,mov,pdf,doc,docx',
        ]);

        if (empty($data['body']) && ! $request->hasFile('attachment')) {
            return back()->with('error', 'Napiši sporočilo ali priloži datoteko.');
        }

        $conversation->loadMissing('channel');
        $channel = $conversation->channel;

        if (! $channel || ! $channel->isConnected()) {
            return back()->with('error', 'Ta pogovor nima povezanega kanala. Poveži Meta v nastavitvah.');
        }

        $provider = $this->providers->forChannel($channel);
        $message = null;

        if ($request->hasFile('attachment')) {
            $attachment = $this->storeOutboundAttachment($request->file('attachment'));
            $message = $this->sendSingle($provider, $channel, $conversation, null, $attachment);

            if ($message->status !== MessageStatus::Sent) {
                return back()->with('error', $message->failure_reason ?? 'Priloge ni bilo mogoče poslati.');
            }
        }

        if (! empty($data['body'])) {
            $message = $this->sendSingle($provider, $channel, $conversation, $data['body']);
        }

        if ($message?->status === MessageStatus::Sent) {
            return back();
        }

        return back()->with('error', $message?->failure_reason ?? 'Sporočila ni bilo mogoče poslati.');
    }

    private function sendSingle(
        MessagingProviderInterface $provider,
        Channel $channel,
        Conversation $conversation,
        ?string $text,
        ?OutboundAttachment $attachment = null,
    ): Message {
        return $this->outboundMessages->send($channel, $conversation, $text, $attachment, $provider);
    }

    /**
     * Outbound attachments are private customer-facing files — stored on
     * the 'local' (non-public) disk under a random, non-guessable name, and
     * only ever served back through the authorized
     * inbox.attachments.show / admin support-content routes. See
     * docs/data-security.md "Private message attachments".
     */
    private function storeOutboundAttachment(UploadedFile $file): OutboundAttachment
    {
        // UploadedFile::store() already generates a random filename (never
        // the original) and Laravel's 'mimes' validation rule (see
        // sendMessage()) already checks the file's actual content, not just
        // the browser-supplied Content-Type — both satisfy "don't trust the
        // browser MIME alone" without extra code here.
        $path = $file->store('inbox-attachments', 'local');

        $type = match (true) {
            str_starts_with((string) $file->getMimeType(), 'image/') => 'image',
            str_starts_with((string) $file->getMimeType(), 'video/') => 'video',
            default => 'file',
        };

        return new OutboundAttachment($type, $path, Storage::disk('local')->path($path));
    }

    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|string|in:'.implode(',', array_map(fn ($c) => $c->value, ConversationStatus::cases())),
        ]);

        $conversation->update(['status' => $data['status']]);

        return back();
    }

    public function createCustomer(Conversation $conversation): RedirectResponse
    {
        if ($conversation->customer_id) {
            return back();
        }

        $customer = Customer::create([
            'full_name' => $conversation->customer_display_name ?? 'Neznana stranka',
            'primary_channel_id' => $conversation->channel_id,
            'first_contacted_at' => $conversation->created_at,
            'last_interaction_at' => $conversation->last_message_at ?? $conversation->created_at,
        ]);

        // If this conversation came in through a real Meta webhook, an
        // identity already exists (keyed on the stable external PSID/IGSID)
        // — attach the new Customer to it instead of creating a duplicate,
        // so future messages from this same external identity resolve here.
        $identity = $conversation->external_conversation_id
            ? CustomerIdentity::where('workspace_id', $customer->workspace_id)
                ->where('channel_type', $conversation->channel->type->value)
                ->where('external_id', $conversation->external_conversation_id)
                ->first()
            : null;

        if ($identity) {
            $identity->update([
                'customer_id' => $customer->id,
                'username' => $identity->username ?? $conversation->customer_username,
                'display_name' => $identity->display_name ?? $conversation->customer_display_name,
            ]);
        } else {
            CustomerIdentity::create([
                'customer_id' => $customer->id,
                'workspace_id' => $customer->workspace_id,
                'channel_type' => $conversation->channel->type,
                'external_id' => $conversation->external_conversation_id,
                'username' => $conversation->customer_username,
                'display_name' => $conversation->customer_display_name,
            ]);
        }

        $conversation->update(['customer_id' => $customer->id]);

        ActivityLog::record('customer_created', "Stranka {$customer->full_name} je bila dodana iz pogovora", $customer);

        return back();
    }

    public function addNote(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate(['note' => 'required|string|max:2000']);

        if ($conversation->customer_id && $conversation->customer) {
            $existing = $conversation->customer->notes;
            $conversation->customer->update([
                'notes' => trim(($existing ? $existing."\n\n" : '').$data['note']),
            ]);
        }

        return back();
    }

    private function conversationList()
    {
        $avatars = $this->avatarMap();

        return Conversation::query()
            ->with(['channel', 'customer'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'customer_id' => $c->customer_id,
                'display_name' => $c->displayName(),
                'avatar_url' => $this->avatarUrlFor($c, $avatars),
                'channel' => $c->channel,
                'status' => $c->status,
                'last_message_preview' => $c->last_message_preview,
                'last_message_at' => $c->last_message_at,
                'unread_count' => $c->unread_count,
            ]);
    }

    /**
     * All known avatar URLs for the current workspace, keyed by
     * "{channel_type}|{external_id}" — cheap enough to load in one query
     * for a small business's identity list, avoids N+1 per conversation.
     */
    private function avatarMap(): array
    {
        return CustomerIdentity::withoutGlobalScopes()
            ->where('workspace_id', auth()->user()->current_workspace_id)
            ->get(['channel_type', 'external_id', 'metadata'])
            ->filter(fn ($i) => ! empty($i->metadata['avatar_url'] ?? null))
            ->mapWithKeys(fn ($i) => ["{$i->channel_type->value}|{$i->external_id}" => $i->metadata['avatar_url']])
            ->all();
    }

    private function avatarUrlFor(Conversation $conversation, array $avatars): ?string
    {
        if (! $conversation->channel || ! $conversation->external_conversation_id) {
            return null;
        }

        return $avatars["{$conversation->channel->type->value}|{$conversation->external_conversation_id}"] ?? null;
    }

    private function presentConversation(Conversation $conversation): array
    {
        $customer = $conversation->customer;
        $channel = $conversation->channel;

        [$canReply, $replyUnavailableReason] = $this->replyAvailability($conversation, $channel);

        return [
            'id' => $conversation->id,
            'status' => $conversation->status,
            'channel' => $channel,
            'customer_display_name' => $conversation->customer_display_name,
            'customer_username' => $conversation->customer_username,
            'avatar_url' => $this->avatarUrlFor($conversation, $this->avatarMap()),
            'messages' => $conversation->messages,
            'can_reply' => $canReply,
            'reply_unavailable_reason' => $replyUnavailableReason,
            'open_in_platform_url' => $channel && $channel->isConnected() && $conversation->external_conversation_id
                ? $this->providers->forChannel($channel)->conversationDeepLink($channel, $conversation)
                : null,
            'customer' => $customer ? [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'notes' => $customer->notes,
                'identities' => $customer->identities,
                'total_orders_count' => $customer->orders->count(),
                'lifetime_spend' => $customer->lifetimeSpend(),
                'open_orders_count' => $customer->openOrdersCount(),
                'current_open_order' => $customer->orders
                    ->filter(fn ($o) => ! in_array($o->status, ['completed', 'cancelled'], true))
                    ->sortByDesc('created_at')
                    ->first(),
                'last_order_date' => $customer->orders->max('created_at'),
                'previous_appointments_count' => $customer->previousAppointmentsCount(),
                'appointments_lifetime_spend' => $customer->appointmentsLifetimeSpend(),
                'no_show_appointments_count' => $customer->noShowAppointmentsCount(),
                'upcoming_appointment' => $customer->upcomingAppointment(),
                'last_appointment' => $customer->lastAppointment(),
            ] : null,
        ];
    }

    /**
     * @return array{0: bool, 1: ?string}
     */
    private function replyAvailability(Conversation $conversation, $channel): array
    {
        if (! $channel) {
            return [false, 'Ta pogovor nima povezanega kanala.'];
        }

        if (! $channel->isConnected()) {
            return [false, 'Kanal ni povezan. Poveži ga v nastavitvah.'];
        }

        $lastCustomerMessageAt = $conversation->messages
            ->where('sender_type', MessageSenderType::Customer)
            ->max('sent_at');

        if (! $lastCustomerMessageAt) {
            return [true, null];
        }

        $windowHours = config('meta.messaging_window_hours', 24);

        if (Carbon::parse($lastCustomerMessageAt)->addHours($windowHours)->isPast()) {
            $platform = $channel->type?->label() ?? 'platformo';

            return [false, "Odgovor prek {$platform} trenutno ni na voljo — okno za odgovor je poteklo."];
        }

        return [true, null];
    }
}
