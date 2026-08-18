<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;

test('a product can be created', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $this->actingAs($user)->post(route('products.store'), [
        'name' => 'Rojstnodnevna torta',
        'default_price' => 85,
        'default_deposit_amount' => 20,
    ])->assertRedirect();

    $product = Product::first();
    expect($product)->not->toBeNull();
    expect($product->workspace_id)->toBe($workspace->id);
    expect($product->type)->toBe('product');
    expect((float) $product->default_price)->toBe(85.0);
});

test('a service can be created', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    $this->actingAs($user)->post(route('services.store'), [
        'name' => 'Gel manikura',
        'default_duration_minutes' => 60,
        'default_price' => 35,
    ])->assertRedirect();

    $service = Service::first();
    expect($service)->not->toBeNull();
    expect($service->type)->toBe('service');
    expect($service->default_duration_minutes)->toBe(60);
});

test('catalog items are isolated per workspace', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $productB = Product::create([
        'workspace_id' => $workspaceB->id,
        'name' => 'Torta B',
        'active' => true,
    ]);

    $this->actingAs($userA)
        ->patch(route('products.update', $productB->id), ['name' => 'Hijacked'])
        ->assertStatus(404);
});

test('only active products appear when creating an order', function () {
    [, $user] = createWorkspaceWithUser();
    $this->actingAs($user);

    $active = Product::create(['name' => 'Aktiven produkt', 'active' => true]);
    $inactive = Product::create(['name' => 'Neaktiven produkt', 'active' => false]);

    $response = $this->get(route('orders.create'));

    $response->assertInertia(fn ($page) => $page
        ->has('products', 1)
        ->where('products.0.id', $active->id)
    );

    expect(Product::find($inactive->id))->not->toBeNull();
});

test('only active services appear when creating an appointment', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    $this->actingAs($user);

    $active = Service::create(['name' => 'Aktivna storitev', 'default_duration_minutes' => 60, 'active' => true]);
    $inactive = Service::create(['name' => 'Neaktivna storitev', 'default_duration_minutes' => 60, 'active' => false]);

    $response = $this->get(route('appointments.create'));

    $response->assertInertia(fn ($page) => $page
        ->has('services', 1)
        ->where('services.0.id', $active->id)
    );

    expect(Service::find($inactive->id))->not->toBeNull();
});

test('a product never appears as a service and vice versa', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    $this->actingAs($user);

    $product = Product::create(['name' => 'Torta', 'active' => true]);
    $service = Service::create(['name' => 'Manikura', 'default_duration_minutes' => 60, 'active' => true]);

    expect(Product::pluck('id'))->not->toContain($service->id);
    expect(Service::pluck('id'))->not->toContain($product->id);

    // Route model binding must respect the type scope too.
    $this->patch(route('services.update', $product->id), ['name' => 'x'])->assertStatus(404);
    $this->patch(route('products.update', $service->id), ['name' => 'x'])->assertStatus(404);
});

