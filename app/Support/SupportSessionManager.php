<?php

namespace App\Support;

use App\Enums\SupportAccessScope;
use App\Models\AuditLog;
use App\Models\SupportAccessGrant;
use App\Models\SupportSession;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;

/**
 * Owns the lifecycle of temporary platform-admin "support mode" access to a
 * customer workspace. A support session is only ever valid while its
 * backing SupportAccessGrant is still active — every read here re-checks
 * the database, never trusts session/cookie state alone. See
 * docs/admin-security.md for the full model.
 */
class SupportSessionManager
{
    private const SESSION_KEY = 'active_support_session_id';

    public function start(User $admin, Workspace $workspace, Request $request): SupportSession
    {
        $grant = $workspace->currentSupportAccessGrant();

        abort_if(! $grant, 403, 'Podpora nima dostopa do vsebine tega delovnega prostora.');

        $session = SupportSession::create([
            'support_access_grant_id' => $grant->id,
            'workspace_id' => $workspace->id,
            'admin_user_id' => $admin->id,
            'scope' => $grant->scope,
            'started_at' => now(),
            'expires_at' => $grant->expires_at,
        ]);

        $request->session()->put(self::SESSION_KEY, $session->id);

        AuditLog::record('support_session.started', $request, $workspace->id, $session, [
            'grant_id' => $grant->id,
            'scope' => $grant->scope->value,
        ]);

        return $session;
    }

    public function end(Request $request, string $reason = 'left'): void
    {
        $sessionId = $request->session()->get(self::SESSION_KEY);
        $session = $sessionId ? SupportSession::find($sessionId) : null;

        $request->session()->forget(self::SESSION_KEY);

        if (! $session || $session->ended_at !== null) {
            return;
        }

        $this->close($request, $session, $reason);
    }

    /**
     * Returns the active session for this request only if it is still
     * valid: not manually ended, not past its own expiry, and its backing
     * grant has not since been revoked or expired. Any failure here is
     * treated as "no session" — callers must not fall back to any other
     * source of workspace access.
     */
    public function current(Request $request): ?SupportSession
    {
        $sessionId = $request->session()->get(self::SESSION_KEY);

        if (! $sessionId) {
            return null;
        }

        $session = SupportSession::with('grant')->find($sessionId);

        if (! $session || $session->ended_at !== null) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        if ($session->expires_at->isPast()) {
            $this->close($request, $session, 'expired');

            return null;
        }

        $grant = $session->grant;

        if (! $grant || ! $grant->isValid()) {
            $this->close($request, $session, 'grant_revoked');

            return null;
        }

        return $session;
    }

    /**
     * Marks a specific, already-loaded session as ended. Never re-resolves
     * "the current session" from the request — that's what caused a
     * current()/end() mutual-recursion bug during development and is worth
     * guarding against regressing.
     */
    private function close(Request $request, SupportSession $session, string $reason): void
    {
        $session->update(['ended_at' => now(), 'ended_reason' => $reason]);

        $event = $reason === 'expired' ? 'support_session.expired' : 'support_session.ended';

        AuditLog::record($event, $request, $session->workspace_id, $session);

        $request->session()->forget(self::SESSION_KEY);
    }

    /**
     * Verifies the current support session grants access to $workspace
     * under (at least) $requiredScope. Aborts with 403 otherwise.
     */
    public function require(Request $request, Workspace $workspace, SupportAccessScope $requiredScope): SupportSession
    {
        $session = $this->current($request);

        abort_if(! $session, 403, 'Ni aktivne seje podpore.');
        abort_if($session->workspace_id !== $workspace->id, 403, 'Seja podpore ne velja za ta delovni prostor.');

        $hasScope = $session->scope === $requiredScope
            || $session->scope === SupportAccessScope::WorkspaceContent;

        abort_if(! $hasScope, 403, 'Seja podpore nima zadostnega obsega dostopa.');

        return $session;
    }
}
