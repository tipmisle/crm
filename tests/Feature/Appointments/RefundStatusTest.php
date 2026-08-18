<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Services\RevenueStatsService;

test('an appointment can be marked as refunded', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Zala Ferlan',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Manikura',
        'appointment_date' => today(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'status' => 'completed',
    ]);

    $this->actingAs($user)->patch(route('appointments.update', $appointment), ['status' => 'refunded'])->assertRedirect();

    expect($appointment->fresh()->status)->toBe('refunded');
});

test('a refunded appointment is excluded from revenue stats', function () {
    [$workspace] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Zala Ferlan',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Manikura',
        'appointment_date' => today(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'status' => 'refunded',
        'price' => 80,
    ]);

    $stats = app(RevenueStatsService::class);
    $revenue = $stats->totalRevenue($workspace, now()->subDay(), now()->addDay());

    expect($revenue)->toBe(0.0);
});

test('a refunded appointment is not counted as upcoming', function () {
    [$workspace] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Zala Ferlan',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Manikura',
        'appointment_date' => today()->addDays(3),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'status' => 'refunded',
    ]);

    expect($appointment->isUpcoming())->toBeFalse();
});
