<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\SubscriptionAccessState;
use App\Events\BillingPaymentSucceeded;
use App\Models\AuditLog;
use App\Models\StripeWebhookEvent;
use App\Models\Workspace;
use App\Services\Billing\WorkspaceSubscriptionStateService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Laravel\Cashier\Cashier;
use Laravel\Cashier\Http\Controllers\WebhookController as CashierWebhookController;

/**
 * Thin subclass of Cashier's official webhook controller — reuses its
 * signature verification (applied automatically in the parent constructor
 * when cashier.webhook.secret is configured) and event dispatch, adding
 * only: webhook-event dedupe, Slovenian-labeled audit events, and the
 * BillingPaymentSucceeded hook for a future invoicing milestone.
 *
 * handleCustomerSubscriptionDeleted only ever updates local subscription
 * status via parent:: — it must NEVER call WorkspaceDeletionService or
 * anything data-deleting. Cancellation and deletion are separate systems;
 * the only direction that exists is purge -> cancel Stripe (see
 * WorkspaceDeletionService), never the reverse. See docs/billing.md.
 */
class StripeWebhookController extends CashierWebhookController
{
    protected function handleCustomerSubscriptionCreated(array $payload)
    {
        if (! $this->markProcessedOrSkip($payload)) {
            return $this->successMethod();
        }

        $response = parent::handleCustomerSubscriptionCreated($payload);

        $workspace = Cashier::findBillable($payload['data']['object']['customer'] ?? null);

        if ($workspace instanceof Workspace) {
            $state = app(WorkspaceSubscriptionStateService::class)->for($workspace);

            if ($state === SubscriptionAccessState::Active) {
                AuditLog::record('billing.subscription_activated', null, $workspace->id, $workspace, [
                    'stripe_customer_id' => $workspace->stripe_id,
                    'stripe_subscription_id' => $payload['data']['object']['id'] ?? null,
                ]);
            }
        }

        return $response;
    }

    protected function handleCustomerSubscriptionUpdated(array $payload)
    {
        if (! $this->markProcessedOrSkip($payload)) {
            return $this->successMethod();
        }

        $workspace = Cashier::findBillable($payload['data']['object']['customer'] ?? null);
        $wasOnGracePeriod = $workspace instanceof Workspace
            ? $workspace->subscription(config('billing.subscription_name'))?->onGracePeriod()
            : null;

        $response = parent::handleCustomerSubscriptionUpdated($payload);

        if ($workspace instanceof Workspace) {
            // parent:: updates the subscription row via a fresh query, not
            // through $workspace's relations — the "subscriptions" relation
            // cached on $workspace above is now stale, must be re-queried.
            $workspace->unsetRelation('subscriptions');
            $subscription = $workspace->subscription(config('billing.subscription_name'));
            $isOnGracePeriod = $subscription?->onGracePeriod() ?? false;

            $metadata = [
                'stripe_customer_id' => $workspace->stripe_id,
                'stripe_subscription_id' => $payload['data']['object']['id'] ?? null,
                'cancel_at_period_end' => $payload['data']['object']['cancel_at_period_end'] ?? false,
            ];

            if ($isOnGracePeriod && ! $wasOnGracePeriod) {
                AuditLog::record('billing.subscription_cancel_scheduled', null, $workspace->id, $workspace, $metadata);
            } elseif (! $isOnGracePeriod && $wasOnGracePeriod) {
                AuditLog::record('billing.subscription_resumed', null, $workspace->id, $workspace, $metadata);
            } elseif (! $wasOnGracePeriod && app(WorkspaceSubscriptionStateService::class)->for($workspace) === SubscriptionAccessState::Active) {
                AuditLog::record('billing.subscription_activated', null, $workspace->id, $workspace, $metadata);
            }
        }

        return $response;
    }

    protected function handleCustomerSubscriptionDeleted(array $payload)
    {
        if (! $this->markProcessedOrSkip($payload)) {
            return $this->successMethod();
        }

        // parent:: only updates local subscription status columns —
        // never touches workspace/customer/message data. See class docblock.
        $response = parent::handleCustomerSubscriptionDeleted($payload);

        $workspace = Cashier::findBillable($payload['data']['object']['customer'] ?? null);

        if ($workspace instanceof Workspace) {
            AuditLog::record('billing.subscription_ended', null, $workspace->id, $workspace, [
                'stripe_customer_id' => $workspace->stripe_id,
                'stripe_subscription_id' => $payload['data']['object']['id'] ?? null,
            ]);
        }

        return $response;
    }

    protected function handleInvoicePaymentFailed(array $payload)
    {
        if (! $this->markProcessedOrSkip($payload)) {
            return $this->successMethod();
        }

        $workspace = Cashier::findBillable($payload['data']['object']['customer'] ?? null);

        if ($workspace instanceof Workspace) {
            AuditLog::record('billing.payment_failed', null, $workspace->id, $workspace, [
                'stripe_customer_id' => $workspace->stripe_id,
                'stripe_invoice_id' => $payload['data']['object']['id'] ?? null,
            ]);
        }

        return $this->successMethod();
    }

    protected function handleInvoicePaymentSucceeded(array $payload)
    {
        if (! $this->markProcessedOrSkip($payload)) {
            return $this->successMethod();
        }

        $invoice = $payload['data']['object'] ?? [];
        $workspace = Cashier::findBillable($invoice['customer'] ?? null);

        if ($workspace instanceof Workspace) {
            event(new BillingPaymentSucceeded(
                workspaceId: $workspace->id,
                stripeCustomerId: (string) $workspace->stripe_id,
                stripeSubscriptionId: (string) ($invoice['subscription'] ?? ''),
                stripeInvoiceId: (string) ($invoice['id'] ?? ''),
                amount: (int) ($invoice['amount_paid'] ?? 0),
                currency: (string) ($invoice['currency'] ?? config('billing.currency')),
                paidAt: isset($invoice['status_transitions']['paid_at'])
                    ? Carbon::createFromTimestamp($invoice['status_transitions']['paid_at'])->toImmutable()
                    : now()->toImmutable(),
            ));
        }

        return $this->successMethod();
    }

    /**
     * Insert-or-skip using the unique constraint as the concurrency-safe
     * dedupe mechanism (not read-then-write). Returns false if this Stripe
     * event has already been processed (a retried delivery).
     */
    private function markProcessedOrSkip(array $payload): bool
    {
        try {
            StripeWebhookEvent::create([
                'stripe_event_id' => $payload['id'],
                'type' => $payload['type'],
            ]);

            return true;
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }
}
