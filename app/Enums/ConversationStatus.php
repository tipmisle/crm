<?php

namespace App\Enums;

enum ConversationStatus: string
{
    case NewEnquiry = 'new_enquiry';
    case NeedsQuote = 'needs_quote';
    case WaitingForCustomer = 'waiting_for_customer';
    case OrderConfirmed = 'order_confirmed';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::NewEnquiry => 'Novo povpraševanje',
            self::NeedsQuote => 'Potrebna ponudba',
            self::WaitingForCustomer => 'Čaka na stranko',
            self::OrderConfirmed => 'Naročilo potrjeno',
            self::Closed => 'Zaključeno',
        };
    }
}
