<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentStatus;

test('the today dashboard renders todays revenue and inquiry stats', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Torta',
        'price' => 60,
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->component('Today')
        ->where('stats.revenue.value', 60)
        ->has('stats.inquiries.value')
        ->where('stats.bookings.value', 1)
        ->has('stats.conversionRate.value')
    );
});

test('the "due today" attention count links to exactly the orders it counted', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $dueToday = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Torta',
        'price' => 60,
        'status' => 'confirmed',
        'due_date' => today(),
    ]);

    // Same day, but cancelled — must not count as "due today" (it's closed).
    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Preklicano',
        'price' => 40,
        'status' => 'cancelled',
        'due_date' => today(),
    ]);

    // Different day — must not count either.
    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Jutri',
        'price' => 40,
        'status' => 'confirmed',
        'due_date' => today()->addDay(),
    ]);

    $page = $this->actingAs($user)->get(route('dashboard'));

    $dueTodayItem = collect($page->inertiaProps('attention'))->firstWhere('key', 'due_today');

    expect($dueTodayItem['count'])->toBe(1);

    $filtered = $this->actingAs($user)->get($dueTodayItem['href']);
    $filtered->assertInertia(fn ($p) => $p
        ->component('Orders/Index')
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $dueToday->id)
    );
});

test('the "deposits unpaid" attention count respects custom outstanding payment statuses', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    // Flag an already-seeded, non-default payment status ("partially_paid")
    // as outstanding — the count/link must include it, not just the
    // hardcoded 'deposit_due' default.
    $customStatus = PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'partially_paid')->firstOrFail();
    $customStatus->update(['is_outstanding' => true]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $matching = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Torta z delnim plačilom',
        'price' => 100,
        'deposit_amount' => 30,
        'payment_status' => $customStatus->key,
        'status' => 'confirmed',
    ]);

    // Outstanding payment status but no deposit required — should not count.
    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Brez are',
        'price' => 100,
        'deposit_amount' => 0,
        'payment_status' => $customStatus->key,
        'status' => 'confirmed',
    ]);

    $page = $this->actingAs($user)->get(route('dashboard'));
    $depositsItem = collect($page->inertiaProps('attention'))->firstWhere('key', 'deposits_unpaid');

    expect($depositsItem['count'])->toBe(1);

    $filtered = $this->actingAs($user)->get($depositsItem['href']);
    $filtered->assertInertia(fn ($p) => $p
        ->has('orders.data', 1)
        ->where('orders.data.0.id', $matching->id)
    );
});

test('the appointment attention counts link to exactly the appointments they counted', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Zala Ferlan',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $requestedToday = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Manikura',
        'appointment_date' => today(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'status' => 'requested',
    ]);

    // Completed today — must not count as "today" (only active statuses do).
    Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Pedikura',
        'appointment_date' => today(),
        'start_time' => '12:00',
        'duration_minutes' => 60,
        'status' => 'completed',
    ]);

    $page = $this->actingAs($user)->get(route('dashboard'));
    $props = $page->inertiaProps();

    $todayItem = collect($props['attention'])->firstWhere('key', 'appointments_today');
    expect($todayItem['count'])->toBe(1);

    $filtered = $this->actingAs($user)->get($todayItem['href']);
    $filtered->assertInertia(fn ($p) => $p
        ->has('appointments.data', 1)
        ->where('appointments.data.0.id', $requestedToday->id)
    );

    $awaitingItem = collect($props['attention'])->firstWhere('key', 'awaiting_confirmation');
    expect($awaitingItem['count'])->toBe(1);

    $filteredAwaiting = $this->actingAs($user)->get($awaitingItem['href']);
    $filteredAwaiting->assertInertia(fn ($p) => $p
        ->has('appointments.data', 1)
        ->where('appointments.data.0.id', $requestedToday->id)
    );
});

test('Today exposes both module flags so the mini calendar can offer both destinations', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('workspace.orders_enabled', true)
        ->where('workspace.appointments_enabled', true)
    );

    // Both destinations the mini calendar can route to must actually work —
    // the choice is only meaningful if neither one is a dead end.
    $this->actingAs($user)->get(route('orders.index', ['view' => 'calendar']))
        ->assertInertia(fn ($page) => $page->component('Orders/Calendar'));

    $this->actingAs($user)->get(route('appointments.index', ['view' => 'calendar']))
        ->assertInertia(fn ($page) => $page->component('Appointments/Calendar'));
});

test('Today exposes only the enabled module for a single-mode workspace', function () {
    [, $user] = createWorkspaceWithUser();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertInertia(fn ($page) => $page
        ->where('workspace.orders_enabled', true)
        ->where('workspace.appointments_enabled', false)
    );
});

test('the "quote sent" attention item was removed rather than guessing at order-status semantics', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => Customer::create([
            'workspace_id' => $workspace->id,
            'full_name' => 'Ana Novak',
            'first_contacted_at' => now(),
            'last_interaction_at' => now(),
        ])->id,
        'title' => 'Ponudba',
        'price' => 50,
        'status' => 'quote_sent',
    ]);

    $page = $this->actingAs($user)->get(route('dashboard'));

    expect(collect($page->inertiaProps('attention'))->firstWhere('key', 'quotes_waiting'))->toBeNull();
});
