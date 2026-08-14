<?php

namespace App\Services\Messaging;

use App\Enums\MessageSenderType;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Events\InboxMessageReceived;
use App\Jobs\FetchCustomerIdentityProfile;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\CustomerIdentity;
use App\Models\Message;
use App\Services\Messaging\DTOs\NormalizedIncomingMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Turns a NormalizedIncomingMessage into the existing domain graph:
 * Channel -> CustomerIdentity -> Customer (if known) -> Conversation -> Message.
 *
 * Safe to run more than once for the same message (Meta retries webhook
 * deliveries) — ingestion is keyed on external_message_id.
 */
class MessageIngestionService
{
    public function __construct(private readonly MessagingProviderManager $providers) {}

    public function ingest(NormalizedIncomingMessage $normalized): ?Message
    {
        $channel = Channel::withoutGlobalScopes()
            ->where('external_account_id', $normalized->channelExternalId)
            ->first();

        if (! $channel) {
            Log::warning('messaging.ingest.unknown_channel', [
                'provider' => $normalized->provider,
                'channel_external_id' => $normalized->channelExternalId,
            ]);

            return null;
        }

        if (! $channel->isConnected()) {
            // Disconnect stops future processing even if Meta delivers a
            // webhook before the unsubscribe call/propagation completes.
            Log::info('messaging.ingest.channel_disconnected', ['channel_id' => $channel->id]);

            return null;
        }

        if (! $normalized->messageExternalId) {
            Log::warning('messaging.ingest.missing_external_id', ['channel_id' => $channel->id]);

            return null;
        }

        // external_message_id is globally unique — if it's already stored,
        // this is a Meta webhook retry and we must not insert a duplicate.
        $existing = Message::where('external_message_id', $normalized->messageExternalId)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($normalized, $channel) {
            $identity = $this->resolveIdentity($channel, $normalized);

            $conversation = Conversation::withoutGlobalScopes()
                ->where('channel_id', $channel->id)
                ->where('external_conversation_id', $normalized->conversationExternalId)
                ->first();

            if (! $conversation) {
                $conversation = Conversation::create([
                    'workspace_id' => $channel->workspace_id,
                    'channel_id' => $channel->id,
                    'customer_id' => $identity->customer_id,
                    'external_conversation_id' => $normalized->conversationExternalId,
                    'customer_display_name' => $identity->display_name,
                    'customer_username' => $identity->username,
                    'status' => 'new_enquiry',
                    'unread_count' => 0,
                ]);
            } elseif (! $conversation->customer_id && $identity->customer_id) {
                $conversation->customer_id = $identity->customer_id;
            }

            $message = null;

            try {
                $message = $conversation->messages()->create([
                    'sender_type' => MessageSenderType::Customer,
                    'external_message_id' => $normalized->messageExternalId,
                    'body' => $normalized->text,
                    'message_type' => empty($normalized->attachments) ? MessageType::Text : MessageType::Image,
                    'status' => MessageStatus::Delivered,
                    'metadata' => empty($normalized->attachments) ? null : ['attachments' => $normalized->attachments],
                    'sent_at' => $normalized->sentAt,
                ]);
            } catch (\Illuminate\Database\UniqueConstraintViolationException) {
                // Lost a race with a concurrent webhook retry — the message
                // already exists, fetch and return it instead of failing.
                return Message::where('external_message_id', $normalized->messageExternalId)->first();
            }

            $conversation->last_message_preview = Str::limit($normalized->text ?? '📎 Priloga', 120);
            $conversation->last_message_at = $normalized->sentAt;
            $conversation->unread_count = $conversation->unread_count + 1;
            $conversation->save();

            if ($identity->customer) {
                $identity->customer->update(['last_interaction_at' => $normalized->sentAt]);
            }

            broadcast(new InboxMessageReceived($channel->workspace_id, $conversation->id));

            return $message;
        });
    }

    private function resolveIdentity(Channel $channel, NormalizedIncomingMessage $normalized): CustomerIdentity
    {
        $identity = CustomerIdentity::withoutGlobalScopes()
            ->where('workspace_id', $channel->workspace_id)
            ->where('channel_type', $channel->type->value)
            ->where('external_id', $normalized->senderExternalId)
            ->first();

        if ($identity) {
            return $identity;
        }

        // Only what the webhook payload itself gave us — no outbound network
        // calls here. The Graph API profile lookup is slow and must not
        // block message ingestion/broadcast, so it runs as a follow-up job.
        $identity = CustomerIdentity::create([
            'customer_id' => null,
            'workspace_id' => $channel->workspace_id,
            'channel_type' => $channel->type->value,
            'external_id' => $normalized->senderExternalId,
            'username' => $normalized->senderUsername,
            'display_name' => $normalized->senderName,
            'metadata' => null,
        ]);

        FetchCustomerIdentityProfile::dispatch($channel->id, $identity->id);

        return $identity;
    }
}
