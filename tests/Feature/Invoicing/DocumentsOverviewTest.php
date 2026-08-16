<?php

use App\Models\Appointment;
use App\Models\SalesDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

function overviewIssueInvoice(\App\Models\Order $order, array $overrides = []): SalesDocument
{
    test()->post(route('orders.documents.store', $order), array_merge([
        'type' => 'invoice',
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Nina Novak'],
        'line_items' => [
            ['description' => 'Torta', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 80, 'vat_rate' => 22],
        ],
    ], $overrides));

    return SalesDocument::where('order_id', $order->id)->where('type', 'invoice')->latest('id')->firstOrFail();
}

test('the documents overview shows documents from both Orders and Appointments', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);
    $orderInvoice = overviewIssueInvoice($order);

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $order->customer_id,
        'conversation_id' => $order->conversation_id,
        'channel_id' => $order->channel_id,
        'service_name' => 'Gel nohti',
        'appointment_date' => now()->addDay(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'price' => 35,
        'payment_status' => 'unpaid',
        'status' => 'confirmed',
    ]);
    $this->post(route('appointments.documents.store', $appointment), [
        'type' => 'invoice',
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Nina Novak'],
        'line_items' => [
            ['description' => 'Gel nohti', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 35, 'vat_rate' => 22],
        ],
    ]);
    $appointmentInvoice = SalesDocument::where('appointment_id', $appointment->id)->firstOrFail();

    $response = $this->get(route('documents.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 2)
    );

    $numbers = collect($response->viewData('page')['props']['documents']['data'])->pluck('document_number')->all();
    expect($numbers)->toContain($orderInvoice->document_number, $appointmentInvoice->document_number);
});

test('each row links to the correct Order or Appointment subject', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace, ['full_name' => 'Nina Novak']);
    $this->actingAs($user);
    overviewIssueInvoice($order);

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $order->customer_id,
        'conversation_id' => $order->conversation_id,
        'channel_id' => $order->channel_id,
        'service_name' => 'Gel nohti',
        'appointment_date' => now()->addDay(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'price' => 35,
        'payment_status' => 'unpaid',
        'status' => 'confirmed',
    ]);
    $this->post(route('appointments.documents.store', $appointment), [
        'type' => 'invoice',
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Nina Novak'],
        'line_items' => [
            ['description' => 'Gel nohti', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 35, 'vat_rate' => 22],
        ],
    ]);

    $response = $this->get(route('documents.index'));
    $rows = collect($response->viewData('page')['props']['documents']['data']);

    $orderRow = $rows->firstWhere('order_id', $order->id);
    expect($orderRow['order']['order_number'])->toBe($order->order_number);
    expect($orderRow['order']['title'])->toBe($order->title);

    $appointmentRow = $rows->firstWhere('appointment_id', $appointment->id);
    expect($appointmentRow['appointment']['appointment_number'])->toBe($appointment->appointment_number);
    expect($appointmentRow['appointment']['service_name'])->toBe('Gel nohti');

    expect($orderRow['customer']['full_name'])->toBe('Nina Novak');
});

test('search matches by document number', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);
    $invoice = overviewIssueInvoice($order);

    [$orderB] = createOrderWithConversation($workspace, ['full_name' => 'Drugi']);
    overviewIssueInvoice($orderB);

    $response = $this->get(route('documents.index', ['search' => $invoice->document_number]));

    $response->assertInertia(fn ($page) => $page->has('documents.data', 1)
        ->where('documents.data.0.document_number', $invoice->document_number));
});

test('search matches by customer name', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace, ['full_name' => 'Ana Kovač']);
    $this->actingAs($user);
    overviewIssueInvoice($order);

    [$orderB] = createOrderWithConversation($workspace, ['full_name' => 'Boris Zupan']);
    overviewIssueInvoice($orderB);

    $response = $this->get(route('documents.index', ['search' => 'Kovač']));

    $response->assertInertia(fn ($page) => $page->has('documents.data', 1)
        ->where('documents.data.0.customer.full_name', 'Ana Kovač'));
});

test('type, date range, and sent filters narrow the results', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);
    $invoice = overviewIssueInvoice($order);

    $this->post(route('orders.documents.store', $order), [
        'type' => 'proforma',
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Nina Novak'],
        'line_items' => [
            ['description' => 'Torta', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 80, 'vat_rate' => 22],
        ],
    ]);

    $this->get(route('documents.index', ['type' => 'invoice']))
        ->assertInertia(fn ($page) => $page->has('documents.data', 1)->where('documents.data.0.type', 'invoice'));

    $this->get(route('documents.index', ['type' => 'proforma']))
        ->assertInertia(fn ($page) => $page->has('documents.data', 1)->where('documents.data.0.type', 'proforma'));

    $this->get(route('documents.index', ['issued_from' => now()->addDay()->format('Y-m-d')]))
        ->assertInertia(fn ($page) => $page->has('documents.data', 0));

    $this->get(route('documents.index', ['issued_from' => now()->format('Y-m-d'), 'issued_to' => now()->format('Y-m-d')]))
        ->assertInertia(fn ($page) => $page->has('documents.data', 2));

    $this->get(route('documents.index', ['sent' => 'sent']))
        ->assertInertia(fn ($page) => $page->has('documents.data', 0));

    Http::fake(['*/messages*' => Http::response(['message_id' => 'mid_ok'], 200)]);
    $this->post(route('documents.send', $invoice), ['body' => 'Pošiljam račun.']);

    $this->get(route('documents.index', ['sent' => 'sent']))
        ->assertInertia(fn ($page) => $page->has('documents.data', 1)->where('documents.data.0.id', $invoice->id));

    $this->get(route('documents.index', ['sent' => 'not_sent']))
        ->assertInertia(fn ($page) => $page->has('documents.data', 1));
});

