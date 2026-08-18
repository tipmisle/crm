<?php

use App\Models\Appointment;
use App\Models\AppointmentItem;
use App\Models\AppointmentStatus;
use App\Models\Customer;

afterEach(function () {
    // See tests/Feature/Orders/AtomicCreateUpdateTest.php — this listener
    // is bound to the class and must not leak into later tests.
    AppointmentItem::flushEventListeners();
});

test('appointment creation rolls back the auto-created customer if item creation fails', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    $customersBefore = Customer::count();

    AppointmentItem::creating(function () {
        throw new RuntimeException('Simulated item failure');
    });

    $response = $this->actingAs($user)->post(route('appointments.store'), [
        'service_name' => 'Striženje',
        'customer_name' => 'Nova stranka',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 30,
        'items' => [
            ['title' => 'Striženje', 'quantity' => 1, 'unit_price' => 30],
        ],
    ]);

    $response->assertStatus(500);

    expect(Customer::count())->toBe($customersBefore);
    expect(Appointment::count())->toBe(0);
});

test('appointment update rolls back the price change if replacing the item set fails', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Termin',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 30,
        'price' => 30,
        'status' => AppointmentStatus::defaultKey(),
    ]);
    $appointment->items()->create(['title' => 'Izvirna storitev', 'quantity' => 1, 'unit_price' => 30]);

    AppointmentItem::creating(function () {
        throw new RuntimeException('Simulated item failure');
    });

    $response = $this->actingAs($user)->patch(route('appointments.update', $appointment), [
        'items' => [
            ['title' => 'Nova storitev', 'quantity' => 1, 'unit_price' => 90],
        ],
    ]);

    $response->assertStatus(500);

    $appointment->refresh();
    expect((float) $appointment->price)->toBe(30.0);
    expect($appointment->items()->count())->toBe(1);
    expect($appointment->items()->first()->title)->toBe('Izvirna storitev');
});
