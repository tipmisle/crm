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
            self::NewEnquiry => 'New enquiry',
            self::NeedsQuote => 'Needs quote',
            self::WaitingForCustomer => 'Waiting for customer',
            self::OrderConfirmed => 'Order confirmed',
            self::Closed => 'Closed',
        };
    }
}
