<?php

use App\Models\Customer;
use App\Models\Order;

test('a crafted DELETE request cannot destroy a customer via the removed customers.destroy route', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana']);

    $this->actingAs($owner)->delete("/customers/{$customer->id}")->assertStatus(405);

    expect(Customer::find($customer->id))->not->toBeNull();
});

test('a crafted DELETE request cannot destroy a customer that has operational orders', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($owner)->delete("/customers/{$order->customer_id}")->assertStatus(405);

    expect(Order::find($order->id))->not->toBeNull();
});
