<?php

namespace App\Enums;

enum OrderStatus: string
{
    case New = 'new';
    case QuoteNeeded = 'quote_needed';
    case QuoteSent = 'quote_sent';
    case WaitingForCustomer = 'waiting_for_customer';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Ready = 'ready';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::QuoteNeeded => 'Quote needed',
            self::QuoteSent => 'Quote sent',
            self::WaitingForCustomer => 'Waiting for customer',
            self::Confirmed => 'Confirmed',
            self::InProgress => 'In progress',
            self::Ready => 'Ready',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    public static function board(): array
    {
        return [
            self::New,
            self::QuoteNeeded,
            self::QuoteSent,
            self::WaitingForCustomer,
            self::Confirmed,
            self::InProgress,
            self::Ready,
            self::Completed,
            self::Cancelled,
        ];
    }
}
