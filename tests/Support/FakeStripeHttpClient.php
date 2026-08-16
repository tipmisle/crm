<?php

namespace Tests\Support;

use Stripe\HttpClient\ClientInterface;

/**
 * Minimal fake for Stripe's HTTP client, used only in tests that exercise
 * a real Checkout-session-creation code path (App\Http\Controllers\Billing\ActivationController::checkout)
 * without making a live network call to Stripe. Records every request so
 * tests can assert exactly which price ID/params were sent.
 */
class FakeStripeHttpClient implements ClientInterface
{
    /** @var array<int, array{method: string, url: string, params: array}> */
    public array $requests = [];

    public function request($method, $absUrl, $headers, $params, $hasFile, $apiMode = 'v1')
    {
        $this->requests[] = ['method' => $method, 'url' => $absUrl, 'params' => $params];

        $body = match (true) {
            str_contains($absUrl, '/v1/customers') => json_encode([
                'id' => 'cus_test_fake',
                'object' => 'customer',
            ]),
            str_contains($absUrl, '/v1/checkout/sessions') => json_encode([
                'id' => 'cs_test_fake',
                'object' => 'checkout.session',
                'url' => 'https://checkout.stripe.com/test/session/cs_test_fake',
                'mode' => 'subscription',
            ]),
            str_contains($absUrl, '/v1/subscriptions/') => json_encode([
                'id' => 'sub_test_fake',
                'object' => 'subscription',
                'status' => 'canceled',
            ]),
            default => json_encode(['id' => 'fake', 'object' => 'unknown']),
        };

        return [$body, 200, []];
    }
}
