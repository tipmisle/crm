<?php

use App\Models\SalesDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function issuePayload(string $type, array $overrides = []): array
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

test('a custom prefix with a mid-year starting number issues the correct first and second numbers', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace, [
        'invoice_prefix' => '2026-',
        'invoice_next_number' => 13, // owner told us "last issued = 12"
    ]);
    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($user)->post(route('orders.documents.store', $order), issuePayload('invoice'))
        ->assertRedirect(route('orders.show', $order));

    $first = SalesDocument::where('order_id', $order->id)->where('type', 'invoice')->first();
    expect($first->document_number)->toBe('2026-13');

    [$order2] = createOrderWithConversation($workspace);
    $this->actingAs($user)->post(route('orders.documents.store', $order2), issuePayload('invoice'));

    $second = SalesDocument::where('order_id', $order2->id)->where('type', 'invoice')->first();
    expect($second->document_number)->toBe('2026-14');
});

test('invoice and proforma sequences are independent', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace, [
        'invoice_prefix' => '2026-',
        'invoice_next_number' => 1,
        'proforma_prefix' => 'P-2026-',
        'proforma_next_number' => 8,
    ]);
    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($user)->post(route('orders.documents.store', $order), issuePayload('proforma'));
    $this->actingAs($user)->post(route('orders.documents.store', $order), issuePayload('invoice'));

    $proforma = SalesDocument::where('order_id', $order->id)->where('type', 'proforma')->first();
    $invoice = SalesDocument::where('order_id', $order->id)->where('type', 'invoice')->first();

    expect($proforma->document_number)->toBe('P-2026-8');
    expect($invoice->document_number)->toBe('2026-1');
});

test('rapid sequential issuance never produces duplicate numbers', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace, ['invoice_prefix' => '2026-', 'invoice_next_number' => 1]);
    [$order] = createOrderWithConversation($workspace);

    $numbers = [];
    for ($i = 0; $i < 3; $i++) {
        $this->actingAs($user)->post(route('orders.documents.store', $order), issuePayload('invoice'));
        $numbers[] = SalesDocument::where('order_id', $order->id)->where('type', 'invoice')->orderByDesc('id')->first()->document_number;
    }

    expect($numbers)->toBe(['2026-1', '2026-2', '2026-3']);
    expect(array_unique($numbers))->toHaveCount(3);
});

test('opening the create form and previewing the settings page never consumes a number', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $settings = configureInvoicing($workspace, ['invoice_prefix' => '2026-', 'invoice_next_number' => 5]);
    [$order] = createOrderWithConversation($workspace);

    $this->actingAs($user)->get(route('orders.documents.create', ['order' => $order->id, 'type' => 'invoice']))->assertOk();
    $this->actingAs($user)->get(route('settings.invoicing.preview', ['type' => 'invoice']))->assertOk();
    $this->actingAs($user)->get(route('settings.invoicing.edit'))->assertOk();

    expect($settings->fresh()->invoice_next_number)->toBe(5);
});

test('an external document upload never touches invoice numbering', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $settings = configureInvoicing($workspace, ['invoice_prefix' => '2026-', 'invoice_next_number' => 1]);
    [$order] = createOrderWithConversation($workspace);

    $file = UploadedFile::fake()->create('racun.pdf', 100, 'application/pdf');

    $this->actingAs($user)->post(route('orders.documents.external.store', $order), [
        'file' => $file,
        'type' => 'invoice',
        'external_document_number' => 'EXT-001',
    ])->assertRedirect();

    $document = SalesDocument::where('order_id', $order->id)->first();
    expect($document->source)->toBe('external');
    expect($document->prefix)->toBeNull();
    expect($document->sequence_number)->toBeNull();
    expect($document->document_number)->toBeNull();
    expect($document->external_document_number)->toBe('EXT-001');
    expect($settings->fresh()->invoice_next_number)->toBe(1);
});
