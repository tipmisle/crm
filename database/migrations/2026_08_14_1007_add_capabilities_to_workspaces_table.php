<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            // Flexible per-feature flags (not a single mode enum) so a
            // workspace can run orders, appointments, or both at once.
            $table->boolean('orders_enabled')->default(true)->after('currency');
            $table->boolean('appointments_enabled')->default(false)->after('orders_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['orders_enabled', 'appointments_enabled']);
        });
    }
};
