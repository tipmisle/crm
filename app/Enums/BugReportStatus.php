<?php

namespace App\Enums;

enum BugReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Odprto',
            self::Resolved => 'Rešeno',
        };
    }
}
