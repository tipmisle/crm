<?php

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Message;

test('viewing a workspace as admin generates an audit row', function () {
    $admin = createPlatformAdmin();
    [$workspace] = createWorkspaceWithUser();

    $this->actingAs($admin)->get(route('admin.workspaces.show', $workspace));

    expect(AuditLog::where('event', 'admin.workspace.view')->where('workspace_id', $workspace->id)->exists())->toBeTrue();
});

test('audit metadata never contains a message body', function () {
    $admin = createPlatformAdmin();
    [$workspace, $owner] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace);

    createSupportGrant($workspace, $owner, 'workspace_content');

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.workspaces.support.start', $workspace));

    $conversation = Conversation::withoutGlobalScopes()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_audit',
        'status' => 'new_enquiry',
    ]);

    $secret = 'Živjo, noseča sem in potrebujem pomoč z naročilom';
    Message::create([
        'conversation_id' => $conversation->id,
        'sender_type' => 'customer',
        'body' => $secret,
        'message_type' => 'text',
        'status' => 'delivered',
        'sent_at' => now(),
    ]);

    $this->get(route('admin.workspaces.support.conversation', [$workspace, $conversation]));

    $allMetadata = AuditLog::pluck('metadata')->map(fn ($m) => json_encode($m))->implode(' ');

    expect($allMetadata)->not->toContain($secret);
});
