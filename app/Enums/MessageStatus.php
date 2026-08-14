<?php

namespace App\Enums;

enum MessageStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Delivered = 'delivered';
    case Read = 'read';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'V pošiljanju',
            self::Sent => 'Poslano',
            self::Failed => 'Neuspešno',
            self::Delivered => 'Dostavljeno',
            self::Read => 'Prebrano',
        };
    }
}
