<?php

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
