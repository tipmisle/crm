<?php

namespace App\Events;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired for every successful subscription invoice payment. Carries the
 * normalized identifiers/facts a FUTURE Slovenian invoicing/fiscalization
 * milestone will need — this milestone does not consume it (no listener
 * registered). Never carries card/payment-method secrets. See
 * docs/billing.md Part 27.
 */
class BillingPaymentSucceeded
{
    use Dispatchable;

    public function __construct(
        public readonly int $workspaceId,
        public readonly string $stripeCustomerId,
        public readonly string $stripeSubscriptionId,
        public readonly string $stripeInvoiceId,
        public readonly int $amount,
        public readonly string $currency,
        public readonly CarbonImmutable $paidAt,
    ) {}
}
