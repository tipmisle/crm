<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Order;

test('a follow-up shown on the order detail page can be completed without visiting Today', function () {
    [$workspace, $user] = createWorkspaceWithUser();

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
    ]);

    $followUp = FollowUp::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'followable_type' => Order::class,
        'followable_id' => $order->id,
        'note' => 'Pokliči glede dostave',
        'due_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->get(route('orders.show', $order))
        ->assertInertia(fn ($page) => $page
            ->has('followUps', 1)
            ->where('followUps.0.id', $followUp->id)
            ->where('followUps.0.completed_at', null)
        );

    $this->actingAs($user)
        ->patch(route('follow-ups.complete', $followUp))
        ->assertRedirect();

    expect($followUp->fresh()->completed_at)->not->toBeNull();
});

test('a follow-up shown on the appointment detail page can be completed inline', function () {
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
        'appointment_date' => now()->addDay(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'status' => 'confirmed',
    ]);

    $followUp = FollowUp::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'followable_type' => Appointment::class,
        'followable_id' => $appointment->id,
        'note' => 'Potrdi termin dan prej',
        'due_at' => now()->addHours(6),
    ]);

    $this->actingAs($user)
        ->patch(route('follow-ups.complete', $followUp))
        ->assertRedirect();

    expect($followUp->fresh()->completed_at)->not->toBeNull();
});

test('a follow-up shown on the customer detail page can be completed inline', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Manca Kolar',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $followUp = FollowUp::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'followable_type' => Customer::class,
        'followable_id' => $customer->id,
        'note' => 'Vprašaj za povratno informacijo',
        'due_at' => now()->addDay(),
    ]);

    $this->actingAs($user)
        ->get(route('customers.show', $customer))
        ->assertInertia(fn ($page) => $page
            ->has('followUps', 1)
            ->where('followUps.0.id', $followUp->id)
        );

    $this->actingAs($user)
        ->patch(route('follow-ups.complete', $followUp))
        ->assertRedirect();

    expect($followUp->fresh()->completed_at)->not->toBeNull();
});
