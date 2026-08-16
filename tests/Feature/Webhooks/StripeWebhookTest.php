<?php

use App\Events\BillingPaymentSucceeded;
use App\Models\AuditLog;
use App\Models\StripeWebhookEvent;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Cashier\Subscription;

function stripeWebhookSignatureHeader(string $payload, ?string $secret = null): string
{
    $secret = $secret ?? config('cashier.webhook.secret');
    $timestamp = time();
    $signedPayload = "{$timestamp}.{$payload}";
    $signature = hash_hmac('sha256', $signedPayload, $secret);

    return "t={$timestamp},v1={$signature}";
}

function postStripeWebhook(array $payload, ?string $secret = null): TestResponse
{
    $body = json_encode($payload);

    return test()->postJson('/stripe/webhook', $payload, [
        'Stripe-Signature' => stripeWebhookSignatureHeader($body, $secret),
    ]);
}

function subscriptionUpdatedPayload(Workspace $workspace, Subscription $subscription, array $objectOverrides = []): array
{
    return [
        'id' => 'evt_'.Str::random(16),
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => array_merge([
                'id' => $subscription->stripe_id,
                'customer' => $workspace->stripe_id,
                'status' => 'active',
                'cancel_at_period_end' => false,
                'current_period_end' => now()->addMonth()->timestamp,
                'items' => ['data' => [[
                    'id' => 'si_'.Str::random(10),
                    'price' => ['id' => $subscription->stripe_price, 'product' => 'prod_test'],
                    'quantity' => 1,
                ]]],
                'metadata' => ['type' => $subscription->type],
            ], $objectOverrides),
        ],
    ];
}

test('invalid webhook signature is rejected', function () {
    [$workspace] = createWorkspaceWithSubscription();
    $subscription = $workspace->subscription(config('billing.subscription_name'));

    $payload = subscriptionUpdatedPayload($workspace, $subscription, ['cancel_at_period_end' => true]);
    $body = json_encode($payload);

    $response = test()->postJson('/stripe/webhook', $payload, [
        'Stripe-Signature' => stripeWebhookSignatureHeader($body, 'wrong_secret'),
    ]);

    $response->assertForbidden();
    expect($subscription->fresh()->stripe_status)->toBe('active');
});

test('a duplicate stripe event does not duplicate audit rows', function () {
    [$workspace] = createWorkspaceWithSubscription();
    $subscription = $workspace->subscription(config('billing.subscription_name'));

    $payload = subscriptionUpdatedPayload($workspace, $subscription, ['cancel_at_period_end' => true]);

    postStripeWebhook($payload)->assertOk();
    postStripeWebhook($payload)->assertOk();

    expect(StripeWebhookEvent::where('stripe_event_id', $payload['id'])->count())->toBe(1);
    expect(AuditLog::where('event', 'billing.subscription_cancel_scheduled')->where('workspace_id', $workspace->id)->count())->toBe(1);
});

test('customer.subscription.updated with cancel_at_period_end schedules cancellation and keeps access', function () {
    [$workspace] = createWorkspaceWithSubscription();
    $subscription = $workspace->subscription(config('billing.subscription_name'));

    $payload = subscriptionUpdatedPayload($workspace, $subscription, ['cancel_at_period_end' => true]);

    postStripeWebhook($payload)->assertOk();

    expect($subscription->fresh()->onGracePeriod())->toBeTrue();
    expect(AuditLog::where('event', 'billing.subscription_cancel_scheduled')->exists())->toBeTrue();
});

test('customer.subscription.updated resuming from cancel logs subscription_resumed', function () {
    [$workspace] = createWorkspaceWithSubscription('active', ['ends_at' => now()->addDays(10)]);
    $subscription = $workspace->subscription(config('billing.subscription_name'));

    expect($subscription->onGracePeriod())->toBeTrue();

    $payload = subscriptionUpdatedPayload($workspace, $subscription, ['cancel_at_period_end' => false]);

    postStripeWebhook($payload)->assertOk();

    expect($subscription->fresh()->onGracePeriod())->toBeFalse();
    expect(AuditLog::where('event', 'billing.subscription_resumed')->exists())->toBeTrue();
});

test('customer.subscription.deleted only updates local status and never deletes workspace data', function () {
    [$workspace] = createWorkspaceWithSubscription();
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

    postStripeWebhook($payload)->assertOk();

    expect(Workspace::find($workspace->id))->not->toBeNull();
    expect($subscription->fresh()->ends_at)->not->toBeNull();
    expect(AuditLog::where('event', 'billing.subscription_ended')->exists())->toBeTrue();
});

test('invoice.payment_failed logs a payment_failed audit event', function () {
    [$workspace] = createWorkspaceWithSubscription();

    $payload = [
        'id' => 'evt_'.Str::random(16),
        'type' => 'invoice.payment_failed',
        'data' => ['object' => [
            'id' => 'in_'.Str::random(10),
            'customer' => $workspace->stripe_id,
        ]],
    ];

    postStripeWebhook($payload)->assertOk();

    expect(AuditLog::where('event', 'billing.payment_failed')->where('workspace_id', $workspace->id)->exists())->toBeTrue();
});

test('invoice.payment_succeeded dispatches BillingPaymentSucceeded', function () {
    Event::fake([BillingPaymentSucceeded::class]);

    [$workspace] = createWorkspaceWithSubscription();
    $subscription = $workspace->subscription(config('billing.subscription_name'));

    $payload = [
        'id' => 'evt_'.Str::random(16),
        'type' => 'invoice.payment_succeeded',
        'data' => ['object' => [
            'id' => 'in_'.Str::random(10),
            'customer' => $workspace->stripe_id,
            'subscription' => $subscription->stripe_id,
            'amount_paid' => 1900,
            'currency' => 'eur',
            'status_transitions' => ['paid_at' => now()->timestamp],
        ]],
    ];

    postStripeWebhook($payload)->assertOk();

    Event::assertDispatched(BillingPaymentSucceeded::class, function ($event) use ($workspace) {
        return $event->workspaceId === $workspace->id && $event->amount === 1900 && $event->currency === 'eur';
    });
});