test('selecting a product copies its default price and deposit into a new order', function () {
    [, $user] = createWorkspaceWithUser();
    $this->actingAs($user);

    $customer = Customer::create([
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $product = Product::create([
        'name' => 'Rojstnodnevna torta',
        'default_price' => 85,
        'default_deposit_amount' => 20,
        'active' => true,
    ]);

    $this->post(route('orders.store'), [
        'title' => $product->name,
        'customer_id' => $customer->id,
        'deposit_amount' => $product->default_deposit_amount,
        'items' => [
            ['catalog_item_id' => $product->id, 'title' => $product->name, 'quantity' => 1, 'unit_price' => $product->default_price],
        ],
    ])->assertRedirect();

    $order = Order::first();
    expect($order->items->first()->catalog_item_id)->toBe($product->id);
    expect((float) $order->price)->toBe(85.0);
    expect((float) $order->deposit_amount)->toBe(20.0);
});

test('selecting a service copies its duration, price and deposit into a new appointment', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    $this->actingAs($user);

    $customer = Customer::create([
        'full_name' => 'Zala Ferlan',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $service = Service::create([
        'name' => 'BIAB nadgradnja',
        'default_duration_minutes' => 75,
        'default_price' => 42,
        'default_deposit_amount' => 10,
        'active' => true,
    ]);

    $this->post(route('appointments.store'), [
        'service_name' => $service->name,
        'customer_id' => $customer->id,
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => $service->default_duration_minutes,
        'deposit_amount' => $service->default_deposit_amount,
        'items' => [
            ['catalog_item_id' => $service->id, 'title' => $service->name, 'quantity' => 1, 'unit_price' => $service->default_price],
        ],
    ])->assertRedirect();

    $appointment = Appointment::first();
    expect($appointment->items->first()->catalog_item_id)->toBe($service->id);
    expect($appointment->duration_minutes)->toBe(75);
    expect((float) $appointment->price)->toBe(42.0);
    expect((float) $appointment->deposit_amount)->toBe(10.0);
});

test('an order price manually overridden away from the catalog default stays independent', function () {
    [, $user] = createWorkspaceWithUser();
    $this->actingAs($user);

    $customer = Customer::create([
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $product = Product::create(['name' => 'Torta', 'default_price' => 85, 'active' => true]);

    $this->post(route('orders.store'), [
        'title' => $product->name,
        'customer_id' => $customer->id,
        'items' => [
            // owner bumped the unit price for this specific order
            ['catalog_item_id' => $product->id, 'title' => $product->name, 'quantity' => 1, 'unit_price' => 95],
        ],
    ])->assertRedirect();

    $order = Order::first();
    expect((float) $order->price)->toBe(95.0);
    expect((float) $product->fresh()->default_price)->toBe(85.0);
});

test('changing a catalog item default price does not alter existing orders or appointments', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    $this->actingAs($user);

    $customer = Customer::create([
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $product = Product::create(['name' => 'Torta', 'default_price' => 85, 'active' => true]);
    $service = Service::create(['name' => 'Manikura', 'default_duration_minutes' => 60, 'default_price' => 35, 'active' => true]);

    $this->post(route('orders.store'), [
        'title' => $product->name,
        'customer_id' => $customer->id,
        'items' => [
            ['catalog_item_id' => $product->id, 'title' => $product->name, 'quantity' => 1, 'unit_price' => $product->default_price],
        ],
    ])->assertRedirect();

    $this->post(route('appointments.store'), [
        'service_name' => $service->name,
        'customer_id' => $customer->id,
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '09:00',
        'duration_minutes' => $service->default_duration_minutes,
        'items' => [
            ['catalog_item_id' => $service->id, 'title' => $service->name, 'quantity' => 1, 'unit_price' => $service->default_price],
        ],
    ])->assertRedirect();

    $order = Order::first();
    $appointment = Appointment::first();

    $product->update(['default_price' => 120]);
    $service->update(['default_price' => 45]);

    expect((float) $order->fresh()->price)->toBe(85.0);
    expect((float) $appointment->fresh()->price)->toBe(35.0);
});

test('deactivating or deleting a catalog item does not break historical orders or appointments', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    $this->actingAs($user);

    $customer = Customer::create([
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $product = Product::create(['name' => 'Torta', 'default_price' => 85, 'active' => true]);
    $service = Service::create(['name' => 'Manikura', 'default_duration_minutes' => 60, 'default_price' => 35, 'active' => true]);

    $this->post(route('orders.store'), [
        'title' => $product->name,
        'customer_id' => $customer->id,
        'items' => [
            ['catalog_item_id' => $product->id, 'title' => $product->name, 'quantity' => 1, 'unit_price' => $product->default_price],
        ],
    ])->assertRedirect();

    $this->post(route('appointments.store'), [
        'service_name' => $service->name,
        'customer_id' => $customer->id,
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '09:00',
        'duration_minutes' => $service->default_duration_minutes,
        'items' => [
            ['catalog_item_id' => $service->id, 'title' => $service->name, 'quantity' => 1, 'unit_price' => $service->default_price],
        ],
    ])->assertRedirect();

    $order = Order::first();
    $appointment = Appointment::first();

    $product->update(['active' => false]);
    $service->update(['active' => false]);

    expect($order->fresh()->title)->toBe('Torta');
    expect((float) $order->fresh()->price)->toBe(85.0);
    expect($appointment->fresh()->service_name)->toBe('Manikura');

    $product->delete();
    $service->delete();

    expect($order->fresh()->items->first()->catalog_item_id)->toBeNull();
    expect($order->fresh()->title)->toBe('Torta');
    expect($order->fresh()->price)->not->toBeNull();
    expect($appointment->fresh()->items->first()->catalog_item_id)->toBeNull();
    expect($appointment->fresh()->service_name)->toBe('Manikura');
});

test('the Ponudba catalog page only exposes products for an order-only workspace', function () {
    [, $user] = createWorkspaceWithUser();
    $this->actingAs($user);

    Product::create(['name' => 'Torta', 'active' => true]);

    // orders_enabled defaults true, appointments_enabled defaults false.
    $this->get(route('catalog.index'))->assertInertia(fn ($page) => $page
        ->has('products', 1)
        ->where('services', [])
    );
});

test('the Ponudba catalog page only exposes services for an appointment-only workspace', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['orders_enabled' => false, 'appointments_enabled' => true]);

    $this->actingAs($user);

    Service::create(['name' => 'Manikura', 'default_duration_minutes' => 60, 'active' => true]);

    $this->get(route('catalog.index'))->assertInertia(fn ($page) => $page
        ->where('products', [])
        ->has('services', 1)
    );
});

test('opening the order create page from a product preselects it', function () {
    [, $user] = createWorkspaceWithUser();
    $this->actingAs($user);

    $product = Product::create(['name' => 'Rojstnodnevna torta', 'default_price' => 85, 'active' => true]);

    $this->get(route('orders.create', ['product_id' => $product->id]))
        ->assertInertia(fn ($page) => $page
            ->component('Orders/Create')
            ->where('selectedProductId', $product->id)
        );
});

test('opening the appointment create page from a service preselects it', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    $this->actingAs($user);

    $service = Service::create(['name' => 'Gel manikura', 'default_duration_minutes' => 60, 'active' => true]);

    $this->get(route('appointments.create', ['service_id' => $service->id]))
        ->assertInertia(fn ($page) => $page
            ->component('Appointments/Create')
            ->where('selectedServiceId', $service->id)
        );
});

test('an order linked to a catalog product exposes it for the "Produkt" link on the order detail page', function () {
    [, $user] = createWorkspaceWithUser();
    $this->actingAs($user);

    $customer = Customer::create([
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);
    $product = Product::create(['name' => 'Rojstnodnevna torta', 'default_price' => 85, 'active' => true]);

    $order = Order::create([
        'customer_id' => $customer->id,
        'title' => $product->name,
        'price' => 85,
        'status' => 'confirmed',
    ]);
    $order->items()->create(['catalog_item_id' => $product->id, 'title' => $product->name, 'quantity' => 1, 'unit_price' => 85]);

    $this->get(route('orders.show', $order))
        ->assertInertia(fn ($page) => $page->where('order.items.0.product.id', $product->id));
});
