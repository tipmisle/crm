<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkspaceMember;

function actingAsConfirmed($test, User $user)
{
    return $test->actingAs($user)->withSession(['auth.password_confirmed_at' => time()]);
}

test('owner can request workspace deletion', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    actingAsConfirmed($this, $owner)
        ->post(route('settings.privacy.delete'), ['password' => 'password'])
        ->assertRedirect();

    $workspace->refresh();

    expect($workspace->deletion_requested_at)->not->toBeNull();
    expect($workspace->deletion_requested_at->diffInDays($workspace->scheduled_deletion_at))
        ->toBe((float) config('retention.workspace_grace_days'));

    expect(AuditLog::where('event', 'privacy.workspace.deletion_requested')->where('workspace_id', $workspace->id)->exists())->toBeTrue();
});

test('non-owner member cannot request workspace deletion', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'member']);

    actingAsConfirmed($this, $member)
        ->post(route('settings.privacy.delete'), ['password' => 'password'])
        ->assertForbidden();

    expect($workspace->fresh()->deletion_requested_at)->toBeNull();
});

test('owner can cancel a pending workspace deletion', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $workspace->update(['deletion_requested_at' => now(), 'scheduled_deletion_at' => now()->addDays(30)]);

    actingAsConfirmed($this, $owner)
        ->delete(route('settings.privacy.cancel'))
        ->assertRedirect();

    $workspace->refresh();

    expect($workspace->deletion_requested_at)->toBeNull();
    expect($workspace->scheduled_deletion_at)->toBeNull();
    expect(AuditLog::where('event', 'privacy.workspace.deletion_cancelled')->exists())->toBeTrue();
});

test('cancelling a deletion that was never requested is a no-op', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    actingAsConfirmed($this, $owner)
        ->delete(route('settings.privacy.cancel'))
        ->assertStatus(422);
});
