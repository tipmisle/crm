<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Product invariant: one Meta channel/account may belong to only ONE
 * workspace at a time. Previously channels.external_account_id was only
 * unique per-workspace (workspace_id, type, external_account_id) — nothing
 * stopped the same Instagram/Messenger account from being connected to two
 * different workspaces, which would make inbound webhook routing
 * (MessageIngestionService) ambiguous about which workspace an incoming DM
 * belongs to. See docs/data-security.md / app/Services/Messaging/MessageIngestionService.php.
 *
 * Demo workspaces never set external_account_id (verified: no seeder or
 * DemoController code path sets it), so NULLs — which a unique index never
 * considers duplicates — keep demo workspaces entirely unaffected.
 *
 * Disconnecting a channel (MetaIntegrationController::destroy) now clears
 * external_account_id, freeing the account to be connected elsewhere —
 * this is what makes "at a time" true without needing a conditional/partial
 * index, which isn't portably expressible across MySQL and the SQLite test
 * database.
 */
return new class extends Migration
{
    public function up(): void
    {
        $duplicates = DB::table('channels')
            ->select('type', 'external_account_id')
            ->whereNotNull('external_account_id')
            ->groupBy('type', 'external_account_id')
            ->havingRaw('count(*) > 1')
            ->get();

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Cannot add unique(type, external_account_id) to channels: '.$duplicates->count().
                ' existing (type, external_account_id) pair(s) are already duplicated across workspaces. '.
                'Resolve manually before re-running this migration — see docs/data-security.md.'
            );
        }

        Schema::table('channels', function (Blueprint $table) {
            $table->unique(['type', 'external_account_id'], 'channels_type_external_account_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropUnique('channels_type_external_account_id_unique');
        });
    }
};
