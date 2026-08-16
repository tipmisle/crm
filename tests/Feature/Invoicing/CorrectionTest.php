<?php

use App\Models\Appointment;
use App\Models\Message;
use App\Models\SalesDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function issueInvoice(\App\Models\Order $order, array $lineItems = null): SalesDocument
{
    $lineItems ??= [
        ['description' => 'Torta', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 80, 'vat_rate' => 22],
    ];

    test()->post(route('orders.documents.store', $order), [
        'type' => 'invoice',
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Nina Novak'],
        'line_items' => $lineItems,
    ]);

    return SalesDocument::where('order_id', $order->id)->where('type', 'invoice')->firstOrFail();
}

function issueProforma(\App\Models\Order $order): SalesDocument
{
    test()->post(route('orders.documents.store', $order), [
        'type' => 'proforma',
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Nina Novak'],
        'line_items' => [
            ['description' => 'Torta', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 80, 'vat_rate' => 22],
        ],
    ]);

    return SalesDocument::where('order_id', $order->id)->where('type', 'proforma')->firstOrFail();
}

test('an issued invoice cannot be edited or deleted at the model layer', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);
    $document = issueInvoice($order);

    // Each attempt uses a fresh model instance — a failed mutation leaves
    // the in-memory model dirty (the SQL never runs, so the DB row itself
    // is untouched, but the object's own attributes would otherwise carry
    // the rejected change into the next call).
    expect(fn () => $document->fresh()->update(['total' => 999]))->toThrow(\LogicException::class);
    expect(fn () => $document->fresh()->update(['line_items_snapshot' => []]))->toThrow(\LogicException::class);
    expect(fn () => $document->fresh()->delete())->toThrow(\LogicException::class);

    expect((string) $document->fresh()->total)->not->toBe('999.00');

    // Lifecycle fields remain mutable (used by send/correction flows).
    $document->fresh()->update(['sent_at' => now()]);
    expect($document->fresh()->sent_at)->not->toBeNull();
});

test('storno preserves the original invoice exactly and marks it reversed', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);
    $document = issueInvoice($order);

    $originalSnapshot = $document->line_items_snapshot;
    $originalSeller = $document->seller_snapshot;
    $originalCustomer = $document->customer_snapshot;
    $originalTotal = (string) $document->total;
    $originalNumber = $document->document_number;

    $this->post(route('documents.storno', $document), ['reason' => 'Napačen znesek'])
        ->assertSessionHas('success');

    $document->refresh();
    expect($document->status)->toBe('reversed');
    expect($document->document_number)->toBe($originalNumber);
    expect($document->line_items_snapshot)->toBe($originalSnapshot);
    expect($document->seller_snapshot)->toBe($originalSeller);
    expect($document->customer_snapshot)->toBe($originalCustomer);
    expect((string) $document->total)->toBe($originalTotal);
});

test('the correction document exactly reverses the original persisted snapshot amounts', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);
    $document = issueInvoice($order, [
        ['description' => 'Torta', 'quantity' => 2, 'unit' => 'kos', 'unit_price' => 40, 'vat_rate' => 22],
    ]);

    $this->post(route('documents.storno', $document), ['reason' => 'Napačen znesek']);

    $document->refresh();
    $correction = $document->correction;

    expect($correction)->not->toBeNull();
    expect($correction->type)->toBe('storno');
    expect((float) $correction->subtotal)->toBe(-1 * (float) $document->subtotal);
    expect((float) $correction->vat_total)->toBe(-1 * (float) $document->vat_total);
    expect((float) $correction->total)->toBe(-1 * (float) $document->total);
    expect(round((float) $correction->subtotal + (float) $correction->vat_total, 2))->toBe(round((float) $correction->total, 2));

    $originalLine = $document->line_items_snapshot[0];
    $correctionLine = $correction->line_items_snapshot[0];
    expect((float) $correctionLine['gross'])->toBe(-1 * (float) $originalLine['gross']);
    expect((float) $correctionLine['net'])->toBe(-1 * (float) $originalLine['net']);
    expect((float) $correctionLine['vat'])->toBe(-1 * (float) $originalLine['vat']);
});

test('changing the source order after storno never affects the correction calculation', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);
    $document = issueInvoice($order);

    $this->post(route('documents.storno', $document), ['reason' => 'Napačen znesek']);
    $correctionTotal = (string) $document->fresh()->correction->total;

    $order->update(['price' => 999999]);

    expect((string) $document->fresh()->correction->fresh()->total)->toBe($correctionTotal);
});

test('changing the source appointment after storno never affects the correction calculation', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);

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

    $this->post(route('appointments.documents.store', $appointment), [
        'type' => 'invoice',
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Nina Novak'],
        'line_items' => [
            ['description' => 'Fotografiranje', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 120, 'vat_rate' => 22],
        ],
    ]);

    $document = SalesDocument::where('appointment_id', $appointment->id)->where('type', 'invoice')->firstOrFail();

    $this->post(route('documents.storno', $document), ['reason' => 'Termin odpovedan']);
    $correctionTotal = (string) $document->fresh()->correction->total;

    $appointment->update(['price' => 1]);

    expect((string) $document->fresh()->correction->fresh()->total)->toBe($correctionTotal);
    expect($document->fresh()->status)->toBe('reversed');
});

