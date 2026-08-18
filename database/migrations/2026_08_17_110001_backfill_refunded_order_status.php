<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off data backfill: every workspace created before is_refunded
 * existed has no "Vračilo" order status. New workspaces get it from
 * App\Services\WorkspaceStatusDefaults::seedOrderStatuses() going
 * forward — this migration only catches existing ones, and only if they
 * don't already have a status flagged is_refunded (so it's safe to
 * re-run and never duplicates one an owner already created themselves).
 */
return new class extends Migration
{
    public function up(): void
    {
        $workspaceIds = DB::table('workspaces')->pluck('id');

        foreach ($workspaceIds as $workspaceId) {
            $alreadyHasRefunded = DB::table('order_statuses')
                ->where('workspace_id', $workspaceId)
                ->where('is_refunded', true)
                ->exists();

            if ($alreadyHasRefunded) {
                continue;
            }

            $nextSortOrder = (int) DB::table('order_statuses')
                ->where('workspace_id', $workspaceId)
                ->max('sort_order') + 1;

            $key = 'refunded';
            $suffix = 2;
            while (DB::table('order_statuses')->where('workspace_id', $workspaceId)->where('key', $key)->exists()) {
                $key = 'refunded_'.$suffix;
                $suffix++;
            }

            DB::table('order_statuses')->insert([
                'workspace_id' => $workspaceId,
                'key' => $key,
                'label' => 'Vračilo',
                'color' => '#B91C1C',
                'bg' => '#FEE2E2',
                'sort_order' => $nextSortOrder,
                'is_default' => false,
                'is_completed' => false,
                'is_cancelled' => false,
                'is_refunded' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('order_statuses')->where('key', 'refunded')->where('is_refunded', true)->delete();
    }
};
