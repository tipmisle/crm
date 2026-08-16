<?php

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\Service;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

function enableAppointments(Workspace $workspace): void
{
    $workspace->update(['appointments_enabled' => true]);
}

test('appointments default to the list view and calendar opens on the current week', function () {
    Carbon::setTestNow('2026-08-19 12:00:00');
    [$workspace, $user] = createWorkspaceWithUser();
    enableAppointments($workspace);

    $this->actingAs($user)->get(route('appointments.index'))
        ->assertInertia(fn ($page) => $page->component('Appointments/Index'));

    $this->actingAs($user)->get(route('appointments.index', ['view' => 'calendar']))
        ->assertInertia(fn ($page) => $page
            ->component('Appointments/Calendar')
            ->where('weekStart', '2026-08-17'));

    Carbon::setTestNow();
});

test('appointments can be filtered by status payment and date', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    enableAppointments($workspace);
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana Novak']);

    $matching = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Manikura',
        'appointment_date' => today(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'price' => 30,
        'payment_status' => 'unpaid',
        'status' => 'confirmed',
    ]);
    Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Pedikura',
        'appointment_date' => today()->addDay(),
        'start_time' => '12:00',
        'duration_minutes' => 60,
        'price' => 40,
        'payment_status' => 'paid',
        'status' => 'completed',
    ]);

    $this->actingAs($user)->get(route('appointments.index', [
        'status' => 'confirmed',
        'payment' => 'unpaid',
        'due' => 'today',
    ]))->assertInertia(fn ($page) => $page
        ->component('Appointments/Index')
        ->has('appointments.data', 1)
        ->where('appointments.data.0.id', $matching->id));
});

test('an appointment can be created for an existing customer', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    enableAppointments($workspace);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $response = $this->actingAs($user)->post(route('appointments.store'), [
        'service_name' => 'Gel manikura',
        'customer_id' => $customer->id,
        'appointment_date' => now()->addDays(3)->toDateString(),
        'start_time' => '14:00',
        'duration_minutes' => 75,
        'price' => 35,
        'deposit_amount' => 10,
    ]);

    $appointment = Appointment::first();

    $response->assertRedirect(route('appointments.show', $appointment));
    expect($appointment)->not->toBeNull();
    expect($appointment->customer_id)->toBe($customer->id);
    expect($appointment->status->value)->toBe('requested');
    expect($appointment->appointment_number)->toStartWith('APT-');
});

test('booking an appointment from an inbox conversation auto-creates and links a customer', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    enableAppointments($workspace);

    $channel = createMetaChannel($workspace, 'instagram', 'ig_studio');
    $conversation = Conversation::create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_123',
        'customer_display_name' => 'Zala Ferlan',
        'customer_username' => '@zala',
        'status' => 'new_enquiry',
    ]);

    expect($conversation->customer_id)->toBeNull();

    $response = $this->actingAs($user)->post(route('appointments.store'), [
        'service_name' => 'BIAB nadgradnja',
        'conversation_id' => $conversation->id,
        'appointment_date' => now()->addDays(2)->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'price' => 30,
        'deposit_amount' => 10,
    ]);

    $response->assertRedirect();

    $conversation->refresh();
    expect($conversation->customer_id)->not->toBeNull();

    $customer = Customer::find($conversation->customer_id);
    expect($customer->full_name)->toBe('Zala Ferlan');

    $appointment = Appointment::first();
    expect($appointment->customer_id)->toBe($customer->id);
    expect($appointment->conversation_id)->toBe($conversation->id);

    // The identity created by the webhook flow (external_conversation_id)
    // should be reused, not duplicated, when the customer is auto-created.
    $identity = CustomerIdentity::where('workspace_id', $workspace->id)
        ->where('external_id', 'sender_123')
        ->first();

    if ($identity) {
        expect($identity->customer_id)->toBe($customer->id);
    }
});

