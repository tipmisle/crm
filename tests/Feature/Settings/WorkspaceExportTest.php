<?php

use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\User;
use App\Models\WorkspaceExport;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Storage;

function actingAsConfirmedOwner($test, User $owner)
{
    return $test->actingAs($owner)->withSession(['auth.password_confirmed_at' => time()]);
}

test('owner can request an export and it excludes secrets while decrypting content', function () {
    Storage::fake('local');

    [$workspace, $owner] = createWorkspaceWithUser();
    Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Jane Doe',
        'notes' => 'a very secret note',
    ]);
    createMetaChannel($workspace, accessToken: 'super-secret-page-token');

    actingAsConfirmedOwner($this, $owner)
        ->post(route('settings.privacy.export.store'))
        ->assertRedirect();

    $export = WorkspaceExport::first();
    expect($export)->not->toBeNull();

    $zipPath = Storage::disk('local')->path($export->disk_path);
    $zip = new ZipArchive;
    $zip->open($zipPath);
    $customersCsv = $zip->getFromName('customers.csv');
    $allContents = '';
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $allContents .= $zip->getFromIndex($i);
    }
    $zip->close();

    expect($customersCsv)->toContain('a very secret note');
    expect($allContents)->not->toContain('super-secret-page-token');
    expect($allContents)->not->toContain('test-user-token');

    expect(AuditLog::where('event', 'privacy.workspace.export_requested')->exists())->toBeTrue();
});

test('an export with orders and appointments does not fatal on the plain-string Order status and includes billing/payment fields', function () {
    Storage::fake('local');

    [$workspace, $owner] = createWorkspaceWithUser(['current_workspace_id' => null]);
    $workspace->update(['appointments_enabled' => true]);
    $owner->update(['current_workspace_id' => $workspace->id]);

    Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Jane Doe',
        'address_line' => 'Testna cesta 1',
        'postal_code' => '1000',
        'city' => 'Ljubljana',
        'country' => 'Slovenija',
        'tax_number' => 'SI12345678',
    ]);

    [$order] = createOrderWithConversation($workspace);

    actingAsConfirmedOwner($this, $owner)
        ->post(route('settings.privacy.export.store'))
        ->assertRedirect();

    $export = WorkspaceExport::first();
    expect($export)->not->toBeNull();

    $zipPath = Storage::disk('local')->path($export->disk_path);
    $zip = new ZipArchive;
    $zip->open($zipPath);
    $customersCsv = $zip->getFromName('customers.csv');
    $ordersCsv = $zip->getFromName('orders.csv');
    $zip->close();

    expect($customersCsv)->toContain('Testna cesta 1', '1000', 'Ljubljana', 'Slovenija', 'SI12345678');
    expect($ordersCsv)->toContain($order->status);
    expect($ordersCsv)->toContain('payment_status');
});

test('non-owner member cannot request an export', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'member']);

    actingAsConfirmedOwner($this, $member)
        ->post(route('settings.privacy.export.store'))
        ->assertForbidden();
});

test('download is owner-only and workspace-scoped', function () {
    Storage::fake('local');
    Storage::disk('local')->put('exports/f.zip', 'zip-bytes');

    [$workspace, $owner] = createWorkspaceWithUser();
    $export = WorkspaceExport::create([
        'workspace_id' => $workspace->id,
        'requested_by_user_id' => $owner->id,
        'disk_path' => 'exports/f.zip',
        'status' => 'ready',
        'expires_at' => now()->addHours(24),
    ]);

    [, $otherOwner] = createWorkspaceWithUser();

    $this->actingAs($otherOwner)
        ->get(route('settings.privacy.export.download', $export))
        ->assertForbidden();

    $this->actingAs($owner)
        ->get(route('settings.privacy.export.download', $export))
        ->assertOk();
});

test('expired export cannot be downloaded', function () {
    Storage::fake('local');
    Storage::disk('local')->put('exports/f.zip', 'zip-bytes');

    [$workspace, $owner] = createWorkspaceWithUser();
    $export = WorkspaceExport::create([
        'workspace_id' => $workspace->id,
        'requested_by_user_id' => $owner->id,
        'disk_path' => 'exports/f.zip',
        'status' => 'ready',
        'expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($owner)
        ->get(route('settings.privacy.export.download', $export))
        ->assertStatus(410);
});

test('an already-downloaded export cannot be downloaded again', function () {
    Storage::fake('local');
    Storage::disk('local')->put('exports/f.zip', 'zip-bytes');

    [$workspace, $owner] = createWorkspaceWithUser();
    $export = WorkspaceExport::create([
        'workspace_id' => $workspace->id,
        'requested_by_user_id' => $owner->id,
        'disk_path' => 'exports/f.zip',
        'status' => 'ready',
        'expires_at' => now()->addHours(24),
    ]);

    $this->actingAs($owner)->get(route('settings.privacy.export.download', $export))->assertOk();
    $this->actingAs($owner)->get(route('settings.privacy.export.download', $export))->assertStatus(410);
});
