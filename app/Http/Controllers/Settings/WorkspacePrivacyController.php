<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WorkspaceMember;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspacePrivacyController extends Controller
{
    public function edit(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        return Inertia::render('Settings/Privacy', [
            'workspace' => [
                'id' => $workspace->id,
                'name' => $workspace->name,
                'deletion_requested_at' => $workspace->deletion_requested_at,
                'scheduled_deletion_at' => $workspace->scheduled_deletion_at,
            ],
            'isOwner' => WorkspaceMember::isOwnerOf($request->user(), $workspace->id),
            'hasActiveSubscription' => $workspace->subscribed(config('billing.subscription_name')),
            'gracePeriodPreviewDate' => now()->addDays((int) config('retention.workspace_grace_days')),
            'exportHours' => (int) config('retention.export_hours'),
            'exports' => $workspace->workspaceExports()
                ->latest('created_at')
                ->limit(10)
                ->get(['id', 'status', 'expires_at', 'downloaded_at', 'created_at']),
        ]);
    }

    public function requestDeletion(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless(WorkspaceMember::isOwnerOf($user, $workspace->id), 403, 'Samo lastnik delovnega prostora lahko zahteva izbris.');

        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $scheduledAt = now()->addDays((int) config('retention.workspace_grace_days'));

        $workspace->update([
            'deletion_requested_at' => now(),
            'scheduled_deletion_at' => $scheduledAt,
        ]);

        AuditLog::record('privacy.workspace.deletion_requested', $request, $workspace->id, $workspace, [
            'scheduled_deletion_at' => $scheduledAt->toIso8601String(),
        ]);

        return back()->with('success', 'Izbris delovnega prostora je bil naročen. Podatke lahko obnoviš do '.$scheduledAt->format('d.m.Y').'.');
    }

    public function cancelDeletion(Request $request): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless(WorkspaceMember::isOwnerOf($user, $workspace->id), 403);
        abort_unless($workspace->isPendingDeletion(), 422, 'Izbris tega delovnega prostora ni bil naročen.');

        $workspace->update([
            'deletion_requested_at' => null,
            'scheduled_deletion_at' => null,
        ]);

        AuditLog::record('privacy.workspace.deletion_cancelled', $request, $workspace->id, $workspace);

        return back()->with('success', 'Izbris delovnega prostora je bil preklican.');
    }
}
