<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IntegrationProvider;
use App\Http\Controllers\Controller;
use App\Models\Integration;
use App\Models\User;
use App\Models\Workspace;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'real_workspaces' => Workspace::where('is_demo', false)->count(),
                'demo_workspaces_active' => Workspace::where('is_demo', true)->where('demo_expires_at', '>', now())->count(),
                'demo_workspaces_awaiting_cleanup' => Workspace::where('is_demo', true)->where('demo_expires_at', '<=', now())->count(),
                'total_users' => User::count(),
                // whereHas('channels', ...) builds its EXISTS subquery from
                // Channel's own query builder, which still carries
                // Channel's BelongsToWorkspace global scope — for a
                // platform admin (no current_workspace_id) that scope
                // matches nothing at all (see BelongsToWorkspace), so
                // without withoutGlobalScopes() here every count below
                // would silently come back as 0 regardless of real data.
                'instagram_connected' => Integration::withoutGlobalScopes()->where('provider', IntegrationProvider::Meta->value)->where('status', 'connected')->whereHas('channels', fn ($q) => $q->withoutGlobalScopes()->where('type', 'instagram')->where('status', 'connected'))->count(),
                'facebook_connected' => Integration::withoutGlobalScopes()->where('provider', IntegrationProvider::Meta->value)->where('status', 'connected')->whereHas('channels', fn ($q) => $q->withoutGlobalScopes()->where('type', 'facebook_messenger')->where('status', 'connected'))->count(),
                'integrations_in_error' => Integration::withoutGlobalScopes()->where('status', 'error')->count(),
            ],
            'recentlyFailedIntegrations' => Integration::withoutGlobalScopes()
                ->where('status', 'error')
                ->with('workspace:id,name,is_demo')
                ->orderByDesc('updated_at')
                ->limit(10)
                ->get(['id', 'workspace_id', 'provider', 'display_name', 'status', 'updated_at']),
            'newestRealWorkspaces' => Workspace::where('is_demo', false)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(['id', 'name', 'created_at']),
        ]);
    }
}
