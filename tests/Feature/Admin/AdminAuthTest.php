<?php

test('a logged-out visitor cannot access the admin area', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
});

test('a regular workspace user cannot access the admin area', function () {
    [$workspace, $user] = createWorkspaceWithUser();

    $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
});

test('a workspace owner is not treated as a platform admin', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    expect($owner->isPlatformAdmin())->toBeFalse();

    $this->actingAs($owner)->get(route('admin.workspaces.index'))->assertForbidden();
});

test('a platform admin can access the admin dashboard', function () {
    $admin = createPlatformAdmin();

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk();
});

test('a deactivated platform admin is denied', function () {
    $admin = createPlatformAdmin(['is_active' => false]);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertForbidden();
});
