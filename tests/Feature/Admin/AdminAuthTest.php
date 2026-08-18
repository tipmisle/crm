<?php

use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;

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

test('a platform admin without confirmed MFA is redirected away from admin instead of let in', function () {
    $admin = createPlatformAdmin([], withMfa: false);

    $this->actingAs($admin)->get(route('admin.dashboard'))
        ->assertRedirect(route('profile.edit'));
});

test('a platform admin with an unconfirmed (pending setup) secret is still denied admin access', function () {
    $admin = createPlatformAdmin([], withMfa: false);
    app(EnableTwoFactorAuthentication::class)($admin);
    // Deliberately no two_factor_confirmed_at — setup started but never finished.

    $this->actingAs($admin->fresh())->get(route('admin.dashboard'))
        ->assertRedirect(route('profile.edit'));
});

test('a normal (non-admin) user cannot access admin regardless of their own MFA state', function () {
    [$workspace, $user] = createWorkspaceWithUser();
    app(EnableTwoFactorAuthentication::class)($user);
    $user->forceFill(['two_factor_confirmed_at' => now()])->save();

    $this->actingAs($user->fresh())->get(route('admin.dashboard'))->assertForbidden();
});
