<?php

namespace App\Enums;

enum ChannelType: string
{
    case Instagram = 'instagram';
    case FacebookMessenger = 'facebook_messenger';
    case TikTok = 'tiktok';
    case WhatsApp = 'whatsapp';
    case Email = 'email';
    case Website = 'website';

    public function label(): string
    {
        return match ($this) {
            self::Instagram => 'Instagram',
            self::FacebookMessenger => 'Facebook Messenger',
            self::TikTok => 'TikTok',
            self::WhatsApp => 'WhatsApp',
            self::Email => 'E-pošta',
            self::Website => 'Spletna stran',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Instagram => '#E1306C',
            self::FacebookMessenger => '#0084FF',
            self::TikTok => '#010101',
            self::WhatsApp => '#25D366',
            self::Email => '#6B7280',
            self::Website => '#6366F1',
        };
    }
}
