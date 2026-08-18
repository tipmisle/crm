<?php

use App\Models\Customer;
use App\Models\Product;
use App\Models\SalesDocument;
use App\Models\Service;

test('global search finds products by name', function () {
    [, $user] = createWorkspaceWithUser();
    $this->actingAs($user);

    Product::create(['name' => 'Unikatna Rojstnodnevna Torta', 'active' => true]);

    $response = $this->getJson(route('search', ['q' => 'Unikatna Rojstnodnevna']));

    $response->assertOk();
    $response->assertJsonFragment(['type' => 'product']);
});

test('global search finds services by name', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    $this->actingAs($user);

    Service::create(['name' => 'Unikatna Gel Manikura', 'default_duration_minutes' => 60, 'active' => true]);

    $response = $this->getJson(route('search', ['q' => 'Unikatna Gel']));

    $response->assertOk();
    $response->assertJsonFragment(['type' => 'service']);
});

test('global search finds a sales document and links back to its order', function () {
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

    $document = SalesDocument::where('order_id', $order->id)->first();

    $response = $this->getJson(route('search', ['q' => $document->document_number]));

    $response->assertOk();
    $response->assertJsonFragment([
        'type' => 'sales_document',
        'href' => route('orders.show', $order),
    ]);
});

test('global search for sales documents respects workspace isolation', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    configureInvoicing($workspaceA);
    [$workspaceB] = createWorkspaceWithUser();
    configureInvoicing($workspaceB);
    [$orderB] = createOrderWithConversation($workspaceB);

    $customerB = Customer::where('workspace_id', $workspaceB->id)->first();

    $documentB = SalesDocument::create([
        'workspace_id' => $workspaceB->id,
        'order_id' => $orderB->id,
        'customer_id' => $customerB->id,
        'type' => 'invoice',
        'source' => 'external',
        'external_document_number' => 'ISOLATED-DOC-9001',
        'issued_at' => now(),
        'currency' => 'EUR',
        'vat_registered' => false,
        'subtotal' => 100,
        'vat_total' => 0,
        'total' => 100,
    ]);

    $response = $this->actingAs($userA)->getJson(route('search', ['q' => 'ISOLATED-DOC-9001']));

    $response->assertOk();
    $response->assertJsonMissing(['id' => $documentB->id, 'type' => 'sales_document']);
    expect($response->json('results'))->toBeEmpty();
});

test('global search finds a business customer by company name and shows it prominently', function () {
    [, $user] = createWorkspaceWithUser();
    $this->actingAs($user);

    Customer::create([
        'full_name' => 'Ana Novak',
        'is_business' => true,
        'company_name' => 'Unikatno Podjetje d.o.o.',
        'tax_number' => 'SI98765432',
    ]);

    $response = $this->getJson(route('search', ['q' => 'Unikatno Podjetje']));

    $response->assertOk();
    $response->assertJsonFragment(['type' => 'customer', 'title' => 'Unikatno Podjetje d.o.o.', 'subtitle' => 'Ana Novak']);
});

test('global search finds a business customer by tax number', function () {
    [, $user] = createWorkspaceWithUser();
    $this->actingAs($user);

    Customer::create([
        'full_name' => 'Ana Novak',
        'is_business' => true,
        'company_name' => 'Podjetje d.o.o.',
        'tax_number' => 'SI11223344',
    ]);

    $response = $this->getJson(route('search', ['q' => 'SI11223344']));

    $response->assertOk();
    $response->assertJsonFragment(['type' => 'customer', 'title' => 'Podjetje d.o.o.']);
});

test('customer list search finds a business customer by company name or tax number', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'is_business' => true,
        'company_name' => 'Poslovna Stranka d.o.o.',
        'tax_number' => 'SI55667788',
    ]);

    $this->actingAs($user)->get(route('customers.index', ['search' => 'Poslovna Stranka']))
        ->assertInertia(fn ($page) => $page->has('customers.data', 1));

    $this->actingAs($user)->get(route('customers.index', ['search' => 'SI55667788']))
        ->assertInertia(fn ($page) => $page->has('customers.data', 1));
});
