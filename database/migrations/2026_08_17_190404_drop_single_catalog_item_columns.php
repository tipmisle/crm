<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * catalog_item_id (orders) / service_id (appointments) are now redundant —
 * every order/appointment's product/service data lives in
 * order_items/appointment_items (see the backfill migration right before
 * this one). title/service_name stay: they're the order/appointment's own
 * label, independent of which items it contains.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('catalog_item_id');
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_id');
        });
    }

    /**
     * Schema-only rollback: the columns come back, but empty. The values
     * that used to live in them were already copied into order_items/
     * appointment_items by the backfill migration before this one, which
     * is itself forward-only (see its own down()) — so there is no source
     * left to repopulate catalog_item_id/service_id from. Do not treat a
     * rollback of this migration as restoring the old single-item data
     * model; it only restores the columns' presence.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('catalog_item_id')->nullable()->after('channel_id')->constrained('catalog_items')->nullOnDelete();
        });

        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('service_id')->nullable()->after('assigned_user_id')->constrained('catalog_items')->nullOnDelete();
        });
    }
};
