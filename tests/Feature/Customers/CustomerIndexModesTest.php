<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

test('customers index shows order-oriented columns for an orders-only workspace', function () {
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
        'price' => 100,
        'amount_paid' => 40,
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($user)->get(route('customers.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('customers.data.0.orders_count', 1)
        ->where('customers.data.0.lifetime_spend', 40)
        ->where('customers.data.0.open_orders_count', 1)
        ->missing('customers.data.0.appointments_count')
    );
});

test('customers index shows appointment-oriented data for an appointments-only workspace', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['orders_enabled' => false, 'appointments_enabled' => true]);

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
        'appointment_date' => now()->addDays(3),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'price' => 35,
        'amount_paid' => 35,
        'status' => 'confirmed',
    ]);

    // Past, no-show appointment must not surface as the "next" upcoming one.
    Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Pedikura',
        'appointment_date' => now()->subWeek(),
        'start_time' => '09:00',
        'duration_minutes' => 45,
        'price' => 25,
        'amount_paid' => 25,
        'status' => 'completed',
    ]);

    $response = $this->actingAs($user)->get(route('customers.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('customers.data.0.appointments_count', 2)
        ->where('customers.data.0.appointments_lifetime_spend', 60)
        ->where('customers.data.0.upcoming_appointment.service_name', 'Manikura')
        ->missing('customers.data.0.orders_count')
    );
});

test('customers index shows a balanced view for a workspace with both modules', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Manca Kolar',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Torta',
        'price' => 80,
        'amount_paid' => 80,
        'status' => 'confirmed',
    ]);

    Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Manikura',
        'appointment_date' => now()->addDays(2),
        'start_time' => '11:00',
        'duration_minutes' => 60,
        'price' => 20,
        'amount_paid' => 20,
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($user)->get(route('customers.index'));

    $response->assertInertia(fn ($page) => $page
        ->where('customers.data.0.orders_count', 1)
        ->where('customers.data.0.appointments_count', 1)
        ->where('customers.data.0.lifetime_spend', 80)
        ->where('customers.data.0.appointments_lifetime_spend', 20)
    );
});

test('the customers index aggregates in a fixed number of queries regardless of row count', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    foreach (range(1, 6) as $i) {
        $customer = Customer::create([
            'workspace_id' => $workspace->id,
            'full_name' => "Stranka {$i}",
            'first_contacted_at' => now(),
            'last_interaction_at' => now(),
        ]);

        Order::create([
            'workspace_id' => $workspace->id,
            'customer_id' => $customer->id,
            'title' => 'Torta',
            'price' => 50,
            'amount_paid' => 50,
            'status' => 'confirmed',
        ]);

        Appointment::create([
            'workspace_id' => $workspace->id,
            'customer_id' => $customer->id,
            'service_name' => 'Manikura',
            'appointment_date' => now()->addDay(),
            'start_time' => '10:00',
            'duration_minutes' => 60,
            'price' => 20,
            'amount_paid' => 20,
            'status' => 'confirmed',
        ]);
    }

    DB::enableQueryLog();
    $this->actingAs($user)->get(route('customers.index'))->assertOk();
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    // A handful of aggregate queries (customers page + withCount/withSum +
    // one upcoming-appointments lookup) — not one pair of queries per row,
    // which is what the old per-customer helper-method approach did.
    expect($queryCount)->toBeLessThanOrEqual(12);
});
