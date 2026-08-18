<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\WorkspaceSubscriptionStateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ActivationController extends Controller
{
    public function edit(Request $request, WorkspaceSubscriptionStateService $state): Response|RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;

        if ($state->grantsAccess($workspace)) {
            return redirect()->route($this->postActivationRoute($workspace));
        }

        return Inertia::render('Billing/Activate', [
            'isOwner' => WorkspaceMember::isOwnerOf($request->user(), $workspace->id),
            'displayPrice' => config('billing.display_price'),
            'billingPeriodLabel' => config('billing.billing_period_label'),
            'displayPriceVatIncluded' => config('billing.display_price_vat_included'),
            'state' => $state->for($workspace)->value,
        ]);
    }

    public function checkout(Request $request, WorkspaceSubscriptionStateService $state): SymfonyResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        abort_if($workspace->is_demo, 404);
        abort_unless(WorkspaceMember::isOwnerOf($user, $workspace->id), 403, 'Samo lastnik delovnega prostora lahko aktivira naročnino.');

        if ($state->grantsAccess($workspace)) {
            return redirect()->route('settings.billing.edit');
        }

        abort_unless(config('billing.monthly_price_id'), 500, 'Naročnina ni konfigurirana.');

        if (config('app.env') === 'production') {
            abort_unless(
                filled(config('billing.display_price')) && config('billing.display_price_vat_included') !== null,
                500,
                'Cena naročnine ali njena DDV obravnava ni objavljena.'
            );
        }

        AuditLog::record('billing.checkout_started', $request, $workspace->id, $workspace);

        $checkout = $workspace->newSubscription(config('billing.subscription_name'), config('billing.monthly_price_id'))
            ->checkout([
                'success_url' => route('billing.activate.success'),
                'cancel_url' => route('billing.activate'),
                // Stripe accounts default new Checkout Sessions into Managed
                // Payments, a hosted-billing feature this app doesn't use —
                // it requires a newer API version than Cashier is pinned to
                // and would break checkout for every account where it's
                // enabled by default. Opt out explicitly rather than relying
                // on a per-account dashboard setting.
                'managed_payments' => ['enabled' => false],
            ]);

        // Stripe Checkout is an external, cross-origin destination — a plain
        // redirect would have Inertia try to follow it via XHR and get
        // blocked by CORS. Inertia::location() forces a full-page visit
        // instead.
        return Inertia::location($checkout->url);
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

        return redirect()->route($this->postActivationRoute($workspace))
            ->with('success', 'Plačilo se obdeluje — Beležka bo na voljo v nekaj trenutkih.');
    }

    private function postActivationRoute(Workspace $workspace): string
    {
        return $workspace->needsOnboarding() ? 'onboarding.show' : 'dashboard';
    }
}
