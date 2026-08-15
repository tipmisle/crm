<?php

namespace App\Enums;

enum SupportAccessScope: string
{
    case Technical = 'technical';
    case WorkspaceContent = 'workspace_content';

    public function label(): string
    {
        return match ($this) {
            self::Technical => 'Tehnični dostop',
            self::WorkspaceContent => 'Vpogled v podatke delovnega prostora',
        };
    }
}
