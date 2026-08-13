<?php

namespace App\Enums;

enum MessageSenderType: string
{
    case Customer = 'customer';
    case Business = 'business';
    case System = 'system';
}
