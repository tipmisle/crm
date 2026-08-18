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

    public function down(): void
    {
        DB::table('order_items')->delete();
        DB::table('appointment_items')->delete();
    }
};
