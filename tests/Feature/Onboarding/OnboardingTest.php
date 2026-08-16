<?php

use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;

test('a new incomplete workspace is routed to onboarding after Stripe activation success', function () {
    [, $owner] = createWorkspaceWithUser(onboardingCompleted: false);

    $this->actingAs($owner)
        ->get(route('billing.activate.success'))
        ->assertRedirect(route('onboarding.show'));
});

test('a workspace that already completed onboarding is routed straight to Today after activation success', function () {
    [, $owner] = createWorkspaceWithUser();

    $this->actingAs($owner)
        ->get(route('billing.activate.success'))
        ->assertRedirect(route('dashboard'));
});

test('an existing (already onboarded) workspace bypasses onboarding entirely', function () {
    [$workspace, $owner] = createWorkspaceWithUser();

    expect($workspace->fresh()->onboarding_completed_at)->not->toBeNull();

    $this->actingAs($owner)->get(route('dashboard'))->assertOk();
    $this->actingAs($owner)->get(route('onboarding.show'))->assertRedirect(route('dashboard'));
});

test('demo workspaces bypass onboarding even with onboarding_completed_at null', function () {
    $workspace = Workspace::create([
        'name' => 'Demo Biz',
        'slug' => 'demo-'.uniqid(),
        'is_demo' => true,
        'demo_variant' => 'services',
        'demo_expires_at' => now()->addHours(4),
    ]);
    $user = User::factory()->create(['current_workspace_id' => $workspace->id, 'is_demo' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'owner']);

    expect($workspace->onboarding_completed_at)->toBeNull();
    expect($workspace->needsOnboarding())->toBeFalse();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();
});

test('an incomplete workspace cannot select neither Orders nor Appointments from onboarding', function () {
    [$workspace, $owner] = createWorkspaceWithUser(onboardingCompleted: false);

    $this->actingAs($owner)->patch(route('settings.capabilities.update'), [
        'orders_enabled' => false,
        'appointments_enabled' => false,
        'accepts_deposit' => true,
    ])->assertRedirect();

    expect($workspace->fresh()->orders_enabled)->toBeTrue();
});

test('capabilities and business name selected during onboarding persist', function () {
    [$workspace, $owner] = createWorkspaceWithUser(onboardingCompleted: false);

    $this->actingAs($owner)->patch(route('settings.update'), [
        'name' => 'Nova obrt',
        'email' => null,
        'timezone' => $workspace->timezone,
        'currency' => $workspace->currency,
    ])->assertRedirect();

    $this->actingAs($owner)->patch(route('settings.capabilities.update'), [
        'orders_enabled' => false,
        'appointments_enabled' => true,
        'accepts_deposit' => false,
    ])->assertRedirect();

    $workspace->refresh();
    expect($workspace->name)->toBe('Nova obrt');
    expect($workspace->orders_enabled)->toBeFalse();
    expect($workspace->appointments_enabled)->toBeTrue();
    expect($workspace->accepts_deposit)->toBeFalse();
});

test('the onboarding catalog step reflects an existing product', function () {
    [$workspace, $owner] = createWorkspaceWithUser(onboardingCompleted: false);

    $response = $this->actingAs($owner)->get(route('onboarding.show'));
    $response->assertInertia(fn ($page) => $page->where('hasCatalogItems', false));

    Product::create([
        'workspace_id' => $workspace->id,
        'name' => 'Torta',
        'default_price' => 25,
    ]);

    $response = $this->actingAs($owner)->get(route('onboarding.show'));
    $response->assertInertia(fn ($page) => $page->where('hasCatalogItems', true));
});

test('the onboarding catalog step reflects an existing service', function () {
    [$workspace, $owner] = createWorkspaceWithUser(onboardingCompleted: false);

    Service::create([
        'workspace_id' => $workspace->id,
        'name' => 'Striženje',
        'default_price' => 20,
        'default_duration_minutes' => 30,
    ]);

    $response = $this->actingAs($owner)->get(route('onboarding.show'));
    $response->assertInertia(fn ($page) => $page->where('hasCatalogItems', true));
});

test('the onboarding messages step reflects a connected Meta channel', function () {
    [$workspace, $owner] = createWorkspaceWithUser(onboardingCompleted: false);

    $response = $this->actingAs($owner)->get(route('onboarding.show'));
    $response->assertInertia(fn ($page) => $page->where('channels', []));

    createMetaChannel($workspace);

    $response = $this->actingAs($owner)->get(route('onboarding.show'));
    $response->assertInertia(fn ($page) => $page->has('channels', 1)
        ->where('channels.0.status', 'connected'));
});

test('onboarding can be completed with no catalog items, no Meta channel, and no invoicing configured', function () {
    [$workspace, $owner] = createWorkspaceWithUser(onboardingCompleted: false);

    $this->actingAs($owner)
        ->post(route('onboarding.complete'))
        ->assertRedirect(route('dashboard'));

    expect($workspace->fresh()->onboarding_completed_at)->not->toBeNull();
});

test('completing onboarding sends the user to Today', function () {
    [, $owner] = createWorkspaceWithUser(onboardingCompleted: false);

    $response = $this->actingAs($owner)->post(route('onboarding.complete'));

    $response->assertRedirect(route('dashboard'));
});

test('an incomplete workspace is redirected to onboarding instead of entering normal product pages', function () {
    [, $owner] = createWorkspaceWithUser(onboardingCompleted: false);

    foreach (['dashboard', 'inbox.index', 'customers.index', 'orders.index', 'catalog.index', 'analytics.index'] as $routeName) {
        $this->actingAs($owner)->get(route($routeName))->assertRedirect(route('onboarding.show'));
    }
});

test('billing, settings, and privacy stay reachable while onboarding is incomplete', function () {
    [, $owner] = createWorkspaceWithUser(onboardingCompleted: false);

    foreach (['settings.edit', 'settings.privacy.edit', 'settings.billing.edit', 'billing.activate', 'profile.edit'] as $routeName) {
        $this->actingAs($owner)->get(route($routeName))->assertOk();
    }
});
