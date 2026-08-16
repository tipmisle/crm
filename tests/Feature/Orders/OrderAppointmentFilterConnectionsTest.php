<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;

test('the catalog_item_id order filter is scoped to the current workspace', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $productB = Product::create(['workspace_id' => $workspaceB->id, 'name' => 'Torta B', 'active' => true]);

    $customerB = Customer::create([
        'workspace_id' => $workspaceB->id,
        'full_name' => 'Stranka B',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    Order::create([
        'workspace_id' => $workspaceB->id,
        'customer_id' => $customerB->id,
        'catalog_item_id' => $productB->id,
        'title' => $productB->name,
        'price' => 50,
        'status' => 'confirmed',
    ]);

    // Workspace A has no such catalog item at all — filtering by workspace
    // B's product id must never leak workspace B's order into A's results.
    $response = $this->actingAs($userA)->get(route('orders.index', ['catalog_item_id' => $productB->id]));

    $response->assertInertia(fn ($page) => $page->has('orders.data', 0));
});

test('the service_id appointment filter is scoped to the current workspace', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    $workspaceA->update(['appointments_enabled' => true]);
    [$workspaceB] = createWorkspaceWithUser();
    $workspaceB->update(['appointments_enabled' => true]);

    $serviceB = Service::create(['workspace_id' => $workspaceB->id, 'name' => 'Manikura B', 'default_duration_minutes' => 60, 'active' => true]);

    $customerB = Customer::create([
        'workspace_id' => $workspaceB->id,
        'full_name' => 'Stranka B',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    Appointment::create([
        'workspace_id' => $workspaceB->id,
        'customer_id' => $customerB->id,
        'service_id' => $serviceB->id,
        'service_name' => $serviceB->name,
        'appointment_date' => now(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($userA)->get(route('appointments.index', ['service_id' => $serviceB->id]));

    $response->assertInertia(fn ($page) => $page->has('appointments.data', 0));
});

test('a channel_type filter narrows both the count and the visible list to that channel', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $instagram = createMetaChannel($workspace, 'instagram');

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $matching = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'channel_id' => $instagram->id,
        'title' => 'Preko Instagrama',
        'price' => 30,
        'status' => 'confirmed',
    ]);

    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Brez kanala',
        'price' => 30,
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($user)->get(route('orders.index', ['channel_type' => 'instagram']));

    $response->assertInertia(fn ($page) => $page
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $matching->id)
    );
});

test('switching between the order list, kanban and calendar views preserves the active filters', function () {
    [, $user] = createWorkspaceWithUser();
    $this->actingAs($user);

    $product = Product::create(['name' => 'Torta', 'active' => true]);

    $listResponse = $this->get(route('orders.index', ['catalog_item_id' => $product->id, 'status_scope' => 'open']));
    $listResponse->assertInertia(fn ($page) => $page
        ->where('filters.catalog_item_id', (string) $product->id)
        ->where('filters.status_scope', 'open')
    );

    $kanbanResponse = $this->get(route('orders.index', ['catalog_item_id' => $product->id, 'status_scope' => 'open', 'view' => 'kanban']));
    $kanbanResponse->assertInertia(fn ($page) => $page
        ->where('filters.catalog_item_id', (string) $product->id)
        ->where('filters.status_scope', 'open')
    );

    $calendarResponse = $this->get(route('orders.index', ['catalog_item_id' => $product->id, 'status_scope' => 'open', 'view' => 'calendar']));
    $calendarResponse->assertInertia(fn ($page) => $page
        ->where('filters.catalog_item_id', (string) $product->id)
        ->where('filters.status_scope', 'open')
    );
});
