<?php

use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

test('rolling back the order/appointment item backfill migration does not delete multi-item data created afterwards', function () {
    [$workspace] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Naročilo z več izdelki',
        'price' => 90,
        'status' => 'new',
    ]);

    // Real, user-created multi-item data — added long after the backfill
    // migration ran, exactly the kind of row its old `down()` used to wipe
    // out with a blanket delete().
    $order->items()->createMany([
        ['title' => 'Izdelek A', 'quantity' => 1, 'unit_price' => 30],
        ['title' => 'Izdelek B', 'quantity' => 2, 'unit_price' => 30],
    ]);

    $migration = require database_path('migrations/2026_08_17_190403_backfill_order_and_appointment_items.php');
    $migration->down();

    expect(DB::table('order_items')->where('order_id', $order->id)->count())->toBe(2);
});
