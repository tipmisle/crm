<?php

namespace App\Enums;

enum FeatureRequestStatus: string
{
    case Open = 'open';
    case Planned = 'planned';
    case Done = 'done';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Predlagano',
            self::Planned => 'Načrtovano',
            self::Done => 'Izvedeno',
        };
    }
}
