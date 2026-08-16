<?php

namespace App\Services\Messaging;

use App\Enums\MessageSenderType;
use App\Enums\MessageStatus;
use App\Enums\MessageType;
use App\Events\InboxMessageReceived;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Services\Messaging\DTOs\OutboundAttachment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Sends a single outbound message (text and/or one attachment) through a
 * channel's messaging provider and records the resulting Message row.
 * Extracted from Inbox\ConversationController so the invoicing module
 * (Invoicing\SalesDocumentSendController / SalesDocumentReminderController)
 * can send through the exact same path — no parallel implementation.
 */
class OutboundMessageService
{
    public function send(
        Channel $channel,
        Conversation $conversation,
        ?string $text,
        ?OutboundAttachment $attachment,
        MessagingProviderInterface $provider,
    ): Message {
        $message = $conversation->messages()->create([
            'sender_type' => MessageSenderType::Business,
            'body' => $text,
            'message_type' => $attachment ? MessageType::Image : MessageType::Text,
            'status' => MessageStatus::Pending,
            'metadata' => $attachment ? ['attachments' => [$attachment->toArray()]] : null,
            'sent_at' => Carbon::now(),
        ]);

        $result = $provider->sendMessage($channel, $conversation, $text, $attachment);

        if ($result->success) {
            $message->update([
                'status' => MessageStatus::Sent,
                'external_message_id' => $result->externalMessageId,
            ]);

            $conversation->update([
                'last_message_preview' => $text ? Str::limit($text, 120) : '📎 Priloga',
                'last_message_at' => $message->sent_at,
            ]);

            broadcast(new InboxMessageReceived($conversation->workspace_id, $conversation->id))->toOthers();

            return $message;
        }

        $message->update([
            'status' => MessageStatus::Failed,
            'failed_at' => Carbon::now(),
            'failure_reason' => $result->errorMessage,
        ]);

        return $message;
    }
}
