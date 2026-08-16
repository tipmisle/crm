<?php

namespace App\Http\Controllers;

use App\Enums\MessageStatus;
use App\Models\ActivityLog;
use App\Models\Order;
use App\Services\Messaging\MessagingProviderManager;
use App\Services\Messaging\OutboundMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Obvesti stranko" — sends a pickup-ready or shipped notification through
 * the order's existing DM conversation, reusing OutboundMessageService (the
 * same path Invoicing\SalesDocumentSendController/SalesDocumentReminderController
 * use). Deliberately status-independent: never reads/writes Order::status,
 * since statuses are workspace-customizable and can't be relied on to mean
 * "ready for pickup" or "shipped" for any given workspace.
 */
class OrderNotificationController extends Controller
{
    public function store(
        Request $request,
        Order $order,
        MessagingProviderManager $providers,
        OutboundMessageService $outboundMessages,
    ): RedirectResponse {
        $data = $request->validate([
            'type' => 'required|in:pickup,shipped',
            'body' => 'required|string|max:2000',
            'tracking_number' => 'nullable|string|max:100',
            'tracking_url' => 'nullable|url|max:2048',
        ]);

        $order->loadMissing('conversation.channel');
        $conversation = $order->conversation;

        abort_unless($conversation, 422, 'To naročilo nima povezanega pogovora.');

        $channel = $conversation->channel;

        if (! $channel || ! $channel->isConnected()) {
            return back()->with('error', 'Ta pogovor nima povezanega kanala. Poveži Meta v nastavitvah.');
        }

        // Tracking data is saved regardless of send outcome — a failed
        // provider send must not lose what the user already typed in.
        if ($data['type'] === 'shipped') {
            $order->update([
                'tracking_number' => $data['tracking_number'] ?? null,
                'tracking_url' => $data['tracking_url'] ?? null,
                'shipped_at' => $order->shipped_at ?? now(),
            ]);
        }

        $provider = $providers->forChannel($channel);
        $message = $outboundMessages->send($channel, $conversation, $data['body'], null, $provider);

        if ($message->status !== MessageStatus::Sent) {
            return back()->with('error', $message->failure_reason ?? 'Sporočila ni bilo mogoče poslati.');
        }

        $description = $data['type'] === 'pickup'
            ? "Stranka je bila obveščena, da je naročilo {$order->order_number} pripravljeno za prevzem."
            : "Stranka je bila obveščena o poslani pošiljki za naročilo {$order->order_number}.";

        ActivityLog::record('customer_notified', $description, $order);

        return back()->with('success', 'Stranka je bila obveščena.');
    }
}
