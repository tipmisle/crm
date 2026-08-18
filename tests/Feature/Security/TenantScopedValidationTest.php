<?php

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Models\Product;
use App\Models\Service;

/*
|--------------------------------------------------------------------------
| Order/Appointment status + payment_status validation
|--------------------------------------------------------------------------
*/

test('an arbitrary custom order status key is accepted when it exists in the current workspace', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    $custom = OrderStatus::create([
        'workspace_id' => $workspace->id,
        'key' => 'awaiting_supplier',
        'label' => 'Čaka dobavitelja',
        'color' => '#000000',
        'bg' => '#FFFFFF',
        'sort_order' => 99,
    ]);

    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($owner)
        ->patch(route('orders.update', $order), ['status' => $custom->key])
        ->assertRedirect();

    expect($order->fresh()->status)->toBe('awaiting_supplier');
});

test('a nonexistent order status key is rejected', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($owner)
        ->patch(route('orders.update', $order), ['status' => 'not_a_real_status'])
        ->assertSessionHasErrors('status');

    expect($order->fresh()->status)->not->toBe('not_a_real_status');
});

test('a nonexistent payment status key is rejected on order update', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($owner)
        ->patch(route('orders.update', $order), ['payment_status' => 'not_a_real_payment_status'])
        ->assertSessionHasErrors('payment_status');
});

