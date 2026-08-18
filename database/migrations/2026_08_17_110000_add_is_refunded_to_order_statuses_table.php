<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Flags a workspace order status as the "refunded" terminal state —
 * distinct from is_cancelled (a refund implies payment was taken and is
 * being returned; a cancellation implies it never was). See
 * OrderController::update() / Orders/Show.vue "Izvedi vračilo".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->boolean('is_refunded')->default(false)->after('is_cancelled');
        });
    }

    public function down(): void
    {
        Schema::table('order_statuses', function (Blueprint $table) {
            $table->dropColumn('is_refunded');
        });
    }
};
