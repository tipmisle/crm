<?php

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Stripe\ApiRequestor;
use Tests\Support\FakeStripeHttpClient;

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

test('owner can initiate checkout using the server-configured price id', function () {
    [$workspace, $owner] = createWorkspaceWithUser(withSubscription: false);

    $fake = new FakeStripeHttpClient;
    ApiRequestor::setHttpClient($fake);

    $response = $this->actingAs($owner)->post(route('billing.activate.checkout'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('checkout.stripe.com');

    $checkoutRequest = collect($fake->requests)->first(fn ($r) => str_contains($r['url'], '/v1/checkout/sessions'));
    expect($checkoutRequest)->not->toBeNull();
    expect($checkoutRequest['params']['line_items'][0]['price'])->toBe(config('billing.monthly_price_id'));
});

test('a request cannot override the price id used for checkout', function () {
    [$workspace, $owner] = createWorkspaceWithUser(withSubscription: false);

    $fake = new FakeStripeHttpClient;
    ApiRequestor::setHttpClient($fake);

    $this->actingAs($owner)->post(route('billing.activate.checkout'), [
        'price_id' => 'price_attacker_injected',
        'monthly_price_id' => 'price_attacker_injected',
    ]);

    $checkoutRequest = collect($fake->requests)->first(fn ($r) => str_contains($r['url'], '/v1/checkout/sessions'));
    expect($checkoutRequest['params']['line_items'][0]['price'])->toBe(config('billing.monthly_price_id'));
    expect($checkoutRequest['params']['line_items'][0]['price'])->not->toBe('price_attacker_injected');
});

test('a non-owner member cannot initiate checkout', function () {
    [$workspace, $owner] = createWorkspaceWithUser(withSubscription: false);
    $member = User::factory()->create(['current_workspace_id' => $workspace->id]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $member->id, 'role' => 'member']);

    $this->actingAs($member)->post(route('billing.activate.checkout'))->assertForbidden();
});

test('a demo workspace cannot initiate checkout', function () {
    $workspace = Workspace::create([
        'name' => 'Demo Biz',
        'slug' => 'demo-'.uniqid(),
        'is_demo' => true,
        'demo_variant' => 'services',
        'demo_expires_at' => now()->addHours(4),
    ]);
    $user = User::factory()->create(['current_workspace_id' => $workspace->id, 'is_demo' => true]);
    WorkspaceMember::create(['workspace_id' => $workspace->id, 'user_id' => $user->id, 'role' => 'owner']);

    $this->actingAs($user)->post(route('billing.activate.checkout'))->assertNotFound();
});

test('an already-active workspace is redirected to billing settings instead of starting a second checkout', function () {
    [$workspace, $owner] = createWorkspaceWithSubscription('active');

    $fake = new FakeStripeHttpClient;
    ApiRequestor::setHttpClient($fake);

    $response = $this->actingAs($owner)->post(route('billing.activate.checkout'));

    $response->assertRedirect(route('settings.billing.edit'));
    expect($fake->requests)->toBeEmpty();
});

test('repeated registration and checkout attempts never create a second workspace or user', function () {
    $payload = [
        'name' => 'Test User',
        'email' => 'retry@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms_dpa_accepted' => true,
    ];

    $this->post('/register', $payload);
    expect(User::where('email', 'retry@example.com')->count())->toBe(1);
    expect(Workspace::count())->toBe(1);

    // Retrying the activation step (e.g. user navigates back and re-submits)
    // never re-runs registration — the user/workspace already exist.
    $user = User::where('email', 'retry@example.com')->first();

    $fake = new FakeStripeHttpClient;
    ApiRequestor::setHttpClient($fake);

    $this->actingAs($user)->post(route('billing.activate.checkout'));
    $this->actingAs($user)->post(route('billing.activate.checkout'));

    expect(User::where('email', 'retry@example.com')->count())->toBe(1);
    expect(Workspace::count())->toBe(1);
});
