<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Requested = 'requested';
    case Confirmed = 'confirmed';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Povpraševanje',
            self::Confirmed => 'Potrjeno',
            self::Completed => 'Zaključeno',
            self::Cancelled => 'Preklicano',
            self::NoShow => 'Ni se zglasil/a',
            self::Refunded => 'Vračilo',
        };
    }

    public static function board(): array
    {
        return [
            self::Requested,
            self::Confirmed,
            self::Completed,
            self::Cancelled,
            self::NoShow,
            self::Refunded,
        ];
    }
}
