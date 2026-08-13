<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case DepositDue = 'deposit_due';
    case DepositPaid = 'deposit_paid';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::DepositDue => 'Deposit due',
            self::DepositPaid => 'Deposit paid',
            self::PartiallyPaid => 'Partially paid',
            self::Paid => 'Paid',
        };
    }
}
