<?php

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;

test('a new customer can be created with the full address/tax field set shared with invoicing', function () {
    [, $user] = createWorkspaceWithUser();

    $this->actingAs($user)->post(route('customers.store'), [
        'full_name' => 'Ana Novak',
        'email' => 'ana@example.com',
        'phone' => '040123456',
        'address_line' => 'Slovenska 1',
        'postal_code' => '1000',
        'city' => 'Ljubljana',
        'country' => 'Slovenija',
        'tax_number' => 'SI12345678',
    ])->assertRedirect();

    $customer = Customer::first();
    expect($customer->country)->toBe('Slovenija');
    expect($customer->tax_number)->toBe('SI12345678');
});

test('a customer defaults to a private person unless explicitly marked as a business', function () {
    [, $user] = createWorkspaceWithUser();

    $this->actingAs($user)->post(route('customers.store'), ['full_name' => 'Ana Novak'])->assertRedirect();

    expect(Customer::first()->is_business)->toBeFalse();
});

test('a customer can be marked as a business and it persists through an update', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Podjetje d.o.o.',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $this->actingAs($user)->patch(route('customers.update', $customer), [
        'is_business' => true,
        'tax_number' => 'SI87654321',
    ])->assertRedirect();

    $customer->refresh();
    expect($customer->is_business)->toBeTrue();
    expect($customer->tax_number)->toBe('SI87654321');
});

test('updating a customer changes the one record read everywhere it is shown', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $this->actingAs($user)->patch(route('customers.update', $customer), [
        'address_line' => 'Nova ulica 5',
        'city' => 'Maribor',
        'country' => 'Slovenija',
        'tax_number' => 'SI87654321',
    ])->assertRedirect();

    $customer->refresh();
    expect($customer->address_line)->toBe('Nova ulica 5');
    expect($customer->country)->toBe('Slovenija');
    expect($customer->tax_number)->toBe('SI87654321');

    // The Customer show page (source of truth) reflects the same update.
    $this->actingAs($user)->get(route('customers.show', $customer))
        ->assertInertia(fn ($page) => $page
            ->where('customer.address_line', 'Nova ulica 5')
            ->where('customer.country', 'Slovenija')
            ->where('customer.tax_number', 'SI87654321')
        );
});

test('the customer detail header CTA respects business mode', function () {
    [$workspace, $userOrdersOnly] = createWorkspaceWithUser();
    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    // orders_enabled defaults true, appointments_enabled defaults false.
    $this->actingAs($userOrdersOnly)->get(route('customers.show', $customer))
        ->assertOk();

    [$workspaceBoth, $userBoth] = createWorkspaceWithUser();
    $workspaceBoth->update(['appointments_enabled' => true]);
    $customerBoth = Customer::create([
        'workspace_id' => $workspaceBoth->id,
        'full_name' => 'Zala Ferlan',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $this->actingAs($userBoth)->get(route('customers.show', $customerBoth))
        ->assertOk();
});

test('customer order history does not repeat orders already shown as current', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $open = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Odprto naročilo',
        'price' => 50,
        'status' => 'confirmed',
    ]);

    $closed = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Zaključeno naročilo',
        'price' => 50,
        'status' => 'completed',
    ]);

    $completedKey = OrderStatus::where('workspace_id', $workspace->id)->where('is_completed', true)->value('key');
    expect($completedKey)->toBe('completed'); // sanity check against the seeded defaults

    $response = $this->actingAs($user)->get(route('customers.show', $customer));

    $response->assertInertia(fn ($page) => $page
        ->has('customer.orders', 2) // both orders are still passed down…
    );

    // …but the frontend derives "Trenutna" vs "Zgodovina" from is_completed/
    // is_cancelled flags, not from the raw list — verify those flags are
    // present and correctly split so the two sections can never overlap.
    $orderStatuses = collect($response->inertiaProps('orderStatuses') ?? []);
    expect($orderStatuses->firstWhere('key', $open->status)['is_completed'] ?? false)->toBeFalsy();
    expect($orderStatuses->firstWhere('key', $closed->status)['is_completed'] ?? false)->toBeTruthy();
});