test('pagination preserves the active filters', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);

    for ($i = 0; $i < 30; $i++) {
        SalesDocument::create([
            'workspace_id' => $workspace->id,
            'order_id' => $order->id,
            'customer_id' => $order->customer_id,
            'type' => 'invoice',
            'source' => 'external',
            'external_document_number' => "EXT-{$i}",
            'issued_at' => now()->subDays($i),
            'currency' => 'EUR',
            'total' => 10,
        ]);
    }

    $response = $this->get(route('documents.index', ['type' => 'invoice']));

    $response->assertInertia(fn ($page) => $page->where('filters.type', 'invoice'));

    $links = collect($response->viewData('page')['props']['documents']['links']);
    $nextLink = $links->firstWhere('label', 'Naslednja &raquo;');

    expect($nextLink)->not->toBeNull();
    expect($nextLink['url'])->toContain('type=invoice');
});

test('storno and cancelled documents show the correct status labels', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);
    $invoice = overviewIssueInvoice($order);

    $this->post(route('documents.storno', $invoice), ['reason' => 'Napačen znesek']);

    $response = $this->get(route('documents.index'));
    $rows = collect($response->viewData('page')['props']['documents']['data']);

    $originalRow = $rows->firstWhere('id', $invoice->id);
    $stornoRow = $rows->firstWhere('type', 'storno');

    expect($originalRow['status_label'])->toBe('Storniran');
    expect($stornoRow['status_label'])->toBeNull();
    expect($stornoRow['corrects_document']['document_number'])->toBe($invoice->document_number);
});

test('external documents display correctly in the overview', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace);
    $this->actingAs($user);

    Storage::fake('local');
    $file = \Illuminate\Http\UploadedFile::fake()->create('racun.pdf', 100, 'application/pdf');
    $this->post(route('orders.documents.external.store', $order), [
        'file' => $file,
        'type' => 'invoice',
        'external_document_number' => 'MINIMAX-42',
    ]);

    $response = $this->get(route('documents.index'));

    $response->assertInertia(fn ($page) => $page
        ->has('documents.data', 1)
        ->where('documents.data.0.source', 'external')
        ->where('documents.data.0.external_document_number', 'MINIMAX-42')
        ->where('documents.data.0.document_number', null)
    );
});

test('workspace isolation: a user only ever sees their own workspace documents', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    // Distinct prefix so workspace A's document number can never
    // coincidentally collide with workspace B's independently-numbered
    // "2026-1" default — the search-leak assertion below needs a number
    // that is unambiguously workspace A's own.
    configureInvoicing($workspaceA, ['invoice_prefix' => 'WSA-']);
    [$orderA] = createOrderWithConversation($workspaceA);
    $this->actingAs($userA);
    $invoiceA = overviewIssueInvoice($orderA);

    // Built without createOrderWithConversation()'s shared Meta channel
    // helper — that defaults to a fixed external_account_id, which would
    // collide with workspace A's channel under the (intentionally) global
    // uniqueness constraint tested elsewhere (MetaTenantRoutingTest).
    // Issuing a document only needs a Customer + Order, not a conversation.
    [$workspaceB, $userB] = createWorkspaceWithUser();
    configureInvoicing($workspaceB);
    $customerB = \App\Models\Customer::create([
        'workspace_id' => $workspaceB->id,
        'full_name' => 'Workspace B stranka',
    ]);
    $orderB = \App\Models\Order::create([
        'workspace_id' => $workspaceB->id,
        'customer_id' => $customerB->id,
        'title' => 'Naročilo B',
        'price' => 50,
        'status' => 'new',
    ]);
    $this->actingAs($userB);
    overviewIssueInvoice($orderB);

    $response = $this->actingAs($userB)->get(route('documents.index'));
    $response->assertInertia(fn ($page) => $page->has('documents.data', 1));

    // Searching by workspace A's document number from workspace B must
    // surface nothing — no existence leakage through search either.
    $response = $this->actingAs($userB)->get(route('documents.index', ['search' => $invoiceA->document_number]));
    $response->assertInertia(fn ($page) => $page->has('documents.data', 0));

    $this->actingAs($userB)->get(route('documents.download', $invoiceA))->assertNotFound();
});
