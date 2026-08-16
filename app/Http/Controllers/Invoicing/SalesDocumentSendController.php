<?php

namespace App\Http\Controllers\Invoicing;

use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\SalesDocument;
use App\Services\Messaging\DTOs\OutboundAttachment;
use App\Services\Messaging\MessagingProviderManager;
use App\Services\Messaging\OutboundMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * "Pošlji stranki" / "Pošlji znova" — sends the already-issued PDF in the
 * order's existing DM conversation via the same OutboundMessageService used
 * by Inbox\ConversationController (extracted from it, not reimplemented).
 * Never re-uploads the file: it's already on the private 'local' disk from
 * issuance/external upload.
 *
 * On failure, sent_at is left untouched — the document stays issued and
 * this same endpoint can simply be called again ("Pošlji znova").
 */
class SalesDocumentSendController extends Controller
{
    public function store(
        Request $request,
        SalesDocument $document,
        MessagingProviderManager $providers,
        OutboundMessageService $outboundMessages,
    ): RedirectResponse {
        abort_unless($document->workspace_id === $request->user()->current_workspace_id, 404);

        $data = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $order = $document->order()->with('conversation.channel')->first();
        $conversation = $order?->conversation;

        abort_unless($conversation, 422, 'To naročilo nima povezanega pogovora.');

        $channel = $conversation->channel;

        if (! $channel || ! $channel->isConnected()) {
            return back()->with('error', 'Ta pogovor nima povezanega kanala. Poveži Meta v nastavitvah.');
        }

        abort_unless($document->pdf_path && Storage::disk('local')->exists($document->pdf_path), 404);

        $attachment = new OutboundAttachment('file', $document->pdf_path, Storage::disk('local')->path($document->pdf_path));

        $provider = $providers->forChannel($channel);
        $message = $outboundMessages->send($channel, $conversation, $data['body'], $attachment, $provider);

        if ($message->status !== MessageStatus::Sent) {
            return back()->with('error', $message->failure_reason ?? 'Dokumenta ni bilo mogoče poslati.');
        }

        $document->update(['sent_at' => now()]);

        return back()->with('success', 'Dokument je bil poslan stranki.');
    }
}
