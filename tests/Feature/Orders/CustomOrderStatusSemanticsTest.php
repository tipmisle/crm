<?php

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;

test('a renamed cancelled status key is still excluded from open-order counts and links', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    // Simulate a workspace that renamed its cancelled status away from the
    // seeded 'cancelled' key — open/closed semantics must follow the
    // is_cancelled flag, not the literal key.
    $cancelledStatus = OrderStatus::where('workspace_id', $workspace->id)->where('is_cancelled', true)->first();
    $cancelledStatus->update(['key' => 'zavrnjeno', 'label' => 'Zavrnjeno']);

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
        'due_date' => today(),
    ]);

    $this->actingAs($user)->patch(route('orders.update', $order), ['status' => 'zavrnjeno'])->assertRedirect();

    expect(OrderStatus::openExclusionKeys())->toContain('zavrnjeno');
    expect($customer->openOrdersCount())->toBe(0);

    $filtered = $this->actingAs($user)->get(route('orders.index', ['status_scope' => 'open']));
    $filtered->assertInertia(fn ($page) => $page->has('orders.data', 0));
});

test('the inbox customer context excludes an order whose custom status is flagged closed', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $customStatus = OrderStatus::create([
        'workspace_id' => $workspace->id,
        'key' => 'archived',
        'label' => 'Arhivirano',
        'color' => '#000000',
        'bg' => '#FFFFFF',
        'sort_order' => 50,
        'is_completed' => true,
    ]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $channel = createMetaChannel($workspace);
    $conversation = Conversation::create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'customer_id' => $customer->id,
        'external_conversation_id' => 'sender_archived',
        'status' => 'order_confirmed',
    ]);

    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Staro naročilo',
        'price' => 40,
        'status' => $customStatus->key,
    ]);

    $response = $this->actingAs($user)->get(route('inbox.show', $conversation));

    $response->assertInertia(fn ($page) => $page->where('conversation.customer.current_open_order', null));
});
