<?php

namespace App\Enums;

enum MessageType: string
{
    case Text = 'text';
    case Image = 'image';
    case Note = 'note';
    case System = 'system';
}
