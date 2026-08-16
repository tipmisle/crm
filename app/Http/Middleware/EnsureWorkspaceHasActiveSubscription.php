<?php

namespace App\Http\Middleware;

use App\Services\Billing\WorkspaceSubscriptionStateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gates the real product surface (Inbox, Customers, Orders, ...) behind an
 * active subscription. Demo workspaces bypass entirely, and so do platform
 * admins (Beležka staff, is_platform_admin=true) — the same deny-by-default,
 * CLI-only-grant flag already used for /admin access (see EnsurePlatformAdmin,
 * docs/admin-security.md), not a separate hidden bypass. See docs/billing.md
 * and routes/web.php for the exact gated/exempt route list.
 */
class EnsureWorkspaceHasActiveSubscription
{
    public function __construct(private WorkspaceSubscriptionStateService $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $workspace = $user?->currentWorkspace;

        abort_unless($workspace, 403);

        if ($workspace->is_demo || $user->isPlatformAdmin()) {
            return $next($request);
        }

        if ($this->state->grantsAccess($workspace)) {
            return $next($request);
        }

        return redirect()->route('billing.activate');
    }
}
