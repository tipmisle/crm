<?php

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

function makeDemoWorkspace(string $expiresRelative): array
{
    $workspace = Workspace::create([
        'name' => 'Cleanup Test Biz',
        'slug' => 'cleanup-test-'.uniqid(),
        'is_demo' => true,
        'demo_variant' => 'services',
        'demo_expires_at' => now()->{$expiresRelative === 'past' ? 'subHour' : 'addHours'}($expiresRelative === 'past' ? 1 : 4),
    ]);

    $user = User::factory()->create(['current_workspace_id' => $workspace->id, 'is_demo' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'owner']);

    return [$workspace, $user];
}

test('demos:cleanup deletes only expired demo workspaces and their demo users', function () {
    [$expiredWorkspace, $expiredUser] = makeDemoWorkspace('past');
    [$activeWorkspace, $activeUser] = makeDemoWorkspace('future');
    [$realWorkspace, $realUser] = createWorkspaceWithUser();

    Artisan::call('demos:cleanup');

    expect(Workspace::find($expiredWorkspace->id))->toBeNull();
    expect(User::find($expiredUser->id))->toBeNull();

    expect(Workspace::find($activeWorkspace->id))->not->toBeNull();
    expect(User::find($activeUser->id))->not->toBeNull();

    expect(Workspace::find($realWorkspace->id))->not->toBeNull();
    expect(User::find($realUser->id))->not->toBeNull();
});

test('demos:cleanup cascades away the expired workspace data', function () {
    [$workspace, $user] = makeDemoWorkspace('past');

    $channel = Channel::create([
        'workspace_id' => $workspace->id,
        'type' => 'instagram',
        'display_name' => 'x',
        'status' => 'not_connected',
    ]);

    $customer = Customer::create([
        'workspace_id' => $workspace->id,
        'full_name' => 'Test Customer',
    ]);

    Artisan::call('demos:cleanup');

    expect(Channel::withoutGlobalScopes()->find($channel->id))->toBeNull();
    expect(Customer::withoutGlobalScopes()->find($customer->id))->toBeNull();
});

test('demos:cleanup deletes demo-owned local attachment files', function () {
    Storage::fake('local');
    Storage::disk('local')->put('inbox-attachments/demo-file.jpg', 'fake-bytes');

    [$workspace, $user] = makeDemoWorkspace('past');

    $channel = Channel::create([
        'workspace_id' => $workspace->id,
        'type' => 'instagram',
        'display_name' => 'x',
        'status' => 'not_connected',
    ]);

    $conversation = Conversation::withoutGlobalScopes()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'demo_sender',
        'status' => 'new_enquiry',
    ]);

    $conversation->messages()->create([
        'sender_type' => 'business',
        'message_type' => 'image',
        'status' => 'sent',
        'metadata' => ['attachments' => [['type' => 'image', 'source' => 'local', 'path' => 'inbox-attachments/demo-file.jpg']]],
        'sent_at' => now(),
    ]);

    Storage::disk('local')->assertExists('inbox-attachments/demo-file.jpg');

    Artisan::call('demos:cleanup');

    Storage::disk('local')->assertMissing('inbox-attachments/demo-file.jpg');
});
