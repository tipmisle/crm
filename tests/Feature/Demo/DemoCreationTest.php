<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Models\Workspace;

test('creating a services demo provisions an isolated, correctly configured workspace', function () {
    $response = $this->post(route('demo.create', 'services'));

    $response->assertRedirect(route('dashboard'));

    $user = auth()->user();
    expect($user)->not->toBeNull();
    expect($user->is_demo)->toBeTrue();

    $workspace = $user->currentWorkspace;
    expect($workspace)->not->toBeNull();
    expect($workspace->is_demo)->toBeTrue();
    expect($workspace->demo_variant)->toBe('services');
    expect($workspace->orders_enabled)->toBeFalse();
    expect($workspace->appointments_enabled)->toBeTrue();
    expect($workspace->demo_expires_at)->not->toBeNull();
    expect($workspace->demo_expires_at->diffInMinutes(now()->addHours(4)))->toBeLessThan(2);
});

test('creating an orders demo provisions an isolated, correctly configured workspace', function () {
    $response = $this->post(route('demo.create', 'orders'));

    $response->assertRedirect(route('dashboard'));

    $workspace = auth()->user()->currentWorkspace;
    expect($workspace->demo_variant)->toBe('orders');
    expect($workspace->orders_enabled)->toBeTrue();
    expect($workspace->appointments_enabled)->toBeFalse();
    expect($workspace->name)->toBe('Sladka delavnica');
});

test('creating a mixed demo enables both orders and appointments', function () {
    $this->post(route('demo.create', 'both'));

    $workspace = auth()->user()->currentWorkspace;
    expect($workspace->demo_variant)->toBe('both');
    expect($workspace->orders_enabled)->toBeTrue();
    expect($workspace->appointments_enabled)->toBeTrue();
    expect($workspace->name)->toBe('Foto studio Luna');
});

test('an invalid demo variant is rejected', function () {
    $this->post(route('demo.create', 'not-a-real-variant'))->assertSessionHasErrors('variant');
});

test('two demo visitors never share a workspace or its data', function () {
    $this->post(route('demo.create', 'services'));
    $workspaceA = auth()->user()->currentWorkspace;
    $userA = auth()->user();
    auth()->logout();

    $this->post(route('demo.create', 'services'));
    $workspaceB = auth()->user()->currentWorkspace;
    $userB = auth()->user();

    expect($workspaceA->id)->not->toBe($workspaceB->id);
    expect($userA->id)->not->toBe($userB->id);
    expect($workspaceA->slug)->not->toBe($workspaceB->slug);

    $customersA = Customer::withoutGlobalScopes()->where('workspace_id', $workspaceA->id)->pluck('id');
    $customersB = Customer::withoutGlobalScopes()->where('workspace_id', $workspaceB->id)->pluck('id');

    expect($customersA)->not->toBeEmpty();
    expect($customersB)->not->toBeEmpty();
    expect($customersA->intersect($customersB)->isEmpty())->toBeTrue();
});

test('the demo user is automatically authenticated after creation', function () {
    expect(auth()->check())->toBeFalse();

    $this->post(route('demo.create', 'services'));

    expect(auth()->check())->toBeTrue();
    expect(auth()->user())->toBeInstanceOf(User::class);
});

test('starting a new demo while already logged into a previous demo session does not corrupt seeding', function () {
    // Regression test: seeding used to run while still authenticated into
    // the PREVIOUS demo's workspace, so BelongsToWorkspace's global scope
    // filtered lazy-loaded relations (e.g. Order::customer) by the wrong
    // workspace during seeding, producing null relations and a 500.
    $this->post(route('demo.create', 'services'));
    $firstWorkspaceId = auth()->user()->currentWorkspace->id;

    $response = $this->post(route('demo.create', 'orders'));

    $response->assertRedirect(route('dashboard'));

    $workspace = auth()->user()->currentWorkspace;
    expect($workspace->id)->not->toBe($firstWorkspaceId);
    expect($workspace->demo_variant)->toBe('orders');
    expect($workspace->orders()->count())->toBeGreaterThan(0);

    $orderCustomerIds = Order::withoutGlobalScopes()->where('workspace_id', $workspace->id)->pluck('customer_id');
    $validCustomerIds = Customer::withoutGlobalScopes()->where('workspace_id', $workspace->id)->pluck('id');

    expect($orderCustomerIds->every(fn ($id) => $id !== null && $validCustomerIds->contains($id)))->toBeTrue();
});
