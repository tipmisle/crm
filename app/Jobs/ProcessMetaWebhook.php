<?php

namespace App\Jobs;

use App\Services\Messaging\MessageIngestionService;
use App\Services\Messaging\MetaMessagingProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Carries the raw Meta webhook payload (may include message text, sender
 * identifiers and attachment URLs) through the queue. The payload is
 * encrypted before it's stored on the job property, so a snapshot of the
 * `jobs` table — or, worse, an indefinitely-retained `failed_jobs` row
 * after repeated failures — never holds this in plaintext. See
 * docs/data-security.md "Queue failure data".
 */
class ProcessMetaWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    private readonly string $encryptedPayload;

    public function __construct(array $payload)
    {
        $this->encryptedPayload = Crypt::encryptString(json_encode($payload));
    }

    public function handle(MetaMessagingProvider $provider, MessageIngestionService $ingestion): void
    {
        $payload = json_decode(Crypt::decryptString($this->encryptedPayload), true);
        $normalizedMessages = $provider->normalizeWebhookPayload($payload);

        foreach ($normalizedMessages as $normalized) {
            try {
                $ingestion->ingest($normalized);
            } catch (\Throwable $e) {
                Log::error('meta.webhook.ingest_failed', [
                    'error' => $e->getMessage(),
                    'channel_external_id' => $normalized->channelExternalId,
                    'message_external_id' => $normalized->messageExternalId,
                ]);

                throw $e;
            }
        }
    }
}
