<?php

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderNote;
use App\Models\SalesDocument;
use App\Services\CustomerErasureService;
use Illuminate\Support\Facades\Storage;

test('erasure clears order/appointment free text and OrderNote bodies, but keeps operational data', function () {
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
        'description' => 'Ana ima alergijo na oreščke',
        'internal_notes' => 'Ana je zahtevna stranka',
        'customer_notes' => 'Prosim dostavite zjutraj',
        'price' => 60,
        'status' => 'new',
    ]);

    $note = OrderNote::create([
        'order_id' => $order->id,
        'user_id' => $user->id,
        'body' => 'Ana je poklicala glede naročila',
    ]);

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Striženje',
        'description' => 'Ana ima občutljivo kožo',
        'internal_notes' => 'Ana raje zjutraj',
        'customer_notes' => 'Pokličite pred terminom',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 30,
        'price' => 30,
        'status' => 'requested',
    ]);

    app(CustomerErasureService::class)->erase($customer);

    $order->refresh();
    expect($order->description)->toBeNull();
    expect($order->internal_notes)->toBeNull();
    expect($order->customer_notes)->toBeNull();
    // Operational data survives.
    expect($order->title)->toBe('Torta');
    expect((float) $order->price)->toBe(60.0);
    expect($order->status)->toBe('new');

    expect($note->fresh()->body)->toBeNull();

    $appointment->refresh();
    expect($appointment->description)->toBeNull();
    expect($appointment->internal_notes)->toBeNull();
    expect($appointment->customer_notes)->toBeNull();
    expect($appointment->service_name)->toBe('Striženje');
    expect((float) $appointment->price)->toBe(30.0);
});

test('erasure anonymizes the customer name inside ActivityLog descriptions for related records', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Posebna Novak',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Torta',
        'price' => 60,
        'status' => 'new',
    ]);

    $this->actingAs($user);
    ActivityLog::record('order_created', "Naročilo {$order->order_number} ustvarjeno za Ana Posebna Novak", $order);
    ActivityLog::record('customer_created', 'Stranka Ana Posebna Novak je bila dodana', $customer);

    app(CustomerErasureService::class)->erase($customer);

    $logs = ActivityLog::where('subject_type', Order::class)->where('subject_id', $order->id)->get();
    foreach ($logs as $log) {
        expect($log->description)->not->toContain('Ana Posebna Novak');
    }

    $customerLog = ActivityLog::where('subject_type', Customer::class)->where('subject_id', $customer->id)->first();
    expect($customerLog->description)->not->toContain('Ana Posebna Novak');
    expect($customerLog->description)->toContain('Izbrisana stranka');
});

test('erasure never touches an issued SalesDocument snapshot', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace, ['full_name' => 'Nina Novak']);
    $customer = $order->customer;

    Storage::fake('local');

    $this->actingAs($user)->post(route('orders.documents.store', $order), [
        'type' => 'invoice',
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Nina Novak'],
        'line_items' => [
            ['description' => 'Torta', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 100, 'vat_rate' => 22],
        ],
    ]);

    $document = SalesDocument::where('order_id', $order->id)->first();
    $originalSnapshot = $document->customer_snapshot;

    app(CustomerErasureService::class)->erase($customer);

    expect($document->fresh()->customer_snapshot)->toBe($originalSnapshot);
    expect($document->fresh()->customer_snapshot['name'])->toBe('Nina Novak');
});
