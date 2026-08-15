<?php

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\SupportAccessGrant;
use App\Models\SupportSession;
use App\Models\User;
use App\Models\WorkspaceMember;

function confirmedAdmin(): User
{
    return createPlatformAdmin();
}

function actingAsConfirmedAdmin($test, User $admin)
{
    return $test->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()]);
}

test('the workspace owner can grant support access', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    $this->actingAs($owner)->post(route('settings.support.store'), [
        'duration_minutes' => 60,
    ])->assertRedirect();

    expect(SupportAccessGrant::where('workspace_id', $workspace->id)->active()->exists())->toBeTrue();
});

test('a non-owner workspace member cannot grant support access', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'member']);

    $this->actingAs($member)->post(route('settings.support.store'), [
        'duration_minutes' => 60,
    ])->assertForbidden();
});

test('an expired grant does not allow a support session to start', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $admin = confirmedAdmin();

    $grant = createSupportGrant($workspace, $owner, 'workspace_content', 60);
    $grant->update(['expires_at' => now()->subMinute()]);

    actingAsConfirmedAdmin($this, $admin)
        ->post(route('admin.workspaces.support.start', $workspace))
        ->assertForbidden();
});

test('a revoked grant stops working immediately, even mid-session', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $admin = confirmedAdmin();
    $channel = createMetaChannel($workspace);

    $grant = createSupportGrant($workspace, $owner, 'workspace_content');

    actingAsConfirmedAdmin($this, $admin)->post(route('admin.workspaces.support.start', $workspace))->assertRedirect();

    $conversation = Conversation::withoutGlobalScopes()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_y',
        'status' => 'new_enquiry',
    ]);

    $this->get(route('admin.workspaces.support.conversation', [$workspace, $conversation]))->assertOk();

    $grant->update(['revoked_at' => now()]);

    $this->get(route('admin.workspaces.support.conversation', [$workspace, $conversation]))->assertForbidden();
});

test('workspace_content scope can access permitted resources', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $admin = confirmedAdmin();
    $channel = createMetaChannel($workspace);

    createSupportGrant($workspace, $owner, 'workspace_content');
    actingAsConfirmedAdmin($this, $admin)->post(route('admin.workspaces.support.start', $workspace))->assertRedirect();

    $conversation = Conversation::withoutGlobalScopes()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_w',
        'status' => 'new_enquiry',
    ]);

    $this->get(route('admin.workspaces.support.conversation', [$workspace, $conversation]))->assertOk();
});

test('a support session cannot be used against a different workspace', function () {
    [$workspaceA, $ownerA] = createWorkspaceWithUser();
    [$workspaceB] = createWorkspaceWithUser();
    $admin = confirmedAdmin();
    $channelB = createMetaChannel($workspaceB);

    createSupportGrant($workspaceA, $ownerA, 'workspace_content');
    actingAsConfirmedAdmin($this, $admin)->post(route('admin.workspaces.support.start', $workspaceA))->assertRedirect();

    $conversationB = Conversation::withoutGlobalScopes()->create([
        'workspace_id' => $workspaceB->id,
        'channel_id' => $channelB->id,
        'external_conversation_id' => 'sender_b',
        'status' => 'new_enquiry',
    ]);

    $this->get(route('admin.workspaces.support.conversation', [$workspaceB, $conversationB]))->assertForbidden();
});

test('an expired session cannot continue accessing content', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $admin = confirmedAdmin();
    $channel = createMetaChannel($workspace);

    createSupportGrant($workspace, $owner, 'workspace_content');
    actingAsConfirmedAdmin($this, $admin)->post(route('admin.workspaces.support.start', $workspace))->assertRedirect();

    $conversation = Conversation::withoutGlobalScopes()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_e',
        'status' => 'new_enquiry',
    ]);

    SupportSession::query()->update(['expires_at' => now()->subMinute()]);

    $this->get(route('admin.workspaces.support.conversation', [$workspace, $conversation]))->assertForbidden();
});

test('granting, starting and ending support access all generate audit events', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $admin = confirmedAdmin();

    $this->actingAs($owner)->post(route('settings.support.store'), [
        'duration_minutes' => 30,
    ]);

    actingAsConfirmedAdmin($this, $admin)->post(route('admin.workspaces.support.start', $workspace));
    $this->actingAs($admin)->post(route('admin.support.end'));

    $events = AuditLog::pluck('event')->all();

    expect($events)->toContain('support_access.granted', 'support_session.started', 'support_session.ended');
});
