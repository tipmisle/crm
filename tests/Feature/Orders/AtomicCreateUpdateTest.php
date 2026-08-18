<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;

afterEach(function () {
    // Model event listeners registered via a static closure survive past
    // the test that registered them (they're bound to the class, not an
    // instance) — flush them so they never leak into a later test.
    OrderItem::flushEventListeners();
});

test('order creation rolls back the auto-created customer if item creation fails', function () {
    [, $user] = createWorkspaceWithUser();

    $customersBefore = Customer::count();

    OrderItem::creating(function () {
        throw new RuntimeException('Simulated item failure');
    });

    $response = $this->actingAs($user)->post(route('orders.store'), [
        'title' => 'Torta za rojstni dan',
        'customer_name' => 'Nova stranka',
        'items' => [
            ['title' => 'Torta', 'quantity' => 1, 'unit_price' => 40],
        ],
    ]);

    $response->assertStatus(500);

    expect(Customer::count())->toBe($customersBefore);
    expect(Order::count())->toBe(0);
});

test('order update rolls back the price change if replacing the item set fails', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Naročilo',
        'price' => 40,
        'status' => 'new',
    ]);
    $order->items()->create(['title' => 'Izvirni izdelek', 'quantity' => 1, 'unit_price' => 40]);

    OrderItem::creating(function () {
        throw new RuntimeException('Simulated item failure');
    });

    $response = $this->actingAs($user)->patch(route('orders.update', $order), [
        'items' => [
            ['title' => 'Nov izdelek', 'quantity' => 1, 'unit_price' => 100],
        ],
    ]);

    $response->assertStatus(500);

    $order->refresh();
    // Neither the price nor the item set changed — the delete-then-recreate
    // and the price update happen in the same transaction.
    expect((float) $order->price)->toBe(40.0);
    expect($order->items()->count())->toBe(1);
    expect($order->items()->first()->title)->toBe('Izvirni izdelek');
});
