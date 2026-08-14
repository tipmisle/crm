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
            self::Unpaid => 'Neplačano',
            self::DepositDue => 'Čaka se ara',
            self::DepositPaid => 'Ara plačana',
            self::PartiallyPaid => 'Delno plačano',
            self::Paid => 'Plačano',
        };
    }
}
