<?php

use App\Models\WorkspaceExport;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

test('expired export file and row are removed', function () {
    Storage::fake('local');
    Storage::disk('local')->put('exports/expired.zip', 'zip-bytes');

    [$workspace] = createWorkspaceWithUser();
    $export = WorkspaceExport::create([
        'workspace_id' => $workspace->id,
        'disk_path' => 'exports/expired.zip',
        'status' => 'ready',
        'expires_at' => now()->subHour(),
    ]);

    Artisan::call('exports:purge-expired');

    expect(WorkspaceExport::find($export->id))->toBeNull();
    Storage::disk('local')->assertMissing('exports/expired.zip');
});

test('non-expired export is untouched', function () {
    Storage::fake('local');
    Storage::disk('local')->put('exports/fresh.zip', 'zip-bytes');

    [$workspace] = createWorkspaceWithUser();
    $export = WorkspaceExport::create([
        'workspace_id' => $workspace->id,
        'disk_path' => 'exports/fresh.zip',
        'status' => 'ready',
        'expires_at' => now()->addHour(),
    ]);

    Artisan::call('exports:purge-expired');

    expect(WorkspaceExport::find($export->id))->not->toBeNull();
    Storage::disk('local')->assertExists('exports/fresh.zip');
});
