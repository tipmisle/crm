<?php

namespace App\Enums;

enum IntegrationStatus: string
{
    case Connected = 'connected';
    case Disconnected = 'disconnected';
    case Expired = 'expired';
    case Error = 'error';

    public function label(): string
    {
        return match ($this) {
            self::Connected => 'Povezano',
            self::Disconnected => 'Ni povezano',
            self::Expired => 'Poteklo',
            self::Error => 'Napaka',
        };
    }
}
