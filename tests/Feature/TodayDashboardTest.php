<?php

use App\Models\Customer;
use App\Models\Order;

test('the today dashboard renders todays revenue and inquiry stats', function () {
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
        'title' => 'Torta',
        'price' => 60,
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->component('Today')
        ->where('stats.revenue.value', 60)
        ->has('stats.inquiries.value')
        ->where('stats.bookings.value', 1)
        ->has('stats.conversionRate.value')
    );
});
