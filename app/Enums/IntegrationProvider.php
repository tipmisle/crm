<?php

namespace App\Enums;

enum IntegrationProvider: string
{
    case Meta = 'meta';
    case TikTok = 'tiktok';
    case Gmail = 'gmail';
    case Outlook = 'outlook';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Meta => 'Meta',
            self::TikTok => 'TikTok',
            self::Gmail => 'Gmail',
            self::Outlook => 'Outlook',
            self::WhatsApp => 'WhatsApp',
        };
    }
}
