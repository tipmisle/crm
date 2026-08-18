<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceMember;
use App\Services\Billing\WorkspaceSubscriptionStateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Owner-only billing status + Stripe Customer Portal redirect. Cancel and
 * resume are deliberately Portal-only, not duplicated as in-app actions —
 * the Stripe webhook handler is the single place that records the
 * corresponding audit events, regardless of whether the owner acted here
 * or in the Portal. See docs/billing.md.
 */
class BillingController extends Controller
{
    public function edit(Request $request, WorkspaceSubscriptionStateService $stateService): Response
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;
        $subscription = $workspace->subscription(config('billing.subscription_name'));
        $state = $stateService->for($workspace);

        return Inertia::render('Settings/Billing', [
            'isOwner' => WorkspaceMember::isOwnerOf($user, $workspace->id),
            'state' => $state->value,
            'stateLabel' => $state->label(),
            'displayPrice' => config('billing.display_price'),
            'billingPeriodLabel' => config('billing.billing_period_label'),
            // ends_at is locally cached by Cashier's cancel() — cheap to
            // read, no live Stripe API call needed on a normal page view.
            // Only meaningful while cancel_at_period_end is scheduled.
            'periodEndsAt' => $subscription?->onGracePeriod() ? $subscription->ends_at : null,
            'cancelAtPeriodEnd' => $subscription?->onGracePeriod() ?? false,
            'pmType' => $workspace->pm_type,
            'pmLastFour' => $workspace->pm_last_four,
            'invoices' => $workspace->invoices()->map(fn ($invoice) => [
                'id' => $invoice->id,
                'date' => $invoice->date()->toDateString(),
                'total' => $invoice->total(),
                'paid' => (bool) $invoice->paid,
                'pdfUrl' => $invoice->invoice_pdf,
            ])->values(),
        ]);
    }

    public function portal(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless(WorkspaceMember::isOwnerOf($user, $workspace->id), 403, 'Samo lastnik delovnega prostora lahko upravlja naročnino.');
        abort_unless($workspace->stripe_id, 404);

        return $workspace->redirectToBillingPortal(route('settings.billing.edit'));
    }
}
