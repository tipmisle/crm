<?php

namespace App\Notifications;

use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class FollowUpDue extends Notification
{
    use Queueable;

    public function __construct(private readonly FollowUp $followUp) {}

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        // Deliberately no note content in the lock-screen/OS notification
        // body — a follow-up note may reference private customer details.
        // The user opens the app (already authenticated) to read it. See
        // docs/data-security.md "Push notifications".
        return (new WebPushMessage)
            ->title('Opomnik')
            ->icon('/favicon.ico')
            ->body('Opomnik je zapadel. Odpri Beležko za podrobnosti.')
            ->action('Odpri', 'open')
            ->data(['url' => $this->followUpUrl()])
            ->options(['TTL' => 3600]);
    }

    private function followUpUrl(): string
    {
        return match ($this->followUp->followable_type) {
            Customer::class => route('customers.show', $this->followUp->followable_id),
            Order::class => route('orders.show', $this->followUp->followable_id),
            Appointment::class => route('appointments.show', $this->followUp->followable_id),
            Conversation::class => route('inbox.show', $this->followUp->followable_id),
            default => route('dashboard'),
        };
    }
}
