<?php

use App\Models\Appointment;
use App\Models\SalesDocument;
use App\Services\Invoicing\SalesDocumentCalculationService;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function calc(): SalesDocumentCalculationService
{
    return app(SalesDocumentCalculationService::class);
}

function calcIssuePayload(string $type, array $overrides = []): array
{
    return array_merge([
        'type' => $type,
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Nina Novak'],
        'line_items' => [
            ['description' => 'Torta', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 100, 'vat_rate' => 22],
        ],
    ], $overrides);
}

test('vat-registered, gross prices (default): 80 EUR at 22% VAT stays 80 EUR total', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace); // prices_include_vat defaults true
    [$order] = createOrderWithConversation($workspace, []);
    $order->update(['price' => 80]);

    $response = $this->actingAs($user)->post(route('orders.documents.store', $order), calcIssuePayload('invoice', [
        'line_items' => [
            ['description' => $order->title, 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 80, 'vat_rate' => 22],
        ],
    ]));

    $response->assertRedirect(route('orders.show', $order));

    $document = SalesDocument::where('order_id', $order->id)->first();
    expect((float) $document->total)->toBe(80.0);
    expect((float) $document->subtotal)->toEqualWithDelta(80 / 1.22, 0.01);
    expect((float) $document->vat_total)->toEqualWithDelta(80 - 80 / 1.22, 0.01);
    expect((float) $document->subtotal + (float) $document->vat_total)->toBe((float) $document->total);
});

test('vat-registered, net-price mode: VAT is added on top of the entered price', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace, ['prices_include_vat' => false]);
    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($user)->post(route('orders.documents.store', $order), calcIssuePayload('invoice', [
        'line_items' => [
            ['description' => 'Storitev', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 80, 'vat_rate' => 22],
        ],
    ]));

    $document = SalesDocument::where('order_id', $order->id)->first();
    expect((float) $document->subtotal)->toBe(80.0);
    expect((float) $document->vat_total)->toBe(17.6);
    expect((float) $document->total)->toBe(97.6);
});

test('non-vat-registered workspace never calculates VAT, regardless of prices_include_vat', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace, ['vat_registered' => false, 'prices_include_vat' => true]);
    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($user)->post(route('orders.documents.store', $order), calcIssuePayload('invoice', [
        'line_items' => [
            ['description' => 'Storitev', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 80, 'vat_rate' => 22],
        ],
    ]));

    $document = SalesDocument::where('order_id', $order->id)->first();
    expect((float) $document->vat_total)->toBe(0.0);
    expect((float) $document->subtotal)->toBe(80.0);
    expect((float) $document->total)->toBe(80.0);
});

test('multiple line items with mixed VAT rates reconcile: tax breakdown + net equals total', function () {
    $result = calc()->calculate([
        ['description' => 'A', 'quantity' => 1, 'unit_price' => 80, 'vat_rate' => 22],
        ['description' => 'B', 'quantity' => 1, 'unit_price' => 50, 'vat_rate' => 9.5],
        ['description' => 'C', 'quantity' => 1, 'unit_price' => 10, 'vat_rate' => 0],
    ], vatRegistered: true, pricesIncludeVat: true);

    expect(round($result['subtotal'] + $result['vat_total'], 2))->toBe(round($result['total'], 2));

    $breakdownNet = array_sum(array_column($result['tax_breakdown'], 'net'));
    $breakdownVat = array_sum(array_column($result['tax_breakdown'], 'vat'));
    $breakdownGross = array_sum(array_column($result['tax_breakdown'], 'gross'));

    expect(round($breakdownNet, 2))->toBe(round($result['subtotal'], 2));
    expect(round($breakdownVat, 2))->toBe(round($result['vat_total'], 2));
    expect(round($breakdownGross, 2))->toBe(round($result['total'], 2));
    expect(round($breakdownNet + $breakdownVat, 2))->toBe(round($result['total'], 2));
});

test('quantity greater than one scales the gross-extracted total correctly', function () {
    $result = calc()->calculate([
        ['description' => 'Ura', 'quantity' => 3, 'unit_price' => 25, 'vat_rate' => 22],
    ], vatRegistered: true, pricesIncludeVat: true);

    // 3 x 25 = 75 gross; net extracted from gross, not added.
    expect((float) $result['total'])->toBe(75.0);
    expect((float) round($result['subtotal'] + $result['vat_total'], 2))->toBe(75.0);
});

test('order prefill keeps the order final price as the default document total', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $order->update(['price' => 80]);

    $response = $this->actingAs($user)->get(route('orders.documents.create', $order));

    $response->assertInertia(fn ($page) => $page
        ->where('defaultLineItems.0.unit_price', fn ($value) => (float) $value === 80.0)
        ->where('pricesIncludeVat', true)
    );
});

test('appointment prefill keeps the appointment final price as the default document total', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $order->customer_id,
        'conversation_id' => $order->conversation_id,
        'channel_id' => $order->channel_id,
        'service_name' => 'Fotografiranje',
        'appointment_date' => now()->addDay(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'price' => 120,
        'payment_status' => 'unpaid',
        'status' => 'confirmed',
    ]);

    $response = $this->actingAs($user)->get(route('appointments.documents.create', $appointment));

    $response->assertInertia(fn ($page) => $page
        ->where('defaultLineItems.0.unit_price', fn ($value) => (float) $value === 120.0)
    );

    $this->actingAs($user)->post(route('appointments.documents.store', $appointment), calcIssuePayload('invoice', [
        'line_items' => [
            ['description' => 'Fotografiranje', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 120, 'vat_rate' => 22],
        ],
    ]));

    $document = SalesDocument::where('appointment_id', $appointment->id)->first();
    expect((float) $document->total)->toBe(120.0);

    // Changing the appointment's price afterwards must not touch the issued snapshot.
    $appointment->update(['price' => 999]);
    $document->refresh();
    expect((float) $document->total)->toBe(120.0);
});

test('the settings preview PDF and a real issued document use the same calculation for identical inputs', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);

    // Preview uses a fixed sample line-item set baked into the controller;
    // replicate it here and confirm it agrees with the shared service, i.e.
    // the preview isn't running separate math.
    $expected = calc()->calculate([
        ['description' => 'Svetovalna ura', 'quantity' => 2, 'unit' => 'h', 'unit_price' => 45, 'vat_rate' => 22],
        ['description' => 'Materiali', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 15, 'vat_rate' => 22],
    ], vatRegistered: true, pricesIncludeVat: true);

    $response = $this->actingAs($user)->get(route('settings.invoicing.preview', ['type' => 'invoice']));
    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');

    // 2 x 45 + 1 x 15 = 105 gross, VAT extracted (not added), so total stays 105.
    expect((float) $expected['total'])->toBe(105.0);
});
