<?php

use App\Models\Customer;
use App\Models\SalesDocument;
use Illuminate\Support\Facades\Storage;

beforeEach(fn () => Storage::fake('local'));

test('a private individual customer defaults the recipient name to full_name and saving it back keeps full_name in sync', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace, ['full_name' => 'Nina Novak']);
    $customer = $order->customer;

    $create = $this->actingAs($user)->get(route('orders.documents.create', $order));
    $create->assertInertia(fn ($page) => $page->where('recipient.name', 'Nina Novak'));

    $this->actingAs($user)->post(route('orders.documents.store', $order), [
        'type' => 'invoice',
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Nina Novak', 'city' => 'Ljubljana'],
        'save_recipient_to_customer' => true,
        'line_items' => [
            ['description' => 'Torta', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 100, 'vat_rate' => 22],
        ],
    ])->assertRedirect();

    $customer->refresh();
    expect($customer->full_name)->toBe('Nina Novak');
    expect($customer->city)->toBe('Ljubljana');

    expect(SalesDocument::first()->customer_snapshot['name'])->toBe('Nina Novak');
});

test('a business customer with a contact person defaults the recipient name to company_name and saving it back never overwrites full_name', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    configureInvoicing($workspace);
    [$order] = createOrderWithConversation($workspace, [
        'full_name' => 'Nina Novak', // the live contact person's own identity
        'is_business' => true,
        'company_name' => 'Novak d.o.o.',
    ]);
    $customer = $order->customer;

    $create = $this->actingAs($user)->get(route('orders.documents.create', $order));
    $create->assertInertia(fn ($page) => $page->where('recipient.name', 'Novak d.o.o.'));

    $this->actingAs($user)->post(route('orders.documents.store', $order), [
        'type' => 'invoice',
        'issued_at' => now()->format('Y-m-d'),
        'due_date' => now()->addDays(8)->format('Y-m-d'),
        'recipient' => ['name' => 'Novak d.o.o.', 'city' => 'Maribor'],
        'save_recipient_to_customer' => true,
        'line_items' => [
            ['description' => 'Torta', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 100, 'vat_rate' => 22],
        ],
    ])->assertRedirect();

    $customer->refresh();
    // full_name (the contact person's live identity) must survive
    // untouched — only company_name is updated from the recipient name.
    expect($customer->full_name)->toBe('Nina Novak');
    expect($customer->company_name)->toBe('Novak d.o.o.');
    expect($customer->city)->toBe('Maribor');

    // The immutable issued-document snapshot still reflects what was
    // actually billed to, regardless of what the live Customer looks like
    // afterwards.
    expect(SalesDocument::first()->customer_snapshot['name'])->toBe('Novak d.o.o.');
});
