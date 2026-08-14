<?php

namespace App\Services\Messaging\DTOs;

use Illuminate\Support\Carbon;

/**
 * Provider-agnostic shape for an inbound message, regardless of whether it
 * came from Instagram, Facebook Messenger, or (later) another provider.
 */
final class NormalizedIncomingMessage
{
    public function __construct(
        public readonly string $provider,
        public readonly string $channelExternalId,
        public readonly string $conversationExternalId,
        public readonly string $senderExternalId,
        public readonly ?string $senderName,
        public readonly ?string $senderUsername,
        public readonly ?string $messageExternalId,
        public readonly ?string $text,
        public readonly array $attachments,
        public readonly Carbon $sentAt,
        public readonly array $rawMetadata,
    ) {}
}
