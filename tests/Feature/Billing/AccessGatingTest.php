<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

$gatedRoutes = [
    ['GET', 'dashboard'],
    ['GET', 'search'],
    ['GET', 'inbox.index'],
    ['GET', 'customers.index'],
    ['GET', 'orders.index'],
    ['GET', 'analytics.index'],
    ['GET', 'catalog.index'],
];

$exemptRoutes = [
    ['GET', 'settings.edit'],
    ['GET', 'settings.privacy.edit'],
    ['GET', 'settings.billing.edit'],
    ['GET', 'billing.activate'],
    ['GET', 'profile.edit'],
];

test('an active workspace reaches every gated route', function () use ($gatedRoutes) {
    [, $owner] = createWorkspaceWithSubscription('active');

    foreach ($gatedRoutes as [$method, $routeName]) {
        $this->actingAs($owner)->call($method, route($routeName))->assertOk();
    }
});

test('a workspace with no subscription is redirected to billing activation from every gated route', function () use ($gatedRoutes) {
    [, $owner] = createWorkspaceWithUser(withSubscription: false);

    foreach ($gatedRoutes as [$method, $routeName]) {
        $this->actingAs($owner)->call($method, route($routeName))->assertRedirect(route('billing.activate'));
    }
});

test('a past-due workspace is blocked by default policy', function () {
    [, $owner] = createWorkspaceWithSubscription('past_due');

    $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('billing.activate'));
});

test('settings, profile, and billing routes stay reachable regardless of subscription state', function () use ($exemptRoutes) {
    [, $owner] = createWorkspaceWithUser(withSubscription: false);

    foreach ($exemptRoutes as [$method, $routeName]) {
        $this->actingAs($owner)->call($method, route($routeName))->assertOk();
    }
});

test('demo workspaces bypass the subscription gate entirely, even with zero subscription rows', function () {
    $workspace = Workspace::create([
        'name' => 'Demo Biz',
        'slug' => 'demo-'.uniqid(),
        'is_demo' => true,
        'demo_variant' => 'services',
        'demo_expires_at' => now()->addHours(4),
    ]);
    $user = User::factory()->create(['current_workspace_id' => $workspace->id, 'is_demo' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'owner']);

    expect($workspace->subscription(config('billing.subscription_name')))->toBeNull();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

test('a platform admin bypasses the subscription gate on their own workspace, even with no subscription', function () {
    [$workspace, $owner] = createWorkspaceWithUser(withSubscription: false);
    $owner->forceFill(['is_platform_admin' => true])->save();

    $this->actingAs($owner)->get(route('dashboard'))->assertOk();
});

test('one workspace subscription never unlocks another workspace', function () {
    [, $paidOwner] = createWorkspaceWithSubscription('active');
    [, $unpaidOwner] = createWorkspaceWithUser(withSubscription: false);

    $this->actingAs($paidOwner)->get(route('dashboard'))->assertOk();
    $this->actingAs($unpaidOwner)->get(route('dashboard'))->assertRedirect(route('billing.activate'));
});
