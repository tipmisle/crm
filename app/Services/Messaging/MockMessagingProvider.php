<?php

namespace App\Services\Messaging;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Integration;
use App\Services\Messaging\DTOs\OutboundAttachment;
use App\Services\Messaging\DTOs\SendMessageResult;
use Illuminate\Support\Str;

/**
 * Messaging provider used for demo (ephemeral) workspaces. It never makes
 * an outbound HTTP call to any real platform — outbound "sends" only ever
 * produce a locally stored Message row, via SendMessageResult::success().
 * All other provider actions (OAuth, webhooks, deep links) are demo
 * workspaces don't reach in practice, but are implemented as safe no-ops to
 * satisfy the interface.
 */
class MockMessagingProvider implements MessagingProviderInterface
{
    public function getAuthorizationUrl(string $state): string
    {
        return '#';
    }

    public function handleOAuthCallback(string $workspaceId, string $code): Integration
    {
        throw new \RuntimeException('Demo workspaces do not support connecting real integrations.');
    }

    public function listConnectableAccounts(Integration $integration): array
    {
        return [];
    }

    public function subscribeWebhooks(Channel $channel): bool
    {
        return true;
    }

    public function unsubscribeWebhooks(Channel $channel): bool
    {
        return true;
    }

    public function sendMessage(Channel $channel, Conversation $conversation, ?string $text, ?OutboundAttachment $attachment = null): SendMessageResult
    {
        return SendMessageResult::success('demo_'.Str::uuid());
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        return false;
    }

    public function normalizeWebhookPayload(array $payload): array
    {
        return [];
    }

    public function conversationDeepLink(Channel $channel, Conversation $conversation): ?string
    {
        return null;
    }

    public function fetchSenderProfile(Channel $channel, string $externalId): array
    {
        return [];
    }
}
