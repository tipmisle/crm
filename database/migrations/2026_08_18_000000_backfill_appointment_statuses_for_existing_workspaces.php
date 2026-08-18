<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off data backfill: any workspace created before appointment_statuses
 * existed (or otherwise created without going through
 * WorkspaceStatusDefaults::seedAppointmentStatuses(), e.g. a workspace
 * created by a script that predates it) has zero appointment_statuses
 * rows. Appointment.status/AppointmentStatus::defaultKey() etc. depend on
 * at least one row existing, so this runs the same starter set through
 * the normal migrate/deploy path rather than relying solely on the
 * operator remembering to run `php artisan workspaces:backfill-statuses`.
 *
 * Safe to re-run and safe on a workspace that already has appointment
 * statuses (including one that deliberately deleted every starter row):
 * only ever inserts into a workspace with ZERO existing appointment_status
 * rows, mirroring WorkspaceStatusDefaults::seedAppointmentStatuses().
 */
return new class extends Migration
{
    public function up(): void
    {
        $appointmentStatuses = [
            ['key' => 'requested', 'label' => 'Povpraševanje', 'color' => '#B45309', 'bg' => '#FEF3C7', 'is_default' => true],
            ['key' => 'confirmed', 'label' => 'Potrjeno', 'color' => '#0E7490', 'bg' => '#E0F7FA'],
            ['key' => 'completed', 'label' => 'Zaključeno', 'color' => '#15803D', 'bg' => '#DCFCE7', 'is_completed' => true],
            ['key' => 'cancelled', 'label' => 'Preklicano', 'color' => '#B91C1C', 'bg' => '#FEE2E2', 'is_cancelled' => true],
            ['key' => 'no_show', 'label' => 'Ni se zglasil/a', 'color' => '#78716C', 'bg' => '#F5F5F4', 'is_no_show' => true],
            ['key' => 'refunded', 'label' => 'Vračilo', 'color' => '#B91C1C', 'bg' => '#FEE2E2', 'is_refunded' => true],
        ];

        $workspaceIds = DB::table('workspaces')->pluck('id');

        foreach ($workspaceIds as $workspaceId) {
            $alreadyHasStatuses = DB::table('appointment_statuses')->where('workspace_id', $workspaceId)->exists();

            if ($alreadyHasStatuses) {
                continue;
            }

            $rows = [];
            foreach ($appointmentStatuses as $i => $status) {
                $rows[] = array_merge([
                    'is_default' => false,
                    'is_completed' => false,
                    'is_cancelled' => false,
                    'is_no_show' => false,
                    'is_refunded' => false,
                ], $status, [
                    'workspace_id' => $workspaceId,
                    'sort_order' => $i,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('appointment_statuses')->insert($rows);
        }
    }

    /**
     * Not reversible in a way that's safe: rows inserted here are
     * indistinguishable in the schema from ones an owner has since edited
     * (renamed/recolored) or that appointments now reference by key —
     * deleting them back out could silently break Appointment.status
     * lookups for real, in-use appointments. Forward-only; see
     * docs/data-lifecycle.md and the sibling backfill migrations for the
     * same pattern.
     */
    public function down(): void
    {
        // Intentionally a no-op — see class docblock.
    }
};
