<?php

namespace App\Services;

use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Models\Workspace;

/**
 * Seeds a workspace's initial, fully-editable order/payment status lists —
 * called once, right after a Workspace is created (real registration, demo
 * creation, and standalone dev seeders alike). The values mirror what used
 * to be the fixed App\Enums\OrderStatus/PaymentStatus cases, so existing
 * behavior is unchanged until an owner actually edits their lists in
 * Settings.
 *
 * Idempotent per list: each of seedOrderStatuses()/seedPaymentStatuses()
 * only inserts the starter set when the workspace has ZERO rows in that
 * list. A workspace that already has any order (or payment) statuses —
 * including one that deliberately deleted every starter row — is left
 * alone, independently per list. This makes seed() safe to call again on
 * an existing workspace (e.g. a backfill for workspaces created before
 * these tables existed) without resurrecting anything the owner removed.
 */
class WorkspaceStatusDefaults
{
    public static function seed(Workspace $workspace): void
    {
        static::seedOrderStatuses($workspace);
        static::seedPaymentStatuses($workspace);
    }

    public static function seedOrderStatuses(Workspace $workspace): void
    {
        if (OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->exists()) {
            return;
        }

        $orderStatuses = [
            ['key' => 'new', 'label' => 'Novo', 'color' => '#4B5563', 'bg' => '#F1F2F4', 'is_default' => true],
            ['key' => 'quote_needed', 'label' => 'Potrebna ponudba', 'color' => '#B45309', 'bg' => '#FEF3C7'],
            ['key' => 'quote_sent', 'label' => 'Ponudba poslana', 'color' => '#9D5CD1', 'bg' => '#F3E8FF'],
            ['key' => 'waiting_for_customer', 'label' => 'Čaka na stranko', 'color' => '#B45309', 'bg' => '#FEF3C7'],
            ['key' => 'confirmed', 'label' => 'Potrjeno', 'color' => '#0E7490', 'bg' => '#E0F7FA'],
            ['key' => 'in_progress', 'label' => 'V izdelavi', 'color' => '#6A3CCB', 'bg' => '#EBE5FD'],
            ['key' => 'ready', 'label' => 'Pripravljeno', 'color' => '#0F766E', 'bg' => '#DBF6EF'],
            ['key' => 'completed', 'label' => 'Zaključeno', 'color' => '#15803D', 'bg' => '#DCFCE7', 'is_completed' => true],
            ['key' => 'cancelled', 'label' => 'Preklicano', 'color' => '#B91C1C', 'bg' => '#FEE2E2', 'is_cancelled' => true],
        ];

        foreach ($orderStatuses as $i => $status) {
            OrderStatus::create(array_merge($status, [
                'workspace_id' => $workspace->id,
                'sort_order' => $i,
            ]));
        }
    }

    public static function seedPaymentStatuses(Workspace $workspace): void
    {
        if (PaymentStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->exists()) {
            return;
        }

        $paymentStatuses = [
            ['key' => 'unpaid', 'label' => 'Neplačano', 'color' => '#B91C1C', 'bg' => '#FEE2E2', 'is_default' => true, 'is_outstanding' => true],
            ['key' => 'deposit_due', 'label' => 'Čaka se ara', 'color' => '#B45309', 'bg' => '#FEF3C7', 'is_deposit_default' => true, 'is_outstanding' => true],
            ['key' => 'deposit_paid', 'label' => 'Ara plačana', 'color' => '#0E7490', 'bg' => '#E0F7FA'],
            ['key' => 'partially_paid', 'label' => 'Delno plačano', 'color' => '#6A3CCB', 'bg' => '#EBE5FD'],
            ['key' => 'paid', 'label' => 'Plačano', 'color' => '#15803D', 'bg' => '#DCFCE7'],
        ];

        foreach ($paymentStatuses as $i => $status) {
            PaymentStatus::create(array_merge($status, [
                'workspace_id' => $workspace->id,
                'sort_order' => $i,
            ]));
        }
    }
}
