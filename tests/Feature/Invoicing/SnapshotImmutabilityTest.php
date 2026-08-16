<?php

use App\Models\SalesDocument;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

test('issued document snapshots are unchanged after the order, customer, and settings are edited', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $settings = configureInvoicing($workspace, ['invoice_prefix' => '2026-', 'invoice_next_number' => 1]);
    [$order] = createOrderWithConversation($workspace);
    $customer = $order->customer;

    $this->actingAs($user)->post(route('orders.documents.store', $order), [
        'type' => 'invoice',
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => $customer->full_name],
        'line_items' => [
            ['description' => 'Torta', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 100, 'vat_rate' => 22],
        ],
    ]);

    $document = SalesDocument::where('order_id', $order->id)->first();
    $originalSeller = $document->seller_snapshot;
    $originalCustomer = $document->customer_snapshot;
    $originalLineItems = $document->line_items_snapshot;
    $originalTotal = (string) $document->total;

    $customer->update(['full_name' => 'Popolnoma drugo ime']);
    $order->update(['price' => 999]);
    $settings->update(['company_name' => 'Drugo podjetje', 'iban' => 'SI00999999999999999']);

    $document->refresh();

    expect($document->seller_snapshot)->toBe($originalSeller);
    expect($document->customer_snapshot)->toBe($originalCustomer);
    expect($document->line_items_snapshot)->toBe($originalLineItems);
    expect((string) $document->total)->toBe($originalTotal);
    expect($document->seller_snapshot['company_name'])->not->toBe('Drugo podjetje');
    expect($document->customer_snapshot['name'])->not->toBe('Popolnoma drugo ime');
});
