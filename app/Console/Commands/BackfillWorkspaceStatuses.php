<?php

namespace App\Console\Commands;

use App\Models\AppointmentStatus;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Models\Workspace;
use App\Services\WorkspaceStatusDefaults;
use Illuminate\Console\Command;

/**
 * Upgrade path for workspaces created before order_statuses/
 * payment_statuses/appointment_statuses existed. Seeds the starter set into
 * any workspace that currently has ZERO rows in a given list —
 * WorkspaceStatusDefaults::seedOrderStatuses()/seedPaymentStatuses()/
 * seedAppointmentStatuses() are independently idempotent, so this never
 * touches a workspace that already has statuses (including one that
 * deleted every starter status on purpose). Existing Order.status/
 * payment_status and Appointment.status/payment_status values are never
 * rewritten — this only adds rows to the status tables.
 */
class BackfillWorkspaceStatuses extends Command
{
    protected $signature = 'workspaces:backfill-statuses';

    protected $description = 'Seed starter order/payment/appointment statuses into any workspace that has none yet.';

    public function handle(): void
    {
        $orderStatusesSeeded = 0;
        $paymentStatusesSeeded = 0;
        $appointmentStatusesSeeded = 0;

        Workspace::query()->orderBy('id')->chunkById(100, function ($workspaces) use (&$orderStatusesSeeded, &$paymentStatusesSeeded, &$appointmentStatusesSeeded) {
            foreach ($workspaces as $workspace) {
                $hadOrderStatuses = OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->exists();
                $hadPaymentStatuses = PaymentStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->exists();
                $hadAppointmentStatuses = AppointmentStatus::withoutGlobalScopes()->where('workspace_id', $workspace->id)->exists();

                WorkspaceStatusDefaults::seed($workspace);

                if (! $hadOrderStatuses) {
                    $orderStatusesSeeded++;
                }

                if (! $hadPaymentStatuses) {
                    $paymentStatusesSeeded++;
                }

                if (! $hadAppointmentStatuses) {
                    $appointmentStatusesSeeded++;
                }
            }
        });

        $this->info("Seeded order statuses for {$orderStatusesSeeded} workspace(s), payment statuses for {$paymentStatusesSeeded} workspace(s), appointment statuses for {$appointmentStatusesSeeded} workspace(s).");
    }
}
