<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessMetaWebhook;
use App\Services\Messaging\MetaMessagingProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MetaWebhookController extends Controller
{
    /**
     * Meta's one-time webhook verification handshake.
     */
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && hash_equals((string) config('meta.webhook_verify_token'), (string) $token)) {
            return response((string) $challenge, 200);
        }

        Log::warning('meta.webhook.verify_failed', ['mode' => $mode]);

        return response('Forbidden', 403);
    }

    /**
     * Receives Instagram + Messenger messaging events. Must respond fast —
     * all real work happens in the queued ProcessMetaWebhook job.
     */
    public function handle(Request $request, MetaMessagingProvider $provider): Response
    {
        $rawBody = $request->getContent();
        $signature = $request->header('X-Hub-Signature-256');

        if (! $provider->verifyWebhookSignature($rawBody, $signature)) {
            Log::warning('meta.webhook.invalid_signature');

            return response('Forbidden', 403);
        }

        $payload = $request->json()->all();

        if (! is_array($payload) || ! isset($payload['object'], $payload['entry'])) {
            Log::warning('meta.webhook.malformed_payload');

            return response('EVENT_RECEIVED', 200);
        }

        ProcessMetaWebhook::dispatch($payload);

        return response('EVENT_RECEIVED', 200);
    }
}
