<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bounces an incomplete (non-demo) workspace to /onboarding from the rest
 * of the product surface, so a fresh workspace never lands in an empty app
 * without being shown what to configure first. Sits alongside
 * subscription.active in routes/web.php, so it only ever runs after a
 * workspace already has product access — it never blocks billing, settings,
 * or privacy routes (those live outside the subscription.active group).
 * The onboarding page itself and the Meta OAuth round-trip are exempt so a
 * user can actually finish/skip step C without getting redirected in a loop.
 */
class EnsureOnboardingComplete
{
    private const EXEMPT_ROUTES = [
        'onboarding.show',
        'onboarding.complete',
        'integrations.meta.connect',
        'integrations.meta.callback',
        'integrations.meta.store',
        'integrations.meta.cancel',
        'integrations.meta.disconnect',
        // Onboarding step 2 links here so the user can add their first
        // product/service before finishing onboarding; capability gates
        // (orders.enabled/appointments.enabled) still apply behind these.
        'catalog.index',
        'products.store',
        'services.store',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $workspace = $request->user()?->currentWorkspace;

        if (! $workspace || ! $workspace->needsOnboarding()) {
            return $next($request);
        }

        if (in_array($request->route()?->getName(), self::EXEMPT_ROUTES, true)) {
            return $next($request);
        }

        return redirect()->route('onboarding.show');
    }
}
