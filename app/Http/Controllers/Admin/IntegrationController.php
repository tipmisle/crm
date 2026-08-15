<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Integration;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class IntegrationController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Integration::withoutGlobalScopes()->with('workspace:id,name,is_demo');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->value());
        }

        $integrations = $query->orderByDesc('updated_at')->paginate(25)->withQueryString()->through(fn (Integration $i) => [
            'id' => $i->id,
            'workspace' => $i->workspace,
            'provider' => $i->provider->value,
            'status' => $i->status,
            'display_name' => $i->display_name,
            'external_account_id' => $i->external_account_id,
            'connected_at' => $i->connected_at,
            'last_synced_at' => $i->last_synced_at,
            'token_expires_at' => $i->token_expires_at,
            'scopes' => $i->scopes,
        ]);

        return Inertia::render('Admin/Integrations/Index', [
            'integrations' => $integrations,
            'filters' => $request->only('status'),
        ]);
    }

    /**
     * Clears a stuck "error" status so the next sync attempt is treated as
     * fresh. Never touches tokens; never contacts the provider itself.
     */
    public function clearError(Request $request, int $integration)
    {
        $integration = Integration::withoutGlobalScopes()->findOrFail($integration);

        abort_unless($integration->status === 'error', 422, 'Integracija ni v napaki.');

        $integration->update(['status' => 'connected']);

        AuditLog::record('admin.integration.changed', $request, $integration->workspace_id, $integration, [
            'action' => 'clear_error',
        ]);

        return back()->with('success', 'Napaka integracije je bila počiščena.');
    }
}
