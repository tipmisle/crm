<?php

use App\Models\Workspace;

test('deleting a demo workspace works', function () {
    $admin = createPlatformAdmin();
    $workspace = Workspace::create([
        'name' => 'Demo Co',
        'slug' => 'demo-co',
        'timezone' => 'Europe/Ljubljana',
        'currency' => 'EUR',
        'is_demo' => true,
        'demo_expires_at' => now()->addHours(4),
    ]);

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('admin.workspaces.destroy-demo', $workspace))
        ->assertRedirect();

    expect(Workspace::find($workspace->id))->toBeNull();
});

test('the demo deletion action rejects a real workspace', function () {
    $admin = createPlatformAdmin();
    [$workspace] = createWorkspaceWithUser();

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('admin.workspaces.destroy-demo', $workspace))
        ->assertStatus(422);

    expect(Workspace::find($workspace->id))->not->toBeNull();
});