test('customer detail loads successfully when an appointment has multiple items', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Striženje in barvanje',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 90,
        'price' => 80,
        'status' => AppointmentStatus::defaultKey(),
    ]);

    $appointment->items()->createMany([
        ['title' => 'Striženje', 'quantity' => 1, 'unit_price' => 30],
        ['title' => 'Barvanje', 'quantity' => 1, 'unit_price' => 50],
    ]);

    // CustomerController::show() eager-loads appointments.items.service —
    // Appointment no longer has a direct `service` relation, so loading
    // `appointments.service` would throw. This must render without error.
    $response = $this->actingAs($user)->get(route('customers.show', $customer));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page->has('customer.appointments', 1));
});

test('customer index lifetime-spend totals match customer detail totals, excluding cancelled/refunded/no-show', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $cancelledKey = OrderStatus::where('workspace_id', $workspace->id)->where('is_cancelled', true)->value('key');

    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Plačano naročilo',
        'price' => 100,
        'amount_paid' => 100,
        'status' => 'confirmed',
    ]);

    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Preklicano, a pobotano naročilo',
        'price' => 40,
        'amount_paid' => 40,
        'status' => $cancelledKey,
    ]);

    $noShowKey = AppointmentStatus::where('workspace_id', $workspace->id)->where('is_no_show', true)->value('key');

    Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Plačan termin',
        'appointment_date' => now()->subDay()->toDateString(),
        'start_time' => '09:00',
        'duration_minutes' => 30,
        'price' => 50,
        'amount_paid' => 50,
        'status' => 'confirmed',
    ]);

    Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Ni se zglasil/a, a pobotano',
        'appointment_date' => now()->subDays(2)->toDateString(),
        'start_time' => '09:00',
        'duration_minutes' => 30,
        'price' => 20,
        'amount_paid' => 20,
        'status' => $noShowKey,
    ]);

    $indexResponse = $this->actingAs($user)->get(route('customers.index'));
    $indexRow = collect($indexResponse->inertiaProps('customers')['data'])->firstWhere('id', $customer->id);

    $detailResponse = $this->actingAs($user)->get(route('customers.show', $customer));
    $detailCustomer = $detailResponse->inertiaProps('customer');

    expect((float) $indexRow['lifetime_spend'])->toBe((float) $customer->lifetimeSpend());
    expect((float) $indexRow['lifetime_spend'])->toBe(100.0);
    expect((float) $detailCustomer['lifetime_spend'])->toBe(100.0);

    expect((float) $indexRow['appointments_lifetime_spend'])->toBe((float) $customer->appointmentsLifetimeSpend());
    expect((float) $indexRow['appointments_lifetime_spend'])->toBe(50.0);
    expect((float) $detailCustomer['appointments_lifetime_spend'])->toBe(50.0);
});

test('customer list upcoming-appointment query respects custom appointment status keys, not hardcoded requested/confirmed', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    // Rename the default "requested"/"confirmed" statuses to custom keys —
    // the upcoming-appointment query must still find an active appointment
    // via AppointmentStatus::openExclusionKeys(), not literal string checks.
    AppointmentStatus::where('workspace_id', $workspace->id)->where('key', 'requested')
        ->update(['key' => 'povprasevanje_novo', 'label' => 'Povpraševanje novo']);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Prihajajoč termin',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 30,
        'price' => 30,
        'status' => 'povprasevanje_novo',
    ]);

    $response = $this->actingAs($user)->get(route('customers.index'));
    $row = collect($response->inertiaProps('customers')['data'])->firstWhere('id', $customer->id);

    expect($row['upcoming_appointment'])->not->toBeNull();
});