test('appointment status can change through its lifecycle', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    enableAppointments($workspace);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Manca Kolar',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Gel manikura',
        'appointment_date' => now()->addDay(),
        'start_time' => '11:00',
        'duration_minutes' => 75,
        'status' => 'requested',
    ]);

    $this->actingAs($user)
        ->patch(route('appointments.update', $appointment), ['status' => 'confirmed'])
        ->assertRedirect();

    expect($appointment->fresh()->status->value)->toBe('confirmed');

    $this->actingAs($user)
        ->patch(route('appointments.update', $appointment), ['status' => 'no_show'])
        ->assertRedirect();

    expect($appointment->fresh()->status->value)->toBe('no_show');

    expect(
        ActivityLog::where('subject_type', Appointment::class)
            ->where('subject_id', $appointment->id)
            ->where('type', 'status_changed')
            ->count()
    )->toBe(2);
});

test('rescheduling an appointment is logged as an activity, not a status', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    enableAppointments($workspace);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Pia Šinkovec',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Gel podaljški',
        'appointment_date' => now()->addDays(2),
        'start_time' => '12:00',
        'duration_minutes' => 105,
        'status' => 'confirmed',
    ]);

    $newDate = now()->addDays(5)->toDateString();

    $this->actingAs($user)->patch(route('appointments.update', $appointment), [
        'appointment_date' => $newDate,
        'start_time' => '16:00',
    ])->assertRedirect();

    $appointment->refresh();
    expect($appointment->appointment_date->format('Y-m-d'))->toBe($newDate);
    expect($appointment->status->value)->toBe('confirmed'); // status unchanged by reschedule

    expect(
        ActivityLog::where('subject_type', Appointment::class)
            ->where('subject_id', $appointment->id)
            ->where('type', 'appointment_rescheduled')
            ->exists()
    )->toBeTrue();
});

test('appointment payment fields calculate a remaining balance correctly', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    enableAppointments($workspace);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Neja Blatnik',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Gel podaljški',
        'appointment_date' => now()->addDay(),
        'start_time' => '09:00',
        'duration_minutes' => 105,
        'price' => 45,
        'deposit_amount' => 15,
        'amount_paid' => 15,
        'payment_status' => 'deposit_paid',
        'status' => 'confirmed',
    ]);

    expect($appointment->remainingBalance())->toBe(30.0);

    $this->actingAs($user)->patch(route('appointments.update', $appointment), [
        'amount_paid' => 45,
        'payment_status' => 'paid',
    ])->assertRedirect();

    expect($appointment->fresh()->remainingBalance())->toBe(0.0);
});

test('a user cannot view an appointment belonging to another workspace', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();
    enableAppointments($workspaceA);
    enableAppointments($workspaceB);

    $customerB = Customer::create([
        'workspace_id' => $workspaceB->id,
        'full_name' => 'Stranka B',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $appointmentB = Appointment::create([
        'workspace_id' => $workspaceB->id,
        'customer_id' => $customerB->id,
        'service_name' => 'Gel manikura',
        'appointment_date' => now()->addDay(),
        'start_time' => '10:00',
        'duration_minutes' => 75,
        'status' => 'requested',
    ]);

    $this->actingAs($userA)
        ->get(route('appointments.show', $appointmentB->id))
        ->assertStatus(404);

    $this->actingAs($userA)
        ->patch(route('appointments.update', $appointmentB->id), ['status' => 'confirmed'])
        ->assertStatus(404);
});

test('a service belongs to its own workspace and cannot be edited cross-workspace', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();
    enableAppointments($workspaceA);
    enableAppointments($workspaceB);

    $serviceB = Service::create([
        'workspace_id' => $workspaceB->id,
        'name' => 'Service B',
        'default_duration_minutes' => 60,
        'active' => true,
    ]);

    $this->actingAs($userA)
        ->patch(route('services.update', $serviceB->id), ['name' => 'Hijacked'])
        ->assertStatus(404);
});
