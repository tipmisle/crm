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
            self::New => 'Novo',
            self::QuoteNeeded => 'Potrebna ponudba',
            self::QuoteSent => 'Ponudba poslana',
            self::WaitingForCustomer => 'Čaka na stranko',
            self::Confirmed => 'Potrjeno',
            self::InProgress => 'V izdelavi',
            self::Ready => 'Pripravljeno',
            self::Completed => 'Zaključeno',
            self::Cancelled => 'Preklicano',
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
