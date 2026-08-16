<?php

namespace App\Http\Controllers;

use App\Enums\MessageStatus;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Channel;
use App\Models\Conversation;
use App\Services\Messaging\MessagingProviderManager;
use App\Services\Messaging\OutboundMessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AppointmentNotificationController extends Controller
{
    public function store(
        Request $request,
        Appointment $appointment,
        MessagingProviderManager $providers,
        OutboundMessageService $outboundMessages,
    ): RedirectResponse {
        $data = $request->validate(['body' => 'required|string|max:2000']);

        $appointment->loadMissing(['conversation.channel', 'conversation.workspace', 'channel', 'customer.primaryChannel', 'workspace']);
        $conversation = $appointment->conversation;
        $mockNotificationsEnabled = app()->isLocal() || $appointment->workspace?->is_demo;

        if (! $conversation && $mockNotificationsEnabled) {
            $channel = $appointment->channel ?? $appointment->customer?->primaryChannel;

            if ($channel) {
                $conversation = Conversation::create([
                    'workspace_id' => $appointment->workspace_id,
                    'channel_id' => $channel->id,
                    'customer_id' => $appointment->customer_id,
                    'external_conversation_id' => 'mock_appointment_'.$appointment->id,
                    'customer_display_name' => $appointment->customer?->full_name,
                    'status' => 'order_confirmed',
                ]);
                $conversation->setRelation('channel', $channel);
                $conversation->setRelation('workspace', $appointment->workspace);
                $appointment->update([
                    'conversation_id' => $conversation->id,
                    'channel_id' => $channel->id,
                ]);
            }
        }

        abort_unless($conversation, 422, 'Ta termin nima povezanega pogovora.');

        $channel = $conversation->channel;
        $useMockProvider = (! $channel || ! $channel->isConnected())
            && (app()->isLocal() || $conversation->workspace?->is_demo);

        if ((! $channel || ! $channel->isConnected()) && ! $useMockProvider) {
            return back()->with('error', 'Ta pogovor nima povezanega kanala. Poveži Meta v nastavitvah.');
        }

        if (! $channel) {
            $channel = new Channel([
                'workspace_id' => $conversation->workspace_id,
                'type' => 'instagram',
                'display_name' => 'Mock',
                'status' => 'connected',
            ]);
            $channel->setRelation('workspace', $conversation->workspace);
        }

        $provider = $useMockProvider ? $providers->driver('mock') : $providers->forChannel($channel);
        $message = $outboundMessages->send($channel, $conversation, $data['body'], null, $provider);

        if ($message->status !== MessageStatus::Sent) {
            return back()->with('error', $message->failure_reason ?? 'Opomnika ni bilo mogoče poslati.');
        }

        ActivityLog::record(
            'customer_notified',
            "Stranka je bila obveščena o terminu {$appointment->appointment_number}.",
            $appointment,
        );

        return back()->with('success', 'Opomnik za termin je bil poslan.');
    }
}
