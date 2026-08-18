<?php

use App\Models\Appointment;
use App\Models\Customer;
use App\Models\PaymentStatus;
use App\Models\Service;
use Illuminate\Testing\TestResponse;

function appointmentsCsvRows(TestResponse $response): array
{
    $content = $response->streamedContent();
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
    $lines = array_filter(explode("\n", trim($content)), fn ($l) => $l !== '');

    return array_map(fn ($line) => str_getcsv($line, ';'), $lines);
}

function baseAppointment(int $workspaceId, int $customerId, array $overrides = []): Appointment
{
    $catalogItemId = $overrides['service_id'] ?? null;
    unset($overrides['service_id']);

    $data = array_merge([
        'workspace_id' => $workspaceId,
        'customer_id' => $customerId,
        'service_name' => 'Manikura',
        'appointment_date' => now()->addDay(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'price' => 35,
        'status' => 'requested',
    ], $overrides);

    $appointment = Appointment::create($data);
    // The CSV "Storitev" column is derived from items, not service_name —
    // keep a matching item so fixtures built with the old single-service_id
    // shape still exercise the current multi-item export path.
    $appointment->items()->create([
        'catalog_item_id' => $catalogItemId,
        'title' => $data['service_name'],
        'quantity' => 1,
        'unit_price' => $data['price'],
    ]);

    return $appointment;
}

test('the appointments CSV export uses the same filters as the appointments index', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana Novak']);

    baseAppointment($workspace->id, $customer->id, ['service_name' => 'Manikura', 'status' => 'requested']);
    baseAppointment($workspace->id, $customer->id, ['service_name' => 'Gel nohti', 'status' => 'confirmed']);

    $indexResponse = $this->actingAs($user)->get(route('appointments.index', ['status' => 'confirmed']));
    $indexIds = collect($indexResponse->viewData('page')['props']['appointments']['data'])->pluck('id')->all();

    $rows = appointmentsCsvRows($this->actingAs($user)->get(route('appointments.export', ['status' => 'confirmed'])));
    $dataRows = array_slice($rows, 1);

    expect($indexIds)->toHaveCount(1);
    expect($dataRows)->toHaveCount(1);
    expect($dataRows[0][7])->toBe('Gel nohti'); // Storitev column
});

test('the appointments export includes every matching row, not only the first pagination page', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana Novak']);

    for ($i = 0; $i < 25; $i++) {
        baseAppointment($workspace->id, $customer->id, ['service_name' => "Storitev {$i}"]);
    }

    $indexResponse = $this->actingAs($user)->get(route('appointments.index'));
    expect($indexResponse->viewData('page')['props']['appointments']['data'])->toHaveCount(20);

    $rows = appointmentsCsvRows($this->actingAs($user)->get(route('appointments.export')));
    expect($rows)->toHaveCount(26);
});

test('the appointments export uses current workspace-editable payment status labels and fixed status labels', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    PaymentStatus::where('workspace_id', $workspace->id)->where('key', 'paid')->update(['label' => 'Vse plačano, hvala']);
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana Novak']);
    baseAppointment($workspace->id, $customer->id, ['status' => 'confirmed', 'payment_status' => 'paid']);

    $rows = appointmentsCsvRows($this->actingAs($user)->get(route('appointments.export')));

    expect($rows[1][8])->toBe('Potrjeno'); // Status column — fixed enum label
    expect($rows[1][9])->toBe('Vse plačano, hvala'); // Status plačila — workspace-editable label
});

test('a linked service name is exported, but historical appointment price is not recalculated from it', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana Novak']);
    $service = Service::create(['workspace_id' => $workspace->id, 'name' => 'Gel nohti', 'default_price' => 999]);
    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Manikura',
        'appointment_date' => now()->addDay(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'price' => 33.50,
        'status' => 'requested',
    ]);
    $appointment->items()->create(['catalog_item_id' => $service->id, 'title' => $service->name, 'quantity' => 1, 'unit_price' => 33.50]);

    $rows = appointmentsCsvRows($this->actingAs($user)->get(route('appointments.export')));

    expect($rows[1][7])->toBe('Gel nohti'); // Storitev column
    expect($rows[1][10])->toBe('33.50'); // Cena column — the appointment's own price
});

test('workspace isolation: a user only ever exports their own workspace appointments', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    $workspaceA->update(['appointments_enabled' => true]);
    $customerA = Customer::create(['workspace_id' => $workspaceA->id, 'full_name' => 'Stranka A']);
    baseAppointment($workspaceA->id, $customerA->id);

    [$workspaceB, $userB] = createWorkspaceWithUser();
    $workspaceB->update(['appointments_enabled' => true]);

    $rows = appointmentsCsvRows($this->actingAs($userB)->get(route('appointments.export')));

    expect($rows)->toHaveCount(1);
});

test('the appointments CSV is UTF-8 with a BOM, uses a semicolon delimiter, and defuses formula injection', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => '=SUM(1+1)']);
    baseAppointment($workspace->id, $customer->id);

    $response = $this->actingAs($user)->get(route('appointments.export'));
    $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    expect($response->headers->get('content-disposition'))->toContain('termini-'.now()->format('Y-m-d').'.csv');

    $rows = appointmentsCsvRows($response);
    $raw = $response->streamedContent();

    expect(substr($raw, 0, 3))->toBe("\xEF\xBB\xBF");
    expect($rows[1][4])->toBe("'=SUM(1+1)"); // Stranka column
});

test('internal notes and customer notes are never present in the appointments export', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Ana Novak']);
    baseAppointment($workspace->id, $customer->id, [
        'internal_notes' => 'TAJNO-INTERNO-BESEDILO',
        'customer_notes' => 'ZAUPNA-OPOMBA',
    ]);

    $raw = $this->actingAs($user)->get(route('appointments.export'))->streamedContent();

    expect($raw)->not->toContain('TAJNO-INTERNO-BESEDILO');
    expect($raw)->not->toContain('ZAUPNA-OPOMBA');
});
