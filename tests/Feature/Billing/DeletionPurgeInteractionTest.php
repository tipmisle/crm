<?php

use App\Models\Workspace;
use App\Services\WorkspaceDeletionService;
use Laravel\Cashier\Subscription;
use Stripe\ApiRequestor;
use Stripe\Exception\ApiConnectionException;
use Stripe\HttpClient\ClientInterface;
use Tests\Support\FakeStripeHttpClient;

afterEach(function () {
    ApiRequestor::setHttpClient(null);
});

test('purging a workspace with an active subscription cancels it as a non-blocking step', function () {
    [$workspace] = createWorkspaceWithSubscription('active');
    $subscription = $workspace->subscription(config('billing.subscription_name'));

    $fake = new FakeStripeHttpClient;
    ApiRequestor::setHttpClient($fake);

    app(WorkspaceDeletionService::class)->delete($workspace->fresh());

    expect(Workspace::find($workspace->id))->toBeNull();
    expect(Subscription::find($subscription->id))->toBeNull();

    // cancelNow() -> Stripe subscriptions.cancel, a DELETE-shaped call to
    // the subscriptions endpoint, was actually attempted before deletion.
    $cancelRequest = collect($fake->requests)->first(fn ($r) => str_contains($r['url'], '/v1/subscriptions/'));
    expect($cancelRequest)->not->toBeNull();
});

test('a stripe cancellation failure during purge never blocks the actual data purge', function () {
    [$workspace] = createWorkspaceWithSubscription('active');

    $throwing = new class implements ClientInterface
    {
        public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1')
        {
            throw new ApiConnectionException('Simulated network failure.');
        }
    };
    ApiRequestor::setHttpClient($throwing);

    app(WorkspaceDeletionService::class)->delete($workspace->fresh());

    expect(Workspace::find($workspace->id))->toBeNull();
});

test('a workspace with no subscription purges cleanly without attempting a stripe call', function () {
    [$workspace] = createWorkspaceWithUser(withSubscription: false);

    $fake = new FakeStripeHttpClient;
    ApiRequestor::setHttpClient($fake);

    app(WorkspaceDeletionService::class)->delete($workspace->fresh());

    expect(Workspace::find($workspace->id))->toBeNull();
    expect($fake->requests)->toBeEmpty();
});
