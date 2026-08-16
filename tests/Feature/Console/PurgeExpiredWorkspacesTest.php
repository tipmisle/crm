<?php

use App\Models\AuditLog;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

test('purges a real workspace past its scheduled deletion date', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $workspace->update(['deletion_requested_at' => now()->subDays(31), 'scheduled_deletion_at' => now()->subDay()]);

    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Test Customer']);

    Artisan::call('workspaces:purge-expired');

    expect(Workspace::find($workspace->id))->toBeNull();
    expect(Customer::withoutGlobalScopes()->find($customer->id))->toBeNull();
    expect(AuditLog::where('event', 'privacy.workspace.purge_attempted')->exists())->toBeTrue();
    expect(AuditLog::where('event', 'privacy.workspace.purged')->exists())->toBeTrue();
});

test('never purges a demo workspace even with a scheduled_deletion_at set', function () {
    $demo = Workspace::create([
        'name' => 'Demo Biz',
        'slug' => 'demo-'.uniqid(),
        'is_demo' => true,
        'demo_variant' => 'services',
        'demo_expires_at' => now()->addHours(4),
        'scheduled_deletion_at' => now()->subDay(),
    ]);

    Artisan::call('workspaces:purge-expired');

    expect(Workspace::find($demo->id))->not->toBeNull();
});

test('does not purge a workspace whose scheduled deletion is in the future', function () {
    [$workspace] = createWorkspaceWithUser();
    $workspace->update(['deletion_requested_at' => now(), 'scheduled_deletion_at' => now()->addDays(29)]);

    Artisan::call('workspaces:purge-expired');

    expect(Workspace::find($workspace->id))->not->toBeNull();
});

test('purging one workspace does not affect an unrelated workspace', function () {
    [$due] = createWorkspaceWithUser();
    $due->update(['deletion_requested_at' => now()->subDays(31), 'scheduled_deletion_at' => now()->subDay()]);

    [$other] = createWorkspaceWithUser();

    Artisan::call('workspaces:purge-expired');

    expect(Workspace::find($due->id))->toBeNull();
    expect(Workspace::find($other->id))->not->toBeNull();
});

test('deletes local attachment files on purge', function () {
    Storage::fake('local');
    Storage::disk('local')->put('inbox-attachments/real-file.jpg', 'fake-bytes');

    [$workspace] = createWorkspaceWithUser();
    $workspace->update(['deletion_requested_at' => now()->subDays(31), 'scheduled_deletion_at' => now()->subDay()]);

    $channel = Channel::create([
        'workspace_id' => $workspace->id,
        'type' => 'instagram',
        'display_name' => 'x',
        'status' => 'not_connected',
    ]);

    $conversation = Conversation::withoutGlobalScopes()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_1',
        'status' => 'new_enquiry',
    ]);

    Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'customer',
        'body' => 'hi',
        'message_type' => 'text',
        'status' => 'sent',
        'metadata' => ['attachments' => [['type' => 'image', 'source' => 'local', 'path' => 'inbox-attachments/real-file.jpg']]],
        'sent_at' => now(),
    ]);

    Artisan::call('workspaces:purge-expired');

    Storage::disk('local')->assertMissing('inbox-attachments/real-file.jpg');
});