test('another workspaces order status key is rejected even though it exists in the database', function () {
    [$workspaceA, $ownerA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    [$order] = createOrderWithConversation($workspaceA);

    // A custom key that only exists in workspace B — the seeded starter
    // keys ("confirmed", "paid", ...) exist independently in every
    // workspace, so a distinct custom key is needed to prove cross-
    // workspace isolation rather than mere key-string overlap.
    $crossWorkspaceStatus = OrderStatus::create([
        'workspace_id' => $workspaceB->id,
        'key' => 'only_in_workspace_b',
        'label' => 'Only in B',
        'color' => '#000000',
        'bg' => '#FFFFFF',
        'sort_order' => 99,
    ]);

    $this->actingAs($ownerA)
        ->patch(route('orders.update', $order), ['status' => $crossWorkspaceStatus->key])
        ->assertSessionHasErrors('status');

    expect($order->fresh()->status)->not->toBe($crossWorkspaceStatus->key);
});

test('another workspaces payment status key is rejected on order update', function () {
    [$workspaceA, $ownerA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    [$order] = createOrderWithConversation($workspaceA);

    $crossWorkspaceStatus = PaymentStatus::create([
        'workspace_id' => $workspaceB->id,
        'key' => 'only_in_workspace_b',
        'label' => 'Only in B',
        'color' => '#000000',
        'bg' => '#FFFFFF',
        'sort_order' => 99,
    ]);

    $this->actingAs($ownerA)
        ->patch(route('orders.update', $order), ['payment_status' => $crossWorkspaceStatus->key])
        ->assertSessionHasErrors('payment_status');
});

test('appointment status only accepts the workspaces appointment status keys', function () {
    [$workspaceA, $owner] = createWorkspaceWithUser(['current_workspace_id' => null]);
    $workspaceA->update(['appointments_enabled' => true]);
    $owner->update(['current_workspace_id' => $workspaceA->id]);

    $customer = Customer::create(['workspace_id' => $workspaceA->id, 'full_name' => 'Ana']);
    $appointment = Appointment::create([
        'workspace_id' => $workspaceA->id,
        'customer_id' => $customer->id,
        'service_name' => 'Cut',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 30,
        'price' => 20,
        'status' => 'requested',
        'payment_status' => 'unpaid',
    ]);

    $this->actingAs($owner)
        ->patch(route('appointments.update', $appointment), ['status' => 'not_a_real_status'])
        ->assertSessionHasErrors('status');

    $this->actingAs($owner)
        ->patch(route('appointments.update', $appointment), ['status' => 'confirmed'])
        ->assertRedirect();

    expect($appointment->fresh()->status)->toBe('confirmed');
});

test('another workspaces payment status key is rejected on appointment update', function () {
    [$workspaceA, $ownerA] = createWorkspaceWithUser(['current_workspace_id' => null]);
    $workspaceA->update(['appointments_enabled' => true]);
    $ownerA->update(['current_workspace_id' => $workspaceA->id]);

    [$workspaceB] = createWorkspaceWithUser();

    $customer = Customer::create(['workspace_id' => $workspaceA->id, 'full_name' => 'Ana']);
    $appointment = Appointment::create([
        'workspace_id' => $workspaceA->id,
        'customer_id' => $customer->id,
        'service_name' => 'Cut',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 30,
        'price' => 20,
        'status' => 'requested',
        'payment_status' => 'unpaid',
    ]);

    $crossWorkspaceStatus = PaymentStatus::create([
        'workspace_id' => $workspaceB->id,
        'key' => 'only_in_workspace_b',
        'label' => 'Only in B',
        'color' => '#000000',
        'bg' => '#FFFFFF',
        'sort_order' => 99,
    ]);

    $this->actingAs($ownerA)
        ->patch(route('appointments.update', $appointment), ['payment_status' => $crossWorkspaceStatus->key])
        ->assertSessionHasErrors('payment_status');
});

test('another workspaces appointment status key is rejected on appointment update', function () {
    [$workspaceA, $ownerA] = createWorkspaceWithUser(['current_workspace_id' => null]);
    $workspaceA->update(['appointments_enabled' => true]);
    $ownerA->update(['current_workspace_id' => $workspaceA->id]);

    [$workspaceB] = createWorkspaceWithUser();

    $customer = Customer::create(['workspace_id' => $workspaceA->id, 'full_name' => 'Ana']);
    $appointment = Appointment::create([
        'workspace_id' => $workspaceA->id,
        'customer_id' => $customer->id,
        'service_name' => 'Cut',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 30,
        'price' => 20,
        'status' => 'requested',
        'payment_status' => 'unpaid',
    ]);

    $crossWorkspaceStatus = AppointmentStatus::create([
        'workspace_id' => $workspaceB->id,
        'key' => 'only_in_workspace_b',
        'label' => 'Only in B',
        'color' => '#000000',
        'bg' => '#FFFFFF',
        'sort_order' => 99,
    ]);

    $this->actingAs($ownerA)
        ->patch(route('appointments.update', $appointment), ['status' => $crossWorkspaceStatus->key])
        ->assertSessionHasErrors('status');
});

/*
|--------------------------------------------------------------------------
| Tenant-scoped foreign key validation on create
|--------------------------------------------------------------------------
*/

test('creating an order with a customer_id belonging to another workspace is rejected', function () {
    [, $ownerA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $customerB = Customer::create(['workspace_id' => $workspaceB->id, 'full_name' => 'B Customer']);

    $this->actingAs($ownerA)
        ->post(route('orders.store'), [
            'title' => 'Cross-workspace attempt',
            'price' => 10,
            'customer_id' => $customerB->id,
        ])
        ->assertSessionHasErrors('customer_id');

    expect(Order::withoutGlobalScopes()->where('title', 'Cross-workspace attempt')->exists())->toBeFalse();
});

test('creating an order with a conversation_id belonging to another workspace is rejected', function () {
    [, $ownerA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    [, $conversationB] = createOrderWithConversation($workspaceB);

    $this->actingAs($ownerA)
        ->post(route('orders.store'), [
            'title' => 'Cross-workspace attempt',
            'price' => 10,
            'customer_name' => 'Someone',
            'conversation_id' => $conversationB->id,
        ])
        ->assertSessionHasErrors('conversation_id');
});

test('creating an order with a catalog_item_id belonging to another workspace is rejected', function () {
    [, $ownerA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $productB = Product::create(['workspace_id' => $workspaceB->id, 'name' => 'B product', 'active' => true]);

    $this->actingAs($ownerA)
        ->post(route('orders.store'), [
            'title' => 'Cross-workspace attempt',
            'price' => 10,
            'customer_name' => 'Someone',
            'catalog_item_id' => $productB->id,
        ])
        ->assertSessionHasErrors('catalog_item_id');
});

test('an order catalog_item_id must actually be a Product, not a Service, even within the same workspace', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    $service = Service::create(['workspace_id' => $workspace->id, 'name' => 'A service', 'active' => true]);

    $this->actingAs($owner)
        ->post(route('orders.store'), [
            'title' => 'Wrong catalog type',
            'price' => 10,
            'customer_name' => 'Someone',
            'catalog_item_id' => $service->id,
        ])
        ->assertSessionHasErrors('catalog_item_id');
});

test('creating an appointment with a service_id belonging to another workspace is rejected', function () {
    [$workspaceA, $ownerA] = createWorkspaceWithUser(['current_workspace_id' => null]);
    $workspaceA->update(['appointments_enabled' => true]);
    $ownerA->update(['current_workspace_id' => $workspaceA->id]);

    [$workspaceB] = createWorkspaceWithUser();
    $serviceB = Service::create(['workspace_id' => $workspaceB->id, 'name' => 'B service', 'active' => true]);

    $this->actingAs($ownerA)
        ->post(route('appointments.store'), [
            'service_name' => 'Cross-workspace attempt',
            'customer_name' => 'Someone',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
            'service_id' => $serviceB->id,
        ])
        ->assertSessionHasErrors('service_id');
});

test('an appointment service_id must actually be a Service, not a Product, even within the same workspace', function () {
    [$workspace, $owner] = createWorkspaceWithUser(['current_workspace_id' => null]);
    $workspace->update(['appointments_enabled' => true]);
    $owner->update(['current_workspace_id' => $workspace->id]);

    $product = Product::create(['workspace_id' => $workspace->id, 'name' => 'A product', 'active' => true]);

    $this->actingAs($owner)
        ->post(route('appointments.store'), [
            'service_name' => 'Wrong catalog type',
            'customer_name' => 'Someone',
            'appointment_date' => now()->addDay()->toDateString(),
            'start_time' => '10:00',
            'duration_minutes' => 30,
            'service_id' => $product->id,
        ])
        ->assertSessionHasErrors('service_id');
});

/*
|--------------------------------------------------------------------------
| Status record resolution outside an authenticated tenant scope
|--------------------------------------------------------------------------
*/

test('resolving order/payment status by key alone would collide across workspaces, so the model relation must include workspace_id', function () {
    [$workspaceA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $customerA = Customer::create(['workspace_id' => $workspaceA->id, 'full_name' => 'A Customer']);
    $orderA = Order::create([
        'workspace_id' => $workspaceA->id,
        'customer_id' => $customerA->id,
        'title' => 'Order A',
        'price' => 10,
        'status' => 'new',
        'payment_status' => 'unpaid',
    ]);

    // Rename workspace B's "new" status label so if resolution ever matched
    // by key alone (ignoring workspace_id), Order A's orderStatus would
    // wrongly pick up workspace B's row instead of its own.
    OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspaceB->id)->where('key', 'new')->update(['label' => 'WRONG WORKSPACE LABEL']);

    // No authenticated tenant scope is active here — the same situation a
    // queued job or artisan command runs in.
    expect(auth()->check())->toBeFalse();

    $resolved = $orderA->orderStatus;

    expect($resolved)->not->toBeNull();
    expect($resolved->workspace_id)->toBe($workspaceA->id);
    expect($resolved->label)->not->toBe('WRONG WORKSPACE LABEL');
});

test('isOverdue resolves this orders own workspace status even without an authenticated tenant scope', function () {
    [$workspaceA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $customerA = Customer::create(['workspace_id' => $workspaceA->id, 'full_name' => 'A Customer']);
    $orderA = Order::create([
        'workspace_id' => $workspaceA->id,
        'customer_id' => $customerA->id,
        'title' => 'Order A',
        'price' => 10,
        'status' => 'confirmed',
        'payment_status' => 'unpaid',
        'due_date' => now()->subDay(),
    ]);

    // Flag workspace B's "confirmed" status (same key) as completed. If
    // resolution ever matched by key alone, orderA->isOverdue() would
    // wrongly read workspace B's is_completed flag.
    OrderStatus::withoutGlobalScopes()->where('workspace_id', $workspaceB->id)->where('key', 'confirmed')->update(['is_completed' => true]);

    expect(auth()->check())->toBeFalse();
    expect($orderA->isOverdue())->toBeTrue();
});
