<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Product;

function ordersCsvRows(\Illuminate\Testing\TestResponse $response): array
{
    $content = $response->streamedContent();
    // Strip the UTF-8 BOM before parsing so str_getcsv doesn't fold it
    // into the first header cell.
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $lines = array_filter(explode("\n", trim($content)), fn ($l) => $l !== '');

    return array_map(fn ($line) => str_getcsv($line, ';'), $lines);
}

test('the orders CSV export uses the same filters as the orders index', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana Novak', 'email' => 'ana@example.com']);

    Order::create(['workspace_id' => $workspace->id, 'customer_id' => $customer->id, 'title' => 'Torta', 'price' => 50, 'status' => 'new']);
    Order::create(['workspace_id' => $workspace->id, 'customer_id' => $customer->id, 'title' => 'Piškoti', 'price' => 20, 'status' => 'confirmed']);

    $indexResponse = $this->actingAs($user)->get(route('orders.index', ['status' => 'confirmed']));
    $indexOrderIds = collect($indexResponse->viewData('page')['props']['orders']['data'])->pluck('id')->all();

    $exportResponse = $this->actingAs($user)->get(route('orders.export', ['status' => 'confirmed']));
    $rows = ordersCsvRows($exportResponse);
    $dataRows = array_slice($rows, 1);

    expect($indexOrderIds)->toHaveCount(1);
    expect($dataRows)->toHaveCount(1);
    expect($dataRows[0][6])->toBe('Piškoti'); // Naslov naročila column
});

test('the orders export includes every matching row, not only the first pagination page', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana Novak']);

    for ($i = 0; $i < 25; $i++) {
        Order::create(['workspace_id' => $workspace->id, 'customer_id' => $customer->id, 'title' => "Naročilo {$i}", 'price' => 10, 'status' => 'new']);
    }

    // Index paginates at 20 — confirm that ceiling exists before proving
    // the export ignores it.
    $indexResponse = $this->actingAs($user)->get(route('orders.index'));
    expect($indexResponse->viewData('page')['props']['orders']['data'])->toHaveCount(20);

    $exportResponse = $this->actingAs($user)->get(route('orders.export'));
    $rows = ordersCsvRows($exportResponse);

    expect($rows)->toHaveCount(26); // header + 25 orders
});

test('the orders export uses current workspace-editable status labels, not internal keys', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    OrderStatus::where('workspace_id', $workspace->id)->where('key', 'confirmed')->update(['label' => 'Moja Zelena Faza']);
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana Novak']);
    Order::create(['workspace_id' => $workspace->id, 'customer_id' => $customer->id, 'title' => 'Torta', 'price' => 50, 'status' => 'confirmed', 'payment_status' => 'paid']);

    $rows = ordersCsvRows($this->actingAs($user)->get(route('orders.export')));

    expect($rows[1][7])->toBe('Moja Zelena Faza'); // Status column
    expect($rows[1][8])->toBe('Plačano'); // Status plačila column
    expect($rows[1][7])->not->toBe('confirmed');
});

test('a linked product name is exported, but historical order price is not recalculated from it', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana Novak']);
    $product = Product::create(['workspace_id' => $workspace->id, 'name' => 'Rojstnodnevna torta', 'default_price' => 999]);
    Order::create([
        'workspace_id' => $workspace->id, 'customer_id' => $customer->id, 'catalog_item_id' => $product->id,
        'title' => 'Naročena torta', 'price' => 42.50, 'status' => 'new',
    ]);

    $rows = ordersCsvRows($this->actingAs($user)->get(route('orders.export')));

    expect($rows[1][5])->toBe('Rojstnodnevna torta'); // Produkt column
    expect($rows[1][11])->toBe('42.50'); // Cena column — the order's own price, not the product's current price
});

test('workspace isolation: a user only ever exports their own workspace orders', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    $customerA = Customer::create(['workspace_id' => $workspaceA->id, 'full_name' => 'Stranka A']);
    Order::create(['workspace_id' => $workspaceA->id, 'customer_id' => $customerA->id, 'title' => 'Naročilo A', 'price' => 10, 'status' => 'new']);

    [$workspaceB, $userB] = createWorkspaceWithUser();

    $rows = ordersCsvRows($this->actingAs($userB)->get(route('orders.export')));

    expect($rows)->toHaveCount(1); // header only — no rows leak from workspace A
});

test('the CSV is UTF-8 with a BOM and uses a semicolon delimiter', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana Novak']);
    Order::create(['workspace_id' => $workspace->id, 'customer_id' => $customer->id, 'title' => 'Torta', 'price' => 10, 'status' => 'new']);

    $response = $this->actingAs($user)->get(route('orders.export'));
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $raw = $response->streamedContent();
    expect(substr($raw, 0, 3))->toBe("\xEF\xBB\xBF");
    expect(explode("\n", $raw)[0])->toContain(';');
    expect($response->headers->get('content-disposition'))->toContain('narocila-'.now()->format('Y-m-d').'.csv');
});

test('cell values are escaped and spreadsheet formula injection is neutralized', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => '=cmd|"/c calc"!A1']);
    Order::create([
        'workspace_id' => $workspace->id, 'customer_id' => $customer->id,
        'title' => "Naročilo; z \"narekovaji\" in podpičjem", 'price' => 10, 'status' => 'new',
        'delivery_method' => '+1234', 'tracking_number' => '-danger',
    ]);

    $rows = ordersCsvRows($this->actingAs($user)->get(route('orders.export')));
    $row = $rows[1];

    expect($row[2])->toBe("'=cmd|\"/c calc\"!A1"); // Stranka — formula-trigger char defused
    expect($row[6])->toBe('Naročilo; z "narekovaji" in podpičjem'); // Naslov naročila — quotes/semicolon survive correctly
    expect($row[15])->toBe("'+1234"); // Način dostave
    expect($row[16])->toBe("'-danger"); // Tracking številka
});

test('internal notes and customer notes are never present in the export', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana Novak']);
    Order::create([
        'workspace_id' => $workspace->id, 'customer_id' => $customer->id, 'title' => 'Torta', 'price' => 10, 'status' => 'new',
        'internal_notes' => 'SUPER-SKRIVNO-INTERNO-BESEDILO', 'customer_notes' => 'ZAUPNA-OPOMBA-STRANKE',
    ]);

    $raw = $this->actingAs($user)->get(route('orders.export'))->streamedContent();

    expect($raw)->not->toContain('SUPER-SKRIVNO-INTERNO-BESEDILO');
    expect($raw)->not->toContain('ZAUPNA-OPOMBA-STRANKE');
});
