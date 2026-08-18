<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off data backfill, same rationale as
 * 2026_08_17_110001_backfill_refunded_order_status: every workspace
 * created before is_paid/is_refunded existed has no status flagged as
 * either. Flags the existing "paid" row (if any) as is_paid, and always
 * inserts a new "Vračilo" payment status if none is flagged is_refunded —
 * a workspace's payment list never had a refund state before this. Safe
 * to re-run: skips workspaces that already have a flagged row.
 */
return new class extends Migration
{
    public function up(): void
    {
        $workspaceIds = DB::table('workspaces')->pluck('id');

        foreach ($workspaceIds as $workspaceId) {
            $this->backfillPaid($workspaceId);
            $this->backfillRefunded($workspaceId);
        }
    }

    private function backfillPaid(int $workspaceId): void
    {
        $alreadyHasPaid = DB::table('payment_statuses')
            ->where('workspace_id', $workspaceId)
            ->where('is_paid', true)
            ->exists();

        if ($alreadyHasPaid) {
            return;
        }

        $updated = DB::table('payment_statuses')
            ->where('workspace_id', $workspaceId)
            ->where('key', 'paid')
            ->update(['is_paid' => true, 'updated_at' => now()]);

        if ($updated > 0) {
            return;
        }

        $this->insertStatus($workspaceId, 'paid', 'Plačano', '#15803D', '#DCFCE7', ['is_paid' => true]);
    }

    private function backfillRefunded(int $workspaceId): void
    {
        $alreadyHasRefunded = DB::table('payment_statuses')
            ->where('workspace_id', $workspaceId)
            ->where('is_refunded', true)
            ->exists();

        if ($alreadyHasRefunded) {
            return;
        }

        $this->insertStatus($workspaceId, 'refunded', 'Vračilo', '#B91C1C', '#FEE2E2', ['is_refunded' => true]);
    }

    private function insertStatus(int $workspaceId, string $baseKey, string $label, string $color, string $bg, array $flags): void
    {
        $key = $baseKey;
        $suffix = 2;
        while (DB::table('payment_statuses')->where('workspace_id', $workspaceId)->where('key', $key)->exists()) {
            $key = "{$baseKey}_{$suffix}";
            $suffix++;
        }

        $nextSortOrder = (int) DB::table('payment_statuses')
            ->where('workspace_id', $workspaceId)
            ->max('sort_order') + 1;

        DB::table('payment_statuses')->insert(array_merge([
            'workspace_id' => $workspaceId,
            'key' => $key,
            'label' => $label,
            'color' => $color,
            'bg' => $bg,
            'sort_order' => $nextSortOrder,
            'is_default' => false,
            'is_deposit_default' => false,
            'is_outstanding' => false,
            'is_paid' => false,
            'is_refunded' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ], $flags));
    }

    public function down(): void
    {
        DB::table('payment_statuses')->where('key', 'refunded')->where('is_refunded', true)->delete();
        DB::table('payment_statuses')->where('key', 'paid')->update(['is_paid' => false]);
    }
};
