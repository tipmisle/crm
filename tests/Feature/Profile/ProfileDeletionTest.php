<?php

use App\Models\AuditLog;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

test('sole owner of a solo workspace has the workspace cascaded on account deletion', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    $this->actingAs($owner)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    expect(Workspace::find($workspace->id))->toBeNull();
    expect(User::find($owner->id))->toBeNull();
    expect(AuditLog::where('event', 'privacy.account.deleted')->exists())->toBeTrue();
});

test('owner of a multi-member workspace cannot delete their account', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'member']);

    $this->actingAs($owner)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('workspace')
        ->assertRedirect(route('profile.edit'));

    expect(Workspace::find($workspace->id))->not->toBeNull();
    expect(User::find($owner->id))->not->toBeNull();
});

test('a non-owner member can delete their account without affecting the workspace', function () {
    [$workspace, $owner] = createWorkspaceWithUser();
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'member']);

    $this->actingAs($member)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertRedirect('/');

    expect(User::find($member->id))->toBeNull();
    expect(Workspace::find($workspace->id))->not->toBeNull();
    expect(WorkspaceMember::where('workspace_id', $workspace->id)->where('user_id', $owner->id)->exists())->toBeTrue();
});
