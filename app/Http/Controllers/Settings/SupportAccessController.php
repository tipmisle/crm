<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SupportAccessScope;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SupportAccessGrant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupportAccessController extends Controller
{
    private const ALLOWED_MINUTES = [30, 60, 240];

    public function edit(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        return Inertia::render('Settings/Support', [
            'currentGrant' => $this->activeGrant($workspace->id),
            'history' => $workspace->supportAccessGrants()
                ->with('grantedBy:id,name')
                ->latest('created_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($this->isOwner($user, $workspace->id), 403, 'Samo lastnik delovnega prostora lahko dovoli dostop podpori.');

        $data = $request->validate([
            'duration_minutes' => ['required', 'integer', Rule::in(self::ALLOWED_MINUTES)],
            'scope' => ['required', Rule::enum(SupportAccessScope::class)],
        ]);

        // A new grant supersedes any prior one for this workspace — never
        // stack grants, and never let scope quietly widen without a fresh
        // explicit choice.
        $workspace->supportAccessGrants()->active()->update(['revoked_at' => now()]);

        $grant = SupportAccessGrant::create([
            'workspace_id' => $workspace->id,
            'granted_by_user_id' => $user->id,
            'granted_at' => now(),
            'expires_at' => now()->addMinutes($data['duration_minutes']),
            'scope' => $data['scope'],
        ]);

        AuditLog::record('support_access.granted', $request, $workspace->id, $grant, [
            'scope' => $grant->scope->value,
            'duration_minutes' => $data['duration_minutes'],
        ]);

        return back()->with('success', 'Dostop za podporo je bil odobren.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless($this->isOwner($user, $workspace->id), 403);

        $grant = $this->activeGrant($workspace->id, asModel: true);

        if ($grant) {
            $grant->update(['revoked_at' => now()]);

            AuditLog::record('support_access.revoked', $request, $workspace->id, $grant);
        }

        return back()->with('success', 'Dostop za podporo je bil preklican.');
    }

    private function isOwner($user, int $workspaceId): bool
    {
        return $user->workspaces()->wherePivot('role', 'owner')->where('workspaces.id', $workspaceId)->exists();
    }

    private function activeGrant(int $workspaceId, bool $asModel = false)
    {
        $grant = SupportAccessGrant::where('workspace_id', $workspaceId)->active()->latest('expires_at')->first();

        if (! $grant) {
            return null;
        }

        return $asModel ? $grant : [
            'id' => $grant->id,
            'scope' => $grant->scope->value,
            'scope_label' => $grant->scope->label(),
            'expires_at' => $grant->expires_at,
        ];
    }
}
