<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off data backfill: orders/appointments used to carry a single
 * catalog_item_id + price (see the columns dropped in the migration right
 * after this one). This copies that into one order_items/appointment_items
 * row per existing order/appointment before the columns disappear. Safe to
 * re-run: skips any order/appointment that already has an item row.
 */
return new class extends Migration
{
    public function up(): void
    {
        $orders = DB::table('orders')->select('id', 'catalog_item_id', 'title', 'price')->get();

        foreach ($orders as $order) {
            $hasItems = DB::table('order_items')->where('order_id', $order->id)->exists();

            if ($hasItems) {
                continue;
            }

            DB::table('order_items')->insert([
                'order_id' => $order->id,
                'catalog_item_id' => $order->catalog_item_id,
                'title' => $order->title,
                'quantity' => 1,
                'unit_price' => $order->price,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $appointments = DB::table('appointments')->select('id', 'service_id', 'service_name', 'price')->get();

        foreach ($appointments as $appointment) {
            $hasItems = DB::table('appointment_items')->where('appointment_id', $appointment->id)->exists();

            if ($hasItems) {
                continue;
            }

            DB::table('appointment_items')->insert([
                'appointment_id' => $appointment->id,
                'catalog_item_id' => $appointment->service_id,
                'title' => $appointment->service_name,
                'quantity' => 1,
                'unit_price' => $appointment->price ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Not reversible: by the time a rollback could run, order_items/
     * appointment_items may contain rows this migration never created —
     * real, user-created multi-item orders/appointments added after this
     * migration ran (each order/appointment can now hold several items,
     * where before it held at most one). A `delete()` here would silently
     * destroy that data, and there is no reliable way to distinguish "a
     * row this backfill inserted" from "a row a user added afterwards"
     * once both exist in the same table. Forward-only: rolling back to
     * the single-item schema is not supported past this point. If you
     * genuinely need to undo the multi-item migration, restore from a
     * backup taken before it ran instead of rolling back live data.
     */
    public function down(): void
    {
        // Intentionally a no-op — see method docblock.
    }
};
