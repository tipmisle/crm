<?php

namespace App\Services\Messaging;

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Integration;
use App\Services\Messaging\DTOs\NormalizedIncomingMessage;
use App\Services\Messaging\DTOs\OutboundAttachment;
use App\Services\Messaging\DTOs\SendMessageResult;

/**
 * Common contract every messaging provider (Meta today, TikTok/Gmail/
 * Outlook/WhatsApp later) must satisfy. The Inbox and Settings layers only
 * ever talk to this interface — never to a provider's raw API.
 */
interface MessagingProviderInterface
{
    /**
     * Build the URL the user is redirected to in order to authorize the app.
     */
    public function getAuthorizationUrl(string $state): string;

    /**
     * Exchange an OAuth callback `code` for a long-lived token and persist
     * (or update) the workspace's Integration record for this provider.
     */
    public function handleOAuthCallback(string $workspaceId, string $code): Integration;

    /**
     * List the accounts/pages this integration's token can access, so the
     * user can choose which ones to turn into Channels.
     *
     * @return \App\Services\Messaging\DTOs\DiscoveredAccount[]
     */
    public function listConnectableAccounts(Integration $integration): array;

    /**
     * Subscribe the given channel's external account to the provider's
     * messaging webhooks.
     */
    public function subscribeWebhooks(Channel $channel): bool;

    /**
     * Unsubscribe the given channel from provider webhooks (used on disconnect).
     */
    public function unsubscribeWebhooks(Channel $channel): bool;

    /**
     * Send a single outbound message: either $text or $attachment (Meta's
     * Send API only accepts one or the other per call). If a caller needs
     * to send both a file and a caption, that's two separate calls — one
     * per resulting Message row, same as how they'd arrive as two messages
     * on the platform itself.
     */
    public function sendMessage(Channel $channel, Conversation $conversation, ?string $text, ?OutboundAttachment $attachment = null): SendMessageResult;

    /**
     * Verify that an incoming webhook request actually came from this provider.
     */
    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool;

    /**
     * Turn a raw webhook payload into zero or more normalized messages.
     *
     * @return NormalizedIncomingMessage[]
     */
    public function normalizeWebhookPayload(array $payload): array;

    /**
     * Build a best-effort deep link back to the original conversation on
     * the provider's own platform (used for the "Open in Instagram" action).
     */
    public function conversationDeepLink(Channel $channel, Conversation $conversation): ?string;

    /**
     * Best-effort lookup of a sender's display name/username, used to
     * enrich a conversation before a Customer record exists. Callers should
     * treat failures as non-fatal — return an empty array on error.
     *
     * @return array{name?: ?string, username?: ?string, avatar_url?: ?string}
     */
    public function fetchSenderProfile(Channel $channel, string $externalId): array;
}
