<?php

use App\Models\SalesDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

test('a send failure leaves the document issued and downloadable, and a resend can succeed', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);

    Http::fakeSequence('*/messages*')
        ->push(['error' => ['message' => 'boom']], 400)
        ->push(['message_id' => 'mid_ok'], 200);

    $this->actingAs($user)->post(route('orders.documents.store', $order), [
        'type' => 'invoice',
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Nina Novak'],
        'line_items' => [
            ['description' => 'Torta', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 100, 'vat_rate' => 22],
        ],
    ]);

    $document = SalesDocument::where('order_id', $order->id)->firstOrFail();
    $number = $document->document_number;

    $this->actingAs($user)->post(route('documents.send', $document), ['body' => 'Živjo, pošiljam račun.'])
        ->assertSessionHas('error');

    $document->refresh();
    expect($document->sent_at)->toBeNull();
    expect($document->document_number)->toBe($number);
    $this->actingAs($user)->get(route('documents.download', $document))->assertOk();

    $this->actingAs($user)->post(route('documents.send', $document), ['body' => 'Živjo, pošiljam račun znova.'])
        ->assertSessionHas('success');

    expect($document->fresh()->sent_at)->not->toBeNull();
});
