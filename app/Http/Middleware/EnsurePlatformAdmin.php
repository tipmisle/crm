<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Deny-by-default gate for the entire /admin namespace. Workspace roles
 * (owner/member) never grant this — only the explicit is_platform_admin
 * flag does. Applies to every /admin route; there is no UI-only hiding.
 */
class EnsurePlatformAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(403);
        }

        if (! $user->isActive() || ! $user->isPlatformAdmin()) {
            AuditLog::record('admin.access_denied', $request, null, null, [
                'path' => $request->path(),
            ], $user);

            abort(403);
        }

        // MFA is required before any /admin access — a platform admin
        // without confirmed 2FA is sent to set it up rather than silently
        // let in. Never abort() here: this is a routine, expected
        // first-time state for a freshly granted admin, not a security
        // violation to audit-log like the branch above.
        if (! $user->hasEnabledTwoFactorAuthentication()) {
            return redirect()->route('profile.edit')
                ->with('error', 'Za dostop do admin panela je potrebno najprej omogočiti dvostopenjsko preverjanje.');
        }

        return $next($request);
    }
}