test('an invoice cannot be storno-ed twice', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);
    $document = issueInvoice($order);

    $this->post(route('documents.storno', $document), ['reason' => 'Napačen znesek'])
        ->assertSessionHas('success');

    $this->post(route('documents.storno', $document), ['reason' => 'Poskus drugič'])
        ->assertStatus(422);

    expect(SalesDocument::where('corrects_document_id', $document->id)->count())->toBe(1);
});

test('a reason is required to issue a storno', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);
    $document = issueInvoice($order);

    $this->post(route('documents.storno', $document), ['reason' => ''])
        ->assertSessionHasErrors('reason');

    expect($document->fresh()->status)->toBe('issued');
});

test('the original and correction reference each other correctly', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);
    $document = issueInvoice($order);

    $this->post(route('documents.storno', $document), ['reason' => 'Napačen znesek']);

    $document->refresh();
    $correction = $document->correction;

    expect($correction->corrects_document_id)->toBe($document->id);
    expect($correction->correctsDocument->id)->toBe($document->id);
    expect($document->correction->id)->toBe($correction->id);
});

test('workspace isolation: a user from another workspace cannot cancel, storno, or download a document', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    configureInvoicing($workspaceA);
    [$orderA] = createOrderWithConversation($workspaceA);
    $this->actingAs($userA);
    $invoice = issueInvoice($orderA);
    $proforma = issueProforma($orderA);

    [$workspaceB, $userB] = createWorkspaceWithUser();

    $this->actingAs($userB)->post(route('documents.storno', $invoice), ['reason' => 'x'])->assertNotFound();
    $this->actingAs($userB)->post(route('documents.cancel', $proforma))->assertNotFound();
    $this->actingAs($userB)->get(route('documents.download', $invoice))->assertNotFound();

    expect($invoice->fresh()->status)->toBe('issued');
    expect($proforma->fresh()->status)->toBe('issued');
});

test('proforma cancellation never creates an invoice correction and keeps its original number', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);
    $proforma = issueProforma($order);
    $originalNumber = $proforma->document_number;

    $this->post(route('documents.cancel', $proforma))->assertSessionHas('success');

    $proforma->refresh();
    expect($proforma->status)->toBe('cancelled');
    expect($proforma->document_number)->toBe($originalNumber);
    expect($proforma->cancelled_at)->not->toBeNull();
    expect(SalesDocument::where('type', 'storno')->count())->toBe(0);
    expect(SalesDocument::where('type', 'invoice')->count())->toBe(0);
});

test('a cancelled proforma cannot be sent as an active payment request', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);
    $proforma = issueProforma($order);

    $this->post(route('documents.cancel', $proforma));

    $this->post(route('documents.send', $proforma), ['body' => 'Pošiljam predračun.'])
        ->assertStatus(422);
});

test('document numbers are never reused across proforma cancellation and invoice storno', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$orderA] = createOrderWithConversation($workspace);
    [$orderB] = createOrderWithConversation($workspace);
    $this->actingAs($user);

    $invoiceA = issueInvoice($orderA);
    $invoiceB = issueInvoice($orderB);

    $this->post(route('documents.storno', $invoiceA), ['reason' => 'A']);
    $this->post(route('documents.storno', $invoiceB), ['reason' => 'B']);

    $stornoNumbers = SalesDocument::where('type', 'storno')->pluck('document_number')->all();
    expect($stornoNumbers)->toHaveCount(2);
    expect($stornoNumbers[0])->not->toBe($stornoNumbers[1]);

    // Storno numbers must never collide with invoice numbers.
    $invoiceNumbers = SalesDocument::where('type', 'invoice')->pluck('document_number')->all();
    expect(array_intersect($stornoNumbers, $invoiceNumbers))->toBeEmpty();
});

test('the correction PDF is only downloadable by the owning workspace', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);
    $document = issueInvoice($order);

    $this->post(route('documents.storno', $document), ['reason' => 'Napačen znesek']);
    $correction = $document->fresh()->correction;

    $this->actingAs($user)->get(route('documents.download', $correction))->assertOk();

    [$workspaceB, $userB] = createWorkspaceWithUser();
    $this->actingAs($userB)->get(route('documents.download', $correction))->assertNotFound();
});

test('sending the storno document uses the original subject Order conversation', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order, $conversation] = createOrderWithConversation($workspace);
    $this->actingAs($user);
    $document = issueInvoice($order);

    $this->post(route('documents.storno', $document), ['reason' => 'Napačen znesek']);
    $correction = $document->fresh()->correction;

    Http::fake(['*/messages*' => Http::response(['message_id' => 'mid_storno'], 200)]);

    $this->post(route('documents.send', $correction), [
        'body' => 'Živjo Nina, pošiljam popravek/storno računa. Dokument je v priponki.',
    ])->assertSessionHas('success');

    $message = Message::where('conversation_id', $conversation->id)->latest('id')->first();
    expect($message)->not->toBeNull();
    expect($message->body)->toContain('popravek/storno');
});
