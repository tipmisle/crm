<?php

namespace App\Services\Messaging;

use App\Enums\ChannelType;
use App\Enums\IntegrationProvider;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Integration;
use App\Services\Messaging\DTOs\DiscoveredAccount;
use App\Services\Messaging\DTOs\NormalizedIncomingMessage;
use App\Services\Messaging\DTOs\OutboundAttachment;
use App\Services\Messaging\DTOs\SendMessageResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Talks to the Meta Graph API for Instagram Professional DMs and Facebook
 * Page Messenger conversations. Both surfaces are unified under Meta's
 * "Facebook Login for Business" + Messenger Platform webhook model, so one
 * provider class serves both ChannelType::Instagram and
 * ChannelType::FacebookMessenger.
 */
class MetaMessagingProvider implements MessagingProviderInterface
{
    private string $graphVersion;

    private string $baseUrl;

    public function __construct()
    {
        $this->graphVersion = config('meta.graph_version');
        $this->baseUrl = "https://graph.facebook.com/{$this->graphVersion}";
    }

    public function getAuthorizationUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => config('meta.app_id'),
            'redirect_uri' => config('meta.redirect_uri'),
            'state' => $state,
            'response_type' => 'code',
            'scope' => implode(',', config('meta.scopes')),
        ]);

        return "https://www.facebook.com/{$this->graphVersion}/dialog/oauth?{$query}";
    }

    public function handleOAuthCallback(string $workspaceId, string $code): Integration
    {
        $shortLived = Http::get("{$this->baseUrl}/oauth/access_token", [
            'client_id' => config('meta.app_id'),
            'client_secret' => config('meta.app_secret'),
            'redirect_uri' => config('meta.redirect_uri'),
            'code' => $code,
        ])->throw()->json();

        $longLived = Http::get("{$this->baseUrl}/oauth/access_token", [
            'grant_type' => 'fb_exchange_token',
            'client_id' => config('meta.app_id'),
            'client_secret' => config('meta.app_secret'),
            'fb_exchange_token' => $shortLived['access_token'],
        ])->throw()->json();

        $accessToken = $longLived['access_token'];
        $expiresInSeconds = $longLived['expires_in'] ?? null;

        $me = Http::get("{$this->baseUrl}/me", [
            'fields' => 'id,name',
            'access_token' => $accessToken,
        ])->throw()->json();

        return Integration::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'provider' => IntegrationProvider::Meta->value,
                'external_account_id' => $me['id'],
            ],
            [
                'display_name' => $me['name'] ?? null,
                'status' => 'connected',
                'access_token' => $accessToken,
                'token_expires_at' => $expiresInSeconds ? Carbon::now()->addSeconds($expiresInSeconds) : null,
                'scopes' => config('meta.scopes'),
                'connected_at' => Carbon::now(),
                'last_synced_at' => Carbon::now(),
            ]
        );
    }

    public function listConnectableAccounts(Integration $integration): array
    {
        $response = Http::get("{$this->baseUrl}/me/accounts", [
            'fields' => 'id,name,access_token,instagram_business_account{id,username,name}',
            'access_token' => $integration->access_token,
            'limit' => 100,
        ])->throw()->json();

        $accounts = [];

        foreach ($response['data'] ?? [] as $page) {
            $accounts[] = new DiscoveredAccount(
                channelType: ChannelType::FacebookMessenger,
                externalAccountId: $page['id'],
                displayName: $page['name'],
                username: null,
                pageAccessToken: $page['access_token'],
            );

            if (! empty($page['instagram_business_account'])) {
                $ig = $page['instagram_business_account'];

                $accounts[] = new DiscoveredAccount(
                    channelType: ChannelType::Instagram,
                    externalAccountId: $ig['id'],
                    displayName: $ig['name'] ?? ($ig['username'] ?? 'Instagram'),
                    username: $ig['username'] ?? null,
                    pageAccessToken: $page['access_token'],
                    parentPageId: $page['id'],
                );
            }
        }

        return $accounts;
    }

    public function subscribeWebhooks(Channel $channel): bool
    {
        $fields = $channel->type === ChannelType::Instagram
            ? config('meta.webhook_fields.instagram')
            : config('meta.webhook_fields.page');

        // Instagram messaging (via a Page-linked Professional account) and
        // Messenger both subscribe through the parent Page's subscribed_apps
        // endpoint — Meta routes IG DMs to the same webhook once the Page
        // is subscribed with the `messages` field.
        $pageId = $channel->type === ChannelType::Instagram
            ? ($channel->metadata['parent_page_id'] ?? null)
            : $channel->external_account_id;

        if (! $pageId) {
            Log::warning('meta.subscribe_webhooks.missing_page_id', ['channel_id' => $channel->id]);

            return false;
        }

        $response = Http::post("{$this->baseUrl}/{$pageId}/subscribed_apps", [
            'subscribed_fields' => implode(',', $fields),
            'access_token' => $channel->access_token,
        ]);

        if ($response->failed()) {
            Log::warning('meta.subscribe_webhooks.failed', [
                'channel_id' => $channel->id,
                'status' => $response->status(),
            ]);

            return false;
        }

        return (bool) ($response->json('success') ?? true);
    }

    public function unsubscribeWebhooks(Channel $channel): bool
    {
        $pageId = $channel->type === ChannelType::Instagram
            ? ($channel->metadata['parent_page_id'] ?? null)
            : $channel->external_account_id;

        if (! $pageId || ! $channel->access_token) {
            return true;
        }

        $response = Http::delete("{$this->baseUrl}/{$pageId}/subscribed_apps", [
            'access_token' => $channel->access_token,
        ]);

        if ($response->failed()) {
            Log::warning('meta.unsubscribe_webhooks.failed', [
                'channel_id' => $channel->id,
                'status' => $response->status(),
            ]);

            return false;
        }

        return true;
    }

    public function sendMessage(Channel $channel, Conversation $conversation, ?string $text, ?OutboundAttachment $attachment = null): SendMessageResult
    {
        if (! $channel->access_token || ! $conversation->external_conversation_id) {
            return SendMessageResult::failure('Kanal ni pravilno povezan.');
        }

        if ($attachment) {
            // Upload the file's bytes directly rather than giving Meta a URL
            // to fetch — avoids depending on our own server being reachable
            // *from* Meta (which local/tunnel dev environments often aren't).
            $response = Http::attach('filedata', file_get_contents($attachment->localPath), basename($attachment->localPath))
                ->post("{$this->baseUrl}/{$channel->external_account_id}/messages", [
                    'recipient' => json_encode(['id' => $conversation->external_conversation_id]),
                    'message' => json_encode(['attachment' => ['type' => $attachment->type, 'payload' => ['is_reusable' => true]]]),
                    'messaging_type' => 'RESPONSE',
                    'access_token' => $channel->access_token,
                ]);
        } else {
            $response = Http::post("{$this->baseUrl}/{$channel->external_account_id}/messages", [
                'recipient' => ['id' => $conversation->external_conversation_id],
                'message' => ['text' => $text],
                'messaging_type' => 'RESPONSE',
                'access_token' => $channel->access_token,
            ]);
        }

        if ($response->successful()) {
            return SendMessageResult::success($response->json('message_id') ?? (string) $response->json('recipient_id'));
        }

        $error = $response->json('error') ?? [];
        $subcode = $error['error_subcode'] ?? null;
        $windowExpired = in_array($subcode, [2018278, 2018001], true)
            || str_contains(strtolower($error['message'] ?? ''), 'outside of allowed window');

        Log::warning('meta.send_message.failed', [
            'channel_id' => $channel->id,
            'conversation_id' => $conversation->id,
            'status' => $response->status(),
            'error_code' => $error['code'] ?? null,
            'error_subcode' => $subcode,
        ]);

        return SendMessageResult::failure(
            $windowExpired
                ? 'Okno za odgovor je poteklo.'
                : ($error['message'] ?? 'Sporočila ni bilo mogoče poslati.'),
            $windowExpired
        );
    }

    public function verifyWebhookSignature(string $rawBody, ?string $signatureHeader): bool
    {
        if (! $signatureHeader || ! str_starts_with($signatureHeader, 'sha256=')) {
            return false;
        }

        $expected = 'sha256='.hash_hmac('sha256', $rawBody, (string) config('meta.app_secret'));

        return hash_equals($expected, $signatureHeader);
    }

    public function normalizeWebhookPayload(array $payload): array
    {
        $provider = 'meta';
        $normalized = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            $channelExternalId = (string) ($entry['id'] ?? '');

            foreach ($entry['messaging'] ?? [] as $event) {
                // Skip echoes of our own outbound sends and non-message events
                // (postbacks/read receipts aren't handled yet — see roadmap).
                if (empty($event['message']) || ! empty($event['message']['is_echo'])) {
                    continue;
                }

                $message = $event['message'];
                $senderId = (string) ($event['sender']['id'] ?? '');

                if ($senderId === '' || $channelExternalId === '') {
                    continue;
                }

                $attachments = [];
                foreach ($message['attachments'] ?? [] as $attachment) {
                    $attachments[] = [
                        'type' => $attachment['type'] ?? 'file',
                        'url' => $attachment['payload']['url'] ?? null,
                    ];
                }

                $normalized[] = new NormalizedIncomingMessage(
                    provider: $provider,
                    channelExternalId: $channelExternalId,
                    conversationExternalId: $senderId,
                    senderExternalId: $senderId,
                    senderName: null,
                    senderUsername: null,
                    messageExternalId: $message['mid'] ?? null,
                    text: $message['text'] ?? null,
                    attachments: $attachments,
                    sentAt: isset($event['timestamp'])
                        ? Carbon::createFromTimestampMs((int) $event['timestamp'])
                        : Carbon::now(),
                    rawMetadata: $event,
                );
            }
        }

        return $normalized;
    }

    public function fetchSenderProfile(Channel $channel, string $externalId): array
    {
        if (! $channel->access_token) {
            return [];
        }

        $fields = $channel->type === ChannelType::Instagram
            ? 'name,username,profile_pic'
            : 'first_name,last_name,profile_pic';

        $response = Http::timeout(5)->get("{$this->baseUrl}/{$externalId}", [
            'fields' => $fields,
            'access_token' => $channel->access_token,
        ]);

        if ($response->failed()) {
            return [];
        }

        $data = $response->json();

        if ($channel->type === ChannelType::Instagram) {
            return [
                'name' => $data['name'] ?? null,
                'username' => $data['username'] ?? null,
                'avatar_url' => $data['profile_pic'] ?? null,
            ];
        }

        $name = trim(($data['first_name'] ?? '').' '.($data['last_name'] ?? ''));

        return [
            'name' => $name !== '' ? $name : null,
            'username' => null,
            'avatar_url' => $data['profile_pic'] ?? null,
        ];
    }

    public function conversationDeepLink(Channel $channel, Conversation $conversation): ?string
    {
        if (! $conversation->external_conversation_id) {
            return null;
        }

        return match ($channel->type) {
            ChannelType::Instagram => 'https://ig.me/m/'.($channel->handle ? ltrim($channel->handle, '@') : ''),
            ChannelType::FacebookMessenger => 'https://www.facebook.com/messages/t/'.$conversation->external_conversation_id,
            default => null,
        };
    }
}
