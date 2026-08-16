<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Order;
use App\Models\SalesDocument;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

test('an order with an issued sales document cannot be deleted', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($user)->post(route('orders.documents.store', $order), [
        'type' => 'invoice',
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Nina Novak'],
        'line_items' => [
            ['description' => 'Torta', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 100, 'vat_rate' => 22],
        ],
    ])->assertRedirect();

    $this->actingAs($user)->delete(route('orders.destroy', $order))->assertStatus(422);

    expect(Order::find($order->id))->not->toBeNull();
    expect(SalesDocument::where('order_id', $order->id)->exists())->toBeTrue();
});

test('an order without any sales documents can still be deleted', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($user)->delete(route('orders.destroy', $order))->assertRedirect(route('orders.index'));

    expect(Order::find($order->id))->toBeNull();
});

test('an appointment with an issued sales document cannot be deleted', function () {
    [$workspace, $user] = createWorkspaceWithUser(['current_workspace_id' => null]);
    $workspace->update(['appointments_enabled' => true]);
    $user->update(['current_workspace_id' => $workspace->id]);
    configureInvoicing($workspace);

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

    $this->actingAs($user)->post(route('appointments.documents.store', $appointment), [
        'type' => 'invoice',
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Ana'],
        'line_items' => [
            ['description' => 'Cut', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 20, 'vat_rate' => 22],
        ],
    ])->assertRedirect();

    $this->actingAs($user)->delete(route('appointments.destroy', $appointment))->assertStatus(422);

    expect(Appointment::find($appointment->id))->not->toBeNull();
    expect(SalesDocument::where('appointment_id', $appointment->id)->exists())->toBeTrue();
});
