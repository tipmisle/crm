<?php

use App\Models\Workspace;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

test('billing settings response never contains stripe secrets', function () {
    [$workspace, $owner] = createWorkspaceWithSubscription();

    $response = $this->actingAs($owner)->get(route('settings.billing.edit'));
    $response->assertOk();

    $body = $response->getContent();
    expect($body)->not->toContain(config('cashier.secret'));
    expect($body)->not->toContain(config('cashier.webhook.secret'));
});

test('admin workspace billing metadata never contains stripe secrets', function () {
    [$workspace] = createWorkspaceWithSubscription();
    $admin = createPlatformAdmin();

    $response = $this->actingAs($admin)->get(route('admin.workspaces.show', $workspace));
    $response->assertOk();

    $body = $response->getContent();
    expect($body)->not->toContain(config('cashier.secret'));
    expect($body)->not->toContain(config('cashier.webhook.secret'));
});

test('the shared billing prop never leaks a stripe secret', function () {
    [$workspace, $owner] = createWorkspaceWithSubscription();

    $response = $this->actingAs($owner)->get(route('dashboard'));

    expect($response->getContent())->not->toContain(config('cashier.secret'));
});

test('demo workspace creation never creates a Stripe customer or subscription', function () {
    $response = $this->post(route('demo.create', 'services'));

    $response->assertRedirect();

    $demoWorkspace = Workspace::where('is_demo', true)->latest('id')->first();

    expect($demoWorkspace)->not->toBeNull();
    expect($demoWorkspace->stripe_id)->toBeNull();
    expect($demoWorkspace->subscription(config('billing.subscription_name')))->toBeNull();
});

test('nothing is logged from the stripe webhook signature header', function () {
    Log::spy();

    [$workspace] = createWorkspaceWithSubscription();
    $subscription = $workspace->subscription(config('billing.subscription_name'));

    $payload = [
        'id' => 'evt_'.Str::random(16),
        'type' => 'customer.subscription.updated',
        'data' => ['object' => [
            'id' => $subscription->stripe_id,
            'customer' => $workspace->stripe_id,
            'status' => 'active',
            'cancel_at_period_end' => true,
            'current_period_end' => now()->addMonth()->timestamp,
            'items' => ['data' => [['id' => 'si_x', 'price' => ['id' => $subscription->stripe_price, 'product' => 'prod_test'], 'quantity' => 1]]],
        ]],
    ];
    $body = json_encode($payload);
    $timestamp = time();
    $signatureHeader = "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$body}", config('cashier.webhook.secret'));

    $this->postJson('/stripe/webhook', $payload, ['Stripe-Signature' => $signatureHeader])->assertOk();

    // The handler never logs anything on a successful, signature-valid
    // delivery — in particular never the signature header itself.
    Log::shouldNotHaveReceived('warning');
    Log::shouldNotHaveReceived('info');
});
