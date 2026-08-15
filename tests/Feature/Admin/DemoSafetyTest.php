<?php

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Storage;

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

test('admin manual demo deletion removes the workspace, its demo user, and its private attachments', function () {
    Storage::fake('local');
    Storage::disk('local')->put('inbox-attachments/manual-demo-delete.jpg', 'fake-bytes');

    $admin = createPlatformAdmin();

    $demoUser = User::factory()->create(['is_demo' => true]);
    $workspace = Workspace::create([
        'name' => 'Demo Manual Delete',
        'slug' => 'demo-manual-delete',
        'timezone' => 'Europe/Ljubljana',
        'currency' => 'EUR',
        'is_demo' => true,
        'demo_expires_at' => now()->addHours(4),
    ]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $demoUser->id, 'role' => 'owner']);
    $demoUser->update(['current_workspace_id' => $workspace->id]);

    $channel = Channel::create(['workspace_id' => $workspace->id, 'type' => 'instagram', 'display_name' => 'x', 'status' => 'not_connected']);
    $conversation = Conversation::withoutGlobalScopes()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'demo_manual_sender',
        'status' => 'new_enquiry',
    ]);
    $conversation->messages()->create([
        'sender_type' => 'business',
        'message_type' => 'image',
        'status' => 'sent',
        'metadata' => ['attachments' => [['type' => 'image', 'source' => 'local', 'path' => 'inbox-attachments/manual-demo-delete.jpg']]],
        'sent_at' => now(),
    ]);

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('admin.workspaces.destroy-demo', $workspace))
        ->assertRedirect();

    expect(Workspace::find($workspace->id))->toBeNull();
    expect(User::find($demoUser->id))->toBeNull();
    Storage::disk('local')->assertMissing('inbox-attachments/manual-demo-delete.jpg');
});

test('admin manual demo deletion never deletes a real user, even one linked to the demo workspace', function () {
    $admin = createPlatformAdmin();
    $realUser = User::factory()->create(['is_demo' => false]);

    $workspace = Workspace::create([
        'name' => 'Demo With Real User',
        'slug' => 'demo-with-real-user',
        'timezone' => 'Europe/Ljubljana',
        'currency' => 'EUR',
        'is_demo' => true,
        'demo_expires_at' => now()->addHours(4),
    ]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $realUser->id, 'role' => 'owner']);

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->delete(route('admin.workspaces.destroy-demo', $workspace))
        ->assertRedirect();

    expect(Workspace::find($workspace->id))->toBeNull();
    expect(User::find($realUser->id))->not->toBeNull();
});
