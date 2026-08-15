<?php

use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Workspace;
use Illuminate\Support\Facades\Storage;

function createLocalAttachmentMessage(Workspace $workspace, Channel $channel): Message
{
    Storage::fake('local');
    Storage::disk('local')->put('inbox-attachments/test-photo.jpg', 'fake-bytes');

    $conversation = Conversation::withoutGlobalScopes()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_attach',
        'status' => 'new_enquiry',
    ]);

    return $conversation->messages()->create([
        'sender_type' => 'business',
        'body' => null,
        'message_type' => 'image',
        'status' => 'sent',
        'metadata' => ['attachments' => [['type' => 'image', 'source' => 'local', 'path' => 'inbox-attachments/test-photo.jpg']]],
        'sent_at' => now(),
    ]);
}

test('a user from the correct workspace can access an attachment', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace);
    $message = createLocalAttachmentMessage($workspace, $channel);

    $this->actingAs($user)
        ->get(route('inbox.attachments.show', [$message->id, 0]))
        ->assertOk();
});

test('a user from a different workspace cannot access the attachment', function () {
    [$workspaceA, $userA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();
    $channelB = createMetaChannel($workspaceB);
    $message = createLocalAttachmentMessage($workspaceB, $channelB);

    $this->actingAs($userA)
        ->get(route('inbox.attachments.show', [$message->id, 0]))
        ->assertStatus(404);
});

test('an anonymous user cannot access a private attachment', function () {
    [$workspace] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace);
    $message = createLocalAttachmentMessage($workspace, $channel);

    $this->get(route('inbox.attachments.show', [$message->id, 0]))
        ->assertRedirect(route('login'));
});

test('a platform admin without a valid support session cannot access the attachment via the admin route', function () {
    $admin = createPlatformAdmin();
    [$workspace] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace);
    $message = createLocalAttachmentMessage($workspace, $channel);

    $this->actingAs($admin)
        ->get(route('admin.workspaces.support.attachment', [$workspace, $message->id, 0]))
        ->assertForbidden();
});

test('a platform admin with an active workspace_content support session can access the attachment', function () {
    $admin = createPlatformAdmin();
    [$workspace, $owner] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace);
    $message = createLocalAttachmentMessage($workspace, $channel);

    createSupportGrant($workspace, $owner, 'workspace_content');
    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.workspaces.support.start', $workspace));

    $this->get(route('admin.workspaces.support.attachment', [$workspace, $message->id, 0]))
        ->assertOk();
});

test('revoked support access immediately stops attachment access', function () {
    $admin = createPlatformAdmin();
    [$workspace, $owner] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace);
    $message = createLocalAttachmentMessage($workspace, $channel);

    $grant = createSupportGrant($workspace, $owner, 'workspace_content');
    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.workspaces.support.start', $workspace));

    $this->get(route('admin.workspaces.support.attachment', [$workspace, $message->id, 0]))->assertOk();

    $grant->update(['revoked_at' => now()]);

    $this->get(route('admin.workspaces.support.attachment', [$workspace, $message->id, 0]))
        ->assertForbidden();
});
