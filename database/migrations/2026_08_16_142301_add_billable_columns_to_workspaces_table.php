<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cashier's Billable columns, hand-authored against `workspaces` — the
 * billable entity is the Workspace (the paying business), never the User.
 * Do NOT use Cashier's stock migration, which targets `users`. See
 * docs/billing.md and App\Providers\AppServiceProvider (Cashier::useCustomerModel).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('stripe_id')->nullable()->unique()->after('scheduled_deletion_at');
            $table->string('pm_type')->nullable()->after('stripe_id');
            $table->string('pm_last_four', 4)->nullable()->after('pm_type');
            $table->timestamp('trial_ends_at')->nullable()->after('pm_last_four');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['stripe_id', 'pm_type', 'pm_last_four', 'trial_ends_at']);
        });
    }
};
