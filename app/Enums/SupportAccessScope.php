<?php

namespace App\Enums;

/**
 * V1 has exactly one grantable scope. The original design also had a
 * `technical` scope, but normal admin metadata (workspace config,
 * integration status, operational counts) was already visible without any
 * grant at all — `technical` therefore granted almost nothing beyond the
 * no-grant baseline and only confused the owner-facing explanation of what
 * they were approving. Removed; see docs/admin-security.md.
 */
enum SupportAccessScope: string
{
    case WorkspaceContent = 'workspace_content';

    public function label(): string
    {
        return match ($this) {
            self::WorkspaceContent => 'Vpogled v podatke delovnega prostora',
        };
    }
}
