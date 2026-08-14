<?php

namespace App\Jobs;

use App\Services\Messaging\MessageIngestionService;
use App\Services\Messaging\MetaMessagingProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessMetaWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private readonly array $payload) {}

    public function handle(MetaMessagingProvider $provider, MessageIngestionService $ingestion): void
    {
        $normalizedMessages = $provider->normalizeWebhookPayload($this->payload);

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
