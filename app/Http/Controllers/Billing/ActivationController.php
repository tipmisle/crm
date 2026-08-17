<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WorkspaceMember;
use App\Services\Billing\WorkspaceSubscriptionStateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivationController extends Controller
{
    public function edit(Request $request, WorkspaceSubscriptionStateService $state): Response
    {
        $workspace = $request->user()->currentWorkspace;

        return Inertia::render('Billing/Activate', [
            'isOwner' => WorkspaceMember::isOwnerOf($request->user(), $workspace->id),
            'displayPrice' => config('billing.display_price'),
            'billingPeriodLabel' => config('billing.billing_period_label'),
            'displayPriceVatIncluded' => config('billing.display_price_vat_included'),
            'state' => $state->for($workspace)->value,
        ]);
    }

    public function checkout(Request $request, WorkspaceSubscriptionStateService $state): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        abort_if($workspace->is_demo, 404);
        abort_unless(WorkspaceMember::isOwnerOf($user, $workspace->id), 403, 'Samo lastnik delovnega prostora lahko aktivira naročnino.');

        if ($state->grantsAccess($workspace)) {
            return redirect()->route('settings.billing.edit');
        }

        abort_unless(config('billing.monthly_price_id'), 500, 'Naročnina ni konfigurirana.');

        AuditLog::record('billing.checkout_started', $request, $workspace->id, $workspace);

        $checkout = $workspace->newSubscription(config('billing.subscription_name'), config('billing.monthly_price_id'))
            ->checkout([
                'success_url' => route('billing.activate.success'),
                'cancel_url' => route('billing.activate'),
            ]);

        return $checkout->redirect();
    }

    /**
     * Stripe Checkout success redirect target. Does NOT itself grant
     * access — access depends only on the server-side subscription state
     * synchronized via webhook. If the webhook hasn't landed yet, the
     * subscription.active gate simply bounces the user back here once
     * more. See docs/billing.md.
     *
     * A brand new (non-demo) workspace hasn't finished onboarding yet, so
     * it's routed to /onboarding instead of straight to Today — see
     * EnsureOnboardingComplete.
     */
    public function success(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        $target = $workspace->needsOnboarding() ? 'onboarding.show' : 'dashboard';

        return redirect()->route($target)->with('success', 'Plačilo se obdeluje — Beležka bo na voljo v nekaj trenutkih.');
    }
}
