<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\FollowUp;

test('a follow-up can be created for a customer in the current workspace', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana']);

    $this->actingAs($owner)->post(route('follow-ups.store'), [
        'followable_type' => 'App\\Models\\Customer',
        'followable_id' => $customer->id,
        'note' => 'Pokliči',
        'due_at' => now()->addDay()->toDateString(),
    ])->assertRedirect();

    expect(FollowUp::where('followable_type', 'App\\Models\\Customer')->where('followable_id', $customer->id)->exists())->toBeTrue();
});

test('a follow-up can be created for an order in the current workspace', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($owner)->post(route('follow-ups.store'), [
        'followable_type' => 'App\\Models\\Order',
        'followable_id' => $order->id,
        'note' => 'Preveri plačilo',
        'due_at' => now()->addDay()->toDateString(),
    ])->assertRedirect();

    expect(FollowUp::where('followable_type', 'App\\Models\\Order')->where('followable_id', $order->id)->exists())->toBeTrue();
});

test('a follow-up can be created for a conversation in the current workspace', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    [, $conversation] = createOrderWithConversation($workspace);

    $this->actingAs($owner)->post(route('follow-ups.store'), [
        'followable_type' => 'App\\Models\\Conversation',
        'followable_id' => $conversation->id,
        'note' => 'Odgovori',
        'due_at' => now()->addDay()->toDateString(),
    ])->assertRedirect();

    expect(FollowUp::where('followable_type', 'App\\Models\\Conversation')->where('followable_id', $conversation->id)->exists())->toBeTrue();
});

test('a follow-up can be created for an appointment in the current workspace', function () {
    [$workspace, $owner] = createWorkspaceWithUser(['current_workspace_id' => null]);
    $workspace->update(['appointments_enabled' => true]);
    $owner->update(['current_workspace_id' => $workspace->id]);

    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana']);
    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Cut',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 30,
        'price' => 20,
        'status' => 'requested',
        'payment_status' => 'unpaid',
    ]);

    $this->actingAs($owner)->post(route('follow-ups.store'), [
        'followable_type' => 'App\\Models\\Appointment',
        'followable_id' => $appointment->id,
        'note' => 'Potrdi termin',
        'due_at' => now()->addDay()->toDateString(),
    ])->assertRedirect();

    expect(FollowUp::where('followable_type', 'App\\Models\\Appointment')->where('followable_id', $appointment->id)->exists())->toBeTrue();
});

test('a crafted followable_id pointing at another workspace customer is rejected', function () {
    [, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();
    $customerB = Customer::create(['workspace_id' => $workspaceB->id, 'full_name' => 'B Customer']);

    $this->actingAs($userA)->post(route('follow-ups.store'), [
        'followable_type' => 'App\\Models\\Customer',
        'followable_id' => $customerB->id,
        'note' => 'hack',
        'due_at' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors('followable_id');

    expect(FollowUp::where('followable_type', 'App\\Models\\Customer')->where('followable_id', $customerB->id)->exists())->toBeFalse();
});

test('a crafted followable_id pointing at another workspace order is rejected', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();
    [$orderB] = createOrderWithConversation($workspaceB);

    $this->actingAs($userA)->post(route('follow-ups.store'), [
        'followable_type' => 'App\\Models\\Order',
        'followable_id' => $orderB->id,
        'note' => 'hack',
        'due_at' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors('followable_id');

    expect(FollowUp::where('followable_type', 'App\\Models\\Order')->where('followable_id', $orderB->id)->exists())->toBeFalse();
});

test('a crafted followable_id pointing at another workspace conversation is rejected', function () {
    [, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();
    [, $conversationB] = createOrderWithConversation($workspaceB);

    $this->actingAs($userA)->post(route('follow-ups.store'), [
        'followable_type' => 'App\\Models\\Conversation',
        'followable_id' => $conversationB->id,
        'note' => 'hack',
        'due_at' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors('followable_id');

    expect(FollowUp::where('followable_type', 'App\\Models\\Conversation')->where('followable_id', $conversationB->id)->exists())->toBeFalse();
});

test('a crafted followable_id pointing at another workspace appointment is rejected', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser(['current_workspace_id' => null]);
    $workspaceA->update(['appointments_enabled' => true]);
    $userA->update(['current_workspace_id' => $workspaceA->id]);

    [$workspaceB] = createWorkspaceWithUser();
    $workspaceB->update(['appointments_enabled' => true]);
    $customerB = Customer::create(['workspace_id' => $workspaceB->id, 'full_name' => 'B Customer']);
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

    $this->actingAs($userA)->post(route('follow-ups.store'), [
        'followable_type' => 'App\\Models\\Appointment',
        'followable_id' => $appointmentB->id,
        'note' => 'hack',
        'due_at' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors('followable_id');

    expect(FollowUp::where('followable_type', 'App\\Models\\Appointment')->where('followable_id', $appointmentB->id)->exists())->toBeFalse();
});

test('an unrecognized followable_type is rejected outright', function () {
    [, $owner] = createWorkspaceWithUser();

    $this->actingAs($owner)->post(route('follow-ups.store'), [
        'followable_type' => 'App\\Models\\Workspace',
        'followable_id' => 1,
        'note' => 'hack',
        'due_at' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors('followable_type');
});
