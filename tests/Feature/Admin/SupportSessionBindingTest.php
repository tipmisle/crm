<?php

use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\SupportSession;

test('a support session id belonging to a different admin is rejected', function () {
    $adminA = createPlatformAdmin();
    $adminB = createPlatformAdmin();
    [$workspace, $owner] = createWorkspaceWithUser();
    $channel = createMetaChannel($workspace);

    createSupportGrant($workspace, $owner, 'workspace_content');

    $this->actingAs($adminA)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.workspaces.support.start', $workspace));

    $sessionOwnedByA = SupportSession::where('admin_user_id', $adminA->id)->latest('id')->first();
    expect($sessionOwnedByA)->not->toBeNull();

    $conversation = Conversation::withoutGlobalScopes()->create([
        'workspace_id' => $workspace->id,
        'channel_id' => $channel->id,
        'external_conversation_id' => 'sender_mismatch',
        'status' => 'new_enquiry',
    ]);

    // Admin B's browser somehow carries admin A's support_session id (e.g.
    // stale/shared session state) — must never be honored for B.
    $this->actingAs($adminB)
        ->withSession(['active_support_session_id' => $sessionOwnedByA->id])
        ->get(route('admin.workspaces.support.conversation', [$workspace, $conversation]))
        ->assertForbidden();

    // Admin A's own session is untouched — it wasn't ended, just refused
    // for the mismatched requester.
    expect($sessionOwnedByA->fresh()->ended_at)->toBeNull();

    expect(AuditLog::where('event', 'support_session.admin_mismatch')->where('workspace_id', $workspace->id)->exists())->toBeTrue();
});

test('starting a new support session closes any existing one for that admin', function () {
    $admin = createPlatformAdmin();
    [$workspaceA, $ownerA] = createWorkspaceWithUser();
    [$workspaceB, $ownerB] = createWorkspaceWithUser();

    createSupportGrant($workspaceA, $ownerA, 'workspace_content');
    createSupportGrant($workspaceB, $ownerB, 'workspace_content');

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.workspaces.support.start', $workspaceA));

    $firstSession = SupportSession::where('admin_user_id', $admin->id)->latest('id')->first();
    expect($firstSession->ended_at)->toBeNull();

    $this->actingAs($admin)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.workspaces.support.start', $workspaceB));

    expect($firstSession->fresh()->ended_at)->not->toBeNull();
    expect($firstSession->fresh()->ended_reason)->toBe('replaced');

    $secondSession = SupportSession::where('admin_user_id', $admin->id)->latest('id')->first();
    expect($secondSession->id)->not->toBe($firstSession->id);
    expect($secondSession->workspace_id)->toBe($workspaceB->id);
    expect($secondSession->ended_at)->toBeNull();
});
