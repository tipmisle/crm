<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\WorkspaceExport;
use App\Models\WorkspaceMember;
use App\Services\ExportWorkspaceDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkspaceExportController extends Controller
{
    public function store(Request $request, ExportWorkspaceDataService $service): RedirectResponse
    {
        $user = $request->user();
        $workspace = $user->currentWorkspace;

        abort_unless(WorkspaceMember::isOwnerOf($user, $workspace->id), 403, 'Samo lastnik delovnega prostora lahko izvozi podatke.');

        $export = $service->build($workspace, $user);

        AuditLog::record('privacy.workspace.export_requested', $request, $workspace->id, $export);

        return back()->with('success', 'Izvoz podatkov je pripravljen za prenos.');
    }

    public function download(Request $request, WorkspaceExport $export): StreamedResponse
    {
        $user = $request->user();

        abort_unless(WorkspaceMember::isOwnerOf($user, $export->workspace_id), 403);
        abort_if(! $export->isDownloadable(), 410, 'Izvoz je potekel ali je bil že prenesen.');

        $response = Storage::disk('local')->response($export->disk_path, 'delovni-prostor-izvoz.zip');

        $export->update(['downloaded_at' => now()]);

        AuditLog::record('privacy.workspace.export_downloaded', $request, $export->workspace_id, $export);

        return $response;
    }
}
