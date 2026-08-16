<?php

namespace App\Http\Controllers\Invoicing;

use App\Enums\MessageStatus;
use App\Http\Controllers\Controller;
use App\Models\SalesDocument;
use App\Services\Messaging\MessagingProviderManager;
use App\Services\Messaging\OutboundMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Pošlji opomnik za plačilo" — text-only reminder (no PDF re-attached)
 * through the same DM conversation. No separate DB tracking of reminder
 * sends: out of scope for V1, would be a speculative column with no
 * consumer yet.
 */
class SalesDocumentReminderController extends Controller
{
    public function store(
        Request $request,
        SalesDocument $document,
        MessagingProviderManager $providers,
        OutboundMessageService $outboundMessages,
    ): RedirectResponse {
        abort_unless($document->workspace_id === $request->user()->current_workspace_id, 404);
        abort_unless($document->isActive(), 422, 'Ta dokument ni več aktiven in ga ni mogoče poslati kot zahtevo za plačilo.');

        $data = $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        $document->loadMissing(['order.conversation.channel', 'appointment.conversation.channel']);
        $subject = $document->order ?? $document->appointment;
        $conversation = $subject?->conversation;

        abort_unless($conversation, 422, 'Ta zapis nima povezanega pogovora.');

        $channel = $conversation->channel;

        if (! $channel || ! $channel->isConnected()) {
            return back()->with('error', 'Ta pogovor nima povezanega kanala. Poveži Meta v nastavitvah.');
        }

        $provider = $providers->forChannel($channel);
        $message = $outboundMessages->send($channel, $conversation, $data['body'], null, $provider);

        if ($message->status === MessageStatus::Failed) {
            return back()->with('error', $message->failure_reason ?? 'Opomnika ni bilo mogoče poslati.');
        }

        return back()->with('success', 'Opomnik je bil poslan.');
    }
}
