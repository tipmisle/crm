<?php

use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\BugReport;
use App\Models\Customer;
use App\Models\FeatureRequest;
use App\Models\Order;
use App\Models\User;
use App\Services\CustomerExportService;
use App\Services\ExportWorkspaceDataService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

function readZipEntry(string $zipBytes, string $entry): string
{
    $tmp = tempnam(sys_get_temp_dir(), 'test-export-').'.zip';
    file_put_contents($tmp, $zipBytes);

    $zip = new ZipArchive;
    $zip->open($tmp);
    $content = $zip->getFromName($entry);
    $zip->close();
    unlink($tmp);

    return $content;
}

function captureStream(StreamedResponse $response): string
{
    ob_start();
    $response->sendContent();

    return ob_get_clean();
}

test('customer export includes B2B fields and current multi-item order/appointment data without stale enum access', function () {
    [$workspace] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'is_business' => true,
        'company_name' => 'Ana d.o.o.',
        'vat_registered' => true,
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Naročilo',
        'price' => 90,
        'status' => 'confirmed',
    ]);
    $order->items()->createMany([
        ['title' => 'Izdelek A', 'quantity' => 2, 'unit_price' => 30],
        ['title' => 'Izdelek B', 'quantity' => 1, 'unit_price' => 30],
    ]);

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Termin',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'price' => 40,
        'status' => AppointmentStatus::defaultKey(),
    ]);
    $appointment->items()->createMany([
        ['title' => 'Storitev A', 'quantity' => 1, 'unit_price' => 40],
    ]);

    // Appointment.status is a plain string key, not an enum — this must not
    // throw when the export service reads $a->status?->value.
    $zipBytes = captureStream(app(CustomerExportService::class)->build($customer));
    $data = json_decode(readZipEntry($zipBytes, 'customer.json'), true);

    expect($data['customer']['is_business'])->toBeTrue();
    expect($data['customer']['company_name'])->toBe('Ana d.o.o.');
    expect($data['customer']['vat_registered'])->toBeTrue();

    expect($data['orders'][0]['status'])->toBe('confirmed');
    expect($data['orders'][0]['items'])->toHaveCount(2);
    expect($data['orders'][0]['items'][0]['title'])->toBe('Izdelek A');

    expect($data['appointments'][0]['status'])->toBe($appointment->status);
    expect($data['appointments'][0]['items'])->toHaveCount(1);
    expect($data['appointments'][0]['items'][0]['title'])->toBe('Storitev A');
});

test('workspace data export includes B2B customer fields, item breakdowns, and current appointment status keys', function () {
    Storage::fake('local');

    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'is_business' => true,
        'company_name' => 'Ana d.o.o.',
        'vat_registered' => true,
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Naročilo',
        'price' => 30,
        'status' => 'confirmed',
    ]);
    $order->items()->create(['title' => 'Izdelek A', 'quantity' => 1, 'unit_price' => 30]);

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Termin',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 60,
        'price' => 40,
        'status' => AppointmentStatus::defaultKey(),
    ]);
    $appointment->items()->create(['title' => 'Storitev A', 'quantity' => 1, 'unit_price' => 40]);

    $export = app(ExportWorkspaceDataService::class)->build($workspace, User::find($user->id));

    $zipBytes = Storage::disk('local')->get($export->disk_path);

    $customersCsv = readZipEntry($zipBytes, 'customers.csv');
    expect($customersCsv)->toContain('is_business');
    expect($customersCsv)->toContain('company_name');
    expect($customersCsv)->toContain('Ana d.o.o.');

    $appointmentsCsv = readZipEntry($zipBytes, 'appointments.csv');
    // Must be the plain status key, never a stale "?->value" artifact
    // (e.g. an empty column or a fatal error building the export).
    expect($appointmentsCsv)->toContain($appointment->status);

    $orderItemsCsv = readZipEntry($zipBytes, 'order-items.csv');
    expect($orderItemsCsv)->toContain('Izdelek A');

    $appointmentItemsCsv = readZipEntry($zipBytes, 'appointment-items.csv');
    expect($appointmentItemsCsv)->toContain('Storitev A');
});

