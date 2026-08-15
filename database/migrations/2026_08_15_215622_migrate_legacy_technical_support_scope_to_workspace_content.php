<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The `technical` support-access scope was removed (see
 * App\Enums\SupportAccessScope, docs/admin-security.md) — normal admin
 * metadata was already visible without any grant at all, so `technical`
 * granted almost nothing and only confused the owner-facing consent copy.
 *
 * Any existing row with scope='technical' must be handled without either
 * (a) crashing the enum cast on read, or (b) silently upgrading an owner's
 * explicit "technical only" choice into content access they never
 * approved. So: legacy rows are rewritten to the remaining
 * 'workspace_content' value (the only value the enum cast can hydrate) but
 * simultaneously deactivated — an active grant is revoked, an active
 * session is ended — so no row gains any access it wasn't already
 * explicitly granted for. This is a V1, no-production-customers-yet
 * dataset, so a direct update is appropriate (no chunking needed).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('support_access_grants')
            ->where('scope', 'technical')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => $now]);

        DB::table('support_access_grants')
            ->where('scope', 'technical')
            ->update(['scope' => 'workspace_content']);

        DB::table('support_sessions')
            ->where('scope', 'technical')
            ->whereNull('ended_at')
            ->update(['ended_at' => $now, 'ended_reason' => 'scope_removed']);

        DB::table('support_sessions')
            ->where('scope', 'technical')
            ->update(['scope' => 'workspace_content']);
    }

    public function down(): void
    {
        // Deliberately a no-op: reintroducing the `technical` scope value
        // would require restoring the removed enum case in code first, and
        // reversing which rows were "already revoked before this ran" vs.
        // "revoked by this migration" is not recoverable from the data
        // alone. If the `technical` scope is ever reinstated, do it as a
        // fresh forward migration instead of rolling this one back.
    }
};
