<?php

use App\Models\Customer;
use App\Models\Workspace;
use App\Services\WorkspaceDeletionService;
use Illuminate\Support\Str;

test('a canceling subscription (cancel_at_period_end) retains access until the period ends', function () {
    [$workspace, $owner] = createWorkspaceWithSubscription('active', ['ends_at' => now()->addDays(5)]);

    $subscription = $workspace->subscription(config('billing.subscription_name'));
    expect($subscription->onGracePeriod())->toBeTrue();

    $this->actingAs($owner)->get(route('dashboard'))->assertOk();
});

test('cancellation never deletes the workspace or its data', function () {
    [$workspace, $owner] = createWorkspaceWithSubscription('active', ['ends_at' => now()->addDays(5)]);

    $customer = Customer::create(['workspace_id' => $workspace->id, 'full_name' => 'Jane Doe']);

    expect(Workspace::find($workspace->id))->not->toBeNull();
    expect(Customer::withoutGlobalScopes()->find($customer->id))->not->toBeNull();
});

test('an ended subscription resolves to a restricted access state', function () {
    [$workspace, $owner] = createWorkspaceWithSubscription('active', ['ends_at' => now()->subDay()]);

    $this->actingAs($owner)->get(route('dashboard'))->assertRedirect(route('billing.activate'));
});

test('a webhook-driven subscription cancellation never invokes WorkspaceDeletionService', function () {
    $mock = Mockery::mock(WorkspaceDeletionService::class);
    $mock->shouldNotReceive('delete');
    $this->app->instance(WorkspaceDeletionService::class, $mock);

    [$workspace] = createWorkspaceWithSubscription('active');
    $subscription = $workspace->subscription(config('billing.subscription_name'));

    $payload = [
        'id' => 'evt_'.Str::random(16),
        'type' => 'customer.subscription.deleted',
        'data' => ['object' => [
            'id' => $subscription->stripe_id,
            'customer' => $workspace->stripe_id,
            'status' => 'canceled',
        ]],
    ];
    $body = json_encode($payload);
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$body}", config('cashier.webhook.secret'));

    $this->postJson('/stripe/webhook', $payload, [
        'Stripe-Signature' => "t={$timestamp},v1={$signature}",
    ])->assertOk();

    expect(Workspace::find($workspace->id))->not->toBeNull();
});
