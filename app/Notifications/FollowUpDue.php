<?php

namespace App\Notifications;

use App\Models\FollowUp;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use NotificationChannels\WebPush\WebPushChannel;
use NotificationChannels\WebPush\WebPushMessage;

class FollowUpDue extends Notification
{
    use Queueable;

    public function __construct(private readonly FollowUp $followUp)
    {
    }

    public function via(object $notifiable): array
    {
        return [WebPushChannel::class];
    }

    public function toWebPush(object $notifiable, object $notification): WebPushMessage
    {
        return (new WebPushMessage)
            ->title('Opomnik')
            ->icon('/favicon.ico')
            ->body($this->followUp->note)
            ->action('Odpri', 'open')
            ->data(['url' => $this->followUpUrl()])
            ->options(['TTL' => 3600]);
    }

    private function followUpUrl(): string
    {
        return match ($this->followUp->followable_type) {
            \App\Models\Customer::class => route('customers.show', $this->followUp->followable_id),
            \App\Models\Order::class => route('orders.show', $this->followUp->followable_id),
            \App\Models\Appointment::class => route('appointments.show', $this->followUp->followable_id),
            \App\Models\Conversation::class => route('inbox.show', $this->followUp->followable_id),
            default => route('dashboard'),
        };
    }
}
