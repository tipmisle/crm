<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;

test('a user cannot fetch a customer belonging to another workspace', function () {
    [, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $customerB = Customer::create(['workspace_id' => $workspaceB->id, 'full_name' => 'Ana B']);

    $this->actingAs($userA)->get(route('customers.show', $customerB))->assertStatus(404);
});

test('a user cannot update an order belonging to another workspace', function () {
    [, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $customerB = Customer::create(['workspace_id' => $workspaceB->id, 'full_name' => 'B Customer']);

    $orderB = Order::create([
        'workspace_id' => $workspaceB->id,
        'customer_id' => $customerB->id,
        'order_number' => 'B-1',
        'title' => 'Order B',
        'price' => 10,
        'status' => 'new',
        'payment_status' => 'unpaid',
    ]);

    $this->actingAs($userA)
        ->patch(route('orders.update', $orderB), ['title' => 'hacked'])
        ->assertStatus(404);

    expect($orderB->fresh()->title)->toBe('Order B');
});

test('a user cannot delete an order belonging to another workspace', function () {
    [, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();

    $customerB = Customer::create(['workspace_id' => $workspaceB->id, 'full_name' => 'B Customer 2']);

    $orderB = Order::create([
        'workspace_id' => $workspaceB->id,
        'customer_id' => $customerB->id,
        'order_number' => 'B-2',
        'title' => 'Order B2',
        'price' => 10,
        'status' => 'new',
        'payment_status' => 'unpaid',
    ]);

    $this->actingAs($userA)->delete(route('orders.destroy', $orderB))->assertStatus(404);

    expect(Order::withoutGlobalScopes()->find($orderB->id))->not->toBeNull();
});

test('a user cannot access an appointment belonging to another workspace', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser(['current_workspace_id' => null]);
    $workspaceA->update(['appointments_enabled' => true]);
    $userA->update(['current_workspace_id' => $workspaceA->id]);

    [$workspaceB] = createWorkspaceWithUser();
    $workspaceB->update(['appointments_enabled' => true]);

    $customerB = Customer::create(['workspace_id' => $workspaceB->id, 'full_name' => 'B Customer 3']);

    $appointmentB = Appointment::create([
        'workspace_id' => $workspaceB->id,
        'customer_id' => $customerB->id,
        'service_name' => 'Cut',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 30,
        'price' => 20,
        'status' => 'requested',
        'payment_status' => 'unpaid',
    ]);

    $this->actingAs($userA)->get(route('appointments.show', $appointmentB))->assertStatus(404);
});

test('an authenticated user with no current workspace cannot see any workspace-scoped rows', function () {
    [$workspaceB] = createWorkspaceWithUser();
    Customer::create(['workspace_id' => $workspaceB->id, 'full_name' => 'Leaked']);

    $orphan = User::factory()->create(['current_workspace_id' => null]);

    $this->actingAs($orphan);

    expect(Customer::count())->toBe(0);
});
