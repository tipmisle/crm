<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Demo visitors must never be able to configure durable account-security
 * state (enabling/disabling 2FA, regenerating recovery codes) — a demo
 * account is shared/reset infrastructure, not a real user's own account,
 * and letting a demo session lock in a TOTP secret would be meaningless
 * (the account gets purged) and a confusing support burden. Read-only
 * access (viewing the QR/secret/recovery codes mid-setup) is harmless and
 * left alone.
 *
 * Registered globally (bootstrap/app.php web group) rather than attached
 * to Fortify's own named routes directly — Fortify's package provider
 * registers those routes inside its own boot(), and reliably attaching
 * middleware to an already-registered route object from a later-booting
 * application provider proved to be boot-order-fragile. Checking the
 * resolved route's name at request time sidesteps that entirely and costs
 * one string comparison on every request.
 */
class DenyDemoTwoFactorMutation
{
    private const GUARDED_ROUTE_NAMES = [
        'two-factor.enable',
        'two-factor.disable',
        'two-factor.confirm',
        'two-factor.regenerate-recovery-codes',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->is_demo && in_array($request->route()?->getName(), self::GUARDED_ROUTE_NAMES, true)) {
            abort(403, 'Demo računi ne morejo nastavljati dvostopenjskega preverjanja.');
        }

        return $next($request);
    }
}
