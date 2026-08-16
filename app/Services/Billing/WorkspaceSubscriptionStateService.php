<?php

namespace App\Services\Billing;

use App\Enums\SubscriptionAccessState;
use App\Models\Workspace;

/**
 * The single centralized place that maps a workspace's Cashier/Stripe
 * subscription to Beležka's access state — every consumer (the gating
 * middleware, the shared Inertia prop, Settings/Billing, the Admin view)
 * calls this rather than scattering `if ($workspace->subscription(...))`
 * checks. See docs/billing.md.
 */
class WorkspaceSubscriptionStateService
{
    public function for(Workspace $workspace): SubscriptionAccessState
    {
        $subscription = $workspace->subscription(config('billing.subscription_name'));

        if (! $subscription) {
            return SubscriptionAccessState::NoSubscription;
        }

        if ($subscription->onGracePeriod()) {
            // cancel_at_period_end scheduled, still within the paid period.
            return SubscriptionAccessState::Canceling;
        }

        if ($subscription->active()) {
            return SubscriptionAccessState::Active;
        }

        if ($subscription->pastDue()) {
            return SubscriptionAccessState::PastDue;
        }

        if ($subscription->incomplete()) {
            return SubscriptionAccessState::Incomplete;
        }

        if ($subscription->ended() || $subscription->canceled()) {
            return SubscriptionAccessState::Canceled;
        }

        return SubscriptionAccessState::NoSubscription;
    }

    /**
     * Whether the workspace should have normal product access right now.
     * Demo workspaces are handled by the caller (EnsureWorkspaceHasActiveSubscription),
     * not here — this service only ever reasons about real, billable workspaces.
     */
    public function grantsAccess(Workspace $workspace): bool
    {
        return match ($this->for($workspace)) {
            SubscriptionAccessState::Active, SubscriptionAccessState::Canceling => true,
            SubscriptionAccessState::PastDue => config('billing.past_due_access_policy') === 'grace',
            default => false,
        };
    }
}