test('customer export includes distinctive values from every sensitive customer-linked free-text field', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $workspace->update(['appointments_enabled' => true]);
    $this->actingAs($user);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Ana Novak',
        'notes' => 'MARKER-CUSTOMER-NOTES',
        'first_contacted_at' => now(),
        'last_interaction_at' => now(),
    ]);

    $order = Order::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'title' => 'Naročilo',
        'description' => 'MARKER-ORDER-DESCRIPTION',
        'internal_notes' => 'MARKER-ORDER-INTERNAL',
        'customer_notes' => 'MARKER-ORDER-CUSTOMER-NOTES',
        'tracking_number' => 'MARKER-TRACKING-123',
        'price' => 50,
        'status' => 'new',
    ]);
    $order->notes()->create(['user_id' => $user->id, 'body' => 'MARKER-ORDER-NOTE-BODY']);

    $appointment = Appointment::create([
        'workspace_id' => $workspace->id,
        'customer_id' => $customer->id,
        'service_name' => 'Termin',
        'description' => 'MARKER-APPOINTMENT-DESCRIPTION',
        'internal_notes' => 'MARKER-APPOINTMENT-INTERNAL',
        'customer_notes' => 'MARKER-APPOINTMENT-CUSTOMER-NOTES',
        'appointment_date' => now()->addDay()->toDateString(),
        'start_time' => '10:00',
        'duration_minutes' => 45,
        'price' => 40,
        'status' => 'requested',
    ]);

    $customer->followUps()->create(['note' => 'MARKER-FOLLOWUP-NOTE', 'due_at' => now()->addDay()]);

    $zipBytes = captureStream(app(CustomerExportService::class)->build($customer));
    $json = readZipEntry($zipBytes, 'customer.json');

    foreach ([
        'MARKER-CUSTOMER-NOTES',
        'MARKER-ORDER-DESCRIPTION',
        'MARKER-ORDER-INTERNAL',
        'MARKER-ORDER-CUSTOMER-NOTES',
        'MARKER-TRACKING-123',
        'MARKER-ORDER-NOTE-BODY',
        'MARKER-APPOINTMENT-DESCRIPTION',
        'MARKER-APPOINTMENT-INTERNAL',
        'MARKER-APPOINTMENT-CUSTOMER-NOTES',
        'MARKER-FOLLOWUP-NOTE',
    ] as $marker) {
        expect($json)->toContain($marker);
    }

    $data = json_decode($json, true);
    expect($data['appointments'][0]['start_time'])->toBe('10:00');
    expect($data['appointments'][0]['duration_minutes'])->toBe(45);
});

test('workspace data export includes decrypted bug reports and feature requests, but no secrets', function () {
    Storage::fake('local');

    [$workspace, $user] = createWorkspaceWithUser();

    BugReport::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'subject' => 'MARKER-BUG-SUBJECT',
        'message' => 'MARKER-BUG-MESSAGE',
        'page_url' => '/settings/support',
    ]);

    FeatureRequest::create([
        'workspace_id' => $workspace->id,
        'user_id' => $user->id,
        'subject' => 'MARKER-FEATURE-SUBJECT',
        'message' => 'MARKER-FEATURE-MESSAGE',
    ]);

    $export = app(ExportWorkspaceDataService::class)->build($workspace, User::find($user->id));
    $zipBytes = Storage::disk('local')->get($export->disk_path);

    $bugReportsCsv = readZipEntry($zipBytes, 'bug-reports.csv');
    expect($bugReportsCsv)->toContain('MARKER-BUG-SUBJECT');
    expect($bugReportsCsv)->toContain('MARKER-BUG-MESSAGE');

    $featureRequestsCsv = readZipEntry($zipBytes, 'feature-requests.csv');
    expect($featureRequestsCsv)->toContain('MARKER-FEATURE-SUBJECT');
    expect($featureRequestsCsv)->toContain('MARKER-FEATURE-MESSAGE');
});
