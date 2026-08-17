<?php

namespace App\Jobs;

use App\Events\InboxMessageReceived;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\CustomerIdentity;
use App\Services\Messaging\MessagingProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Backfills a new CustomerIdentity's display name/avatar from the provider's
 * Graph API. Runs after the message is already visible in the Inbox — the
 * profile lookup can be slow (real network call to Meta) and must never
 * block message ingestion/broadcast on the critical path.
 */
class FetchCustomerIdentityProfile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public array $backoff = [30];

    // A real network call to Meta's Graph API — must not hold the worker
    // indefinitely if Meta is slow/unresponsive.
    public int $timeout = 30;

    public function __construct(
        private readonly int $channelId,
        private readonly int $customerIdentityId,
    ) {}

    public function handle(MessagingProviderManager $providers): void
    {
        $channel = Channel::withoutGlobalScopes()->find($this->channelId);
        $identity = CustomerIdentity::withoutGlobalScopes()->find($this->customerIdentityId);

        if (! $channel || ! $identity) {
            return;
        }

        try {
            $profile = $providers->forChannel($channel)->fetchSenderProfile($channel, $identity->external_id);
        } catch (\Throwable $e) {
            Log::info('messaging.ingest.profile_lookup_failed', ['channel_id' => $channel->id, 'error' => $e->getMessage()]);

            return;
        }

        if (empty($profile)) {
            return;
        }

        $identity->update([
            'username' => $identity->username ?? ($profile['username'] ?? null),
            'display_name' => $identity->display_name ?? ($profile['name'] ?? null),
            'metadata' => isset($profile['avatar_url']) ? ['avatar_url' => $profile['avatar_url']] : $identity->metadata,
        ]);

        // The conversation snapshot its display name at creation time, before
        // this profile lookup had a chance to run — backfill it now and tell
        // any open Inbox tab to refresh so the name doesn't stay "Unknown".
        $conversations = Conversation::withoutGlobalScopes()
            ->where('channel_id', $channel->id)
            ->where('external_conversation_id', $identity->external_id)
            ->get();

        foreach ($conversations as $conversation) {
            $conversation->update([
                'customer_display_name' => $conversation->customer_display_name ?? $identity->display_name,
                'customer_username' => $conversation->customer_username ?? $identity->username,
            ]);

            broadcast(new InboxMessageReceived($channel->workspace_id, $conversation->id));
        }
    }
}
