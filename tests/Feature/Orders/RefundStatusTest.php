<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\RevenueStatsService;

test('a workspace has a default order status flagged as refunded', function () {
    [$workspace] = createWorkspaceWithUser();

    $refunded = OrderStatus::where('workspace_id', $workspace->id)->where('is_refunded', true)->first();

    expect($refunded)->not->toBeNull();
    expect($refunded->key)->toBe('refunded');
});

test('marking an order as refunded excludes it from open-order queries', function () {
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
        'title' => 'Torta',
        'price' => 60,
        'status' => 'confirmed',
    ]);

    $refundedKey = OrderStatus::where('workspace_id', $workspace->id)->where('is_refunded', true)->value('key');

    $this->actingAs($user)->patch(route('orders.update', $order), ['status' => $refundedKey])->assertRedirect();

    expect(OrderStatus::openExclusionKeys())->toContain($refundedKey);
    expect($customer->openOrdersCount())->toBe(0);
});

test('a refunded order is excluded from revenue stats like a cancelled one', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $refundedKey = OrderStatus::where('workspace_id', $workspace->id)->where('is_refunded', true)->value('key');

    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Torta',
        'price' => 100,
        'status' => $refundedKey,
    ]);

    $stats = app(RevenueStatsService::class);
    $revenue = $stats->totalRevenue($workspace, now()->subDay(), now()->addDay());

    expect($revenue)->toBe(0.0);
});
