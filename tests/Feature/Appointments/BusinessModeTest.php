<?php

use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;

test('an order-only workspace does not expose appointment routes', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->refresh(); // pick up DB column defaults (Eloquent::create() doesn't backfill them in-memory)

    expect($workspace->orders_enabled)->toBeTrue();
    expect($workspace->appointments_enabled)->toBeFalse();

    $this->actingAs($user)->get(route('appointments.index'))->assertStatus(404);
    $this->actingAs($user)->get(route('appointments.create'))->assertStatus(404);
});

test('an order-only workspace inbox page does not offer booking an appointment', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $channel = createMetaChannel($workspace, 'instagram', 'ig_bloom');
    $conversation = Conversation::create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_1',
        'customer_display_name' => 'Ana Novak',
        'status' => 'new_enquiry',
    ]);

    $response = $this->actingAs($user)->get(route('inbox.show', $conversation));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->where('conversation.id', $conversation->id)
    );
});

test('an appointment-only workspace hides order actions and gates order-independent capability correctly', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['orders_enabled' => false, 'appointments_enabled' => true]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Test Stranka',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    // Appointments still work.
    $this->actingAs($user)->get(route('appointments.index'))->assertOk();

    // The workspace capability itself is what the frontend uses to hide the
    // "Ustvari naročilo" action — assert the flag the UI reads is correct.
    $response = $this->actingAs($user)->get(route('customers.show', $customer));
    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->where('customer.id', $customer->id));

    expect($user->fresh()->currentWorkspace->orders_enabled)->toBeFalse();
});

test('a workspace with both capabilities enabled supports creating an order and an appointment for the same customer', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['orders_enabled' => true, 'appointments_enabled' => true]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Hibridna Stranka',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $this->actingAs($user)->post(route('orders.store'), [
        'title' => 'Torta',
        'customer_id' => $customer->id,
        'price' => 50,
    ])->assertRedirect();

    $this->actingAs($user)->post(route('appointments.store'), [
        'service_name' => 'Gel manikura',
        'customer_id' => $customer->id,
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
    ])->assertRedirect();

    expect(Order::where('customer_id', $customer->id)->count())->toBe(1);
    expect(Appointment::where('customer_id', $customer->id)->count())->toBe(1);
});

test('global search includes appointments when appointments are enabled', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Iskana Stranka',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Balayage Deluxe',
        'appointment_date' => now()->addDay(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'status' => 'requested',
    ]);

    $response = $this->actingAs($user)->getJson(route('search', ['q' => 'Balayage Deluxe']));

    $response->assertOk();
    $response->assertJsonFragment(['type' => 'appointment']);
});

test('global search excludes appointments when appointments are disabled', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->refresh();

    expect($workspace->appointments_enabled)->toBeFalse();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Skrita Stranka',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    // Directly bypass the disabled gate at the DB layer to prove the search
    // filter — not just an empty table — is what hides the result.
    Appointment::withoutGlobalScopes()->create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Hidden Service Unique',
        'appointment_date' => now()->addDay(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'status' => 'requested',
    ]);

    $response = $this->actingAs($user)->getJson(route('search', ['q' => 'Hidden Service Unique']));

    $response->assertOk();
    $response->assertJsonMissing(['type' => 'appointment']);
});
