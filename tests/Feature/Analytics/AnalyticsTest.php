<?php

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;

test('the analytics page renders revenue, inquiries and channel breakdowns', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace, 'instagram', 'ig_analytics');

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'primary_channel_id' => $channel->id,
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    Conversation::create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'customer_id' => $customer->id,
        'customer_display_name' => $customer->full_name,
        'status' => 'order_confirmed',
    ]);

    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'channel_id' => $channel->id,
        'title' => 'Rojstnodnevna torta',
        'price' => 85,
        'deposit_amount' => 0,
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($user)->get(route('analytics.index'));

    $response->assertInertia(fn ($page) => $page
        ->component('Analytics/Index')
        ->where('range.from', now()->subDays(29)->toDateString())
        ->where('range.to', now()->toDateString())
        ->where('stats.revenue.value', 85)
        ->where('stats.inquiries.value', 1)
        ->has('revenueSeries', 30)
        ->has('channelInquiries', 1)
        ->where('channelInquiries.0.label', 'Instagram')
        ->has('topProducts', 1)
        ->where('topProducts.0.name', 'Rojstnodnevna torta')
    );
});

test('analytics revenue excludes cancelled orders and is scoped to the workspace', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $customerA = Customer::create([
        'workspace_id' => $workspaceA->id,
        'full_name' => 'Stranka A',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    Order::create([
        'workspace_id' => $workspaceA->id,
        'customer_id' => $customerA->id,
        'title' => 'Preklicano naročilo',
        'price' => 999,
        'status' => 'cancelled',
    ]);

    $customerB = Customer::create([
        'workspace_id' => $workspaceB->id,
        'full_name' => 'Stranka B',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    Order::create([
        'workspace_id' => $workspaceB->id,
        'customer_id' => $customerB->id,
        'title' => 'Tuje naročilo',
        'price' => 500,
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($userA)->get(route('analytics.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('stats.revenue.value', 0)
        ->where('topProducts', [])
    );
});

test('an unauthenticated user cannot view analytics', function () {
    $this->get(route('analytics.index'))->assertRedirect(route('login'));
});

test('analytics can be filtered to an arbitrary custom date range', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    // Inside the requested range.
    $inRange = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Naročilo v obdobju',
        'price' => 50,
        'status' => 'confirmed',
    ]);
    $inRange->forceFill(['created_at' => '2026-06-15'])->saveQuietly();

    // Outside the requested range — must not be counted.
    $outOfRange = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Naročilo izven obdobja',
        'price' => 999,
        'status' => 'confirmed',
    ]);
    $outOfRange->forceFill(['created_at' => '2026-01-01'])->saveQuietly();

    $response = $this->actingAs($user)->get(route('analytics.index', ['from' => '2026-06-01', 'to' => '2026-06-30']));

    $response->assertInertia(fn ($page) => $page
        ->where('range.from', '2026-06-01')
        ->where('range.to', '2026-06-30')
        ->where('stats.revenue.value', 50)
        ->has('revenueSeries', 30)
        ->has('topProducts', 1)
        ->where('topProducts.0.name', 'Naročilo v obdobju')
    );
});

test('analytics can be filtered to a single day', function () {
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
        'title' => 'Naročilo na en dan',
        'price' => 40,
        'status' => 'confirmed',
    ]);
    $order->forceFill(['created_at' => '2026-06-15 10:00:00'])->saveQuietly();

    $response = $this->actingAs($user)->get(route('analytics.index', ['from' => '2026-06-15', 'to' => '2026-06-15']));

    $response->assertInertia(fn ($page) => $page
        ->where('range.from', '2026-06-15')
        ->where('range.to', '2026-06-15')
        ->has('revenueSeries', 1)
        ->where('stats.revenue.value', 40)
    );
});

test('comparing with the previous period computes a delta against the equally long prior range', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    // Current 7-day window: 2026-06-09 .. 2026-06-15.
    $current = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Trenutno naročilo',
        'price' => 100,
        'status' => 'confirmed',
    ]);
    $current->forceFill(['created_at' => '2026-06-12'])->saveQuietly();

    // Previous 7-day window: 2026-06-02 .. 2026-06-08.
    $previous = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Prejšnje naročilo',
        'price' => 50,
        'status' => 'confirmed',
    ]);
    $previous->forceFill(['created_at' => '2026-06-05'])->saveQuietly();

    $response = $this->actingAs($user)->get(route('analytics.index', [
        'from' => '2026-06-09', 'to' => '2026-06-15', 'compare' => 'previous',
    ]));

    $response->assertInertia(fn ($page) => $page
        ->where('compare.key', 'previous')
        ->where('compare.range.from', '2026-06-02')
        ->where('compare.range.to', '2026-06-08')
        ->where('stats.revenue.value', 100)
        ->where('stats.revenue.delta', 100) // 100 vs 50 = +100%
        ->has('compareRevenueSeries', 7)
    );
});

test('comparing with last year shifts the compare range back exactly one year', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $response = $this->actingAs($user)->get(route('analytics.index', [
        'from' => '2026-06-09', 'to' => '2026-06-15', 'compare' => 'year',
    ]));

    $response->assertInertia(fn ($page) => $page
        ->where('compare.key', 'year')
        ->where('compare.range.from', '2025-06-09')
        ->where('compare.range.to', '2025-06-15')
    );
});

test('compare=none skips comparison entirely and returns null deltas', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Naročilo',
        'price' => 40,
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($user)->get(route('analytics.index', ['compare' => 'none']));

    $response->assertInertia(fn ($page) => $page
        ->where('compare.key', 'none')
        ->where('compare.range', null)
        ->where('compare.label', null)
        ->where('stats.revenue.delta', null)
        ->where('compareRevenueSeries', null)
    );
});

test('bookings stat counts orders and appointments created in the range', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Naročilo ena',
        'price' => 10,
        'status' => 'confirmed',
    ]);
    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Naročilo dve — celo preklicano se šteje',
        'price' => 10,
        'status' => 'cancelled',
    ]);

    $response = $this->actingAs($user)->get(route('analytics.index'));

    $response->assertInertia(fn ($page) => $page->where('stats.bookings.value', 2));
});
