<?php

namespace App\Services;

use App\Models\InvoiceSettings;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Concerns\CollectsLocalAttachmentPaths;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * The single place that knows how to safely delete a demo workspace —
 * used by both the scheduled `demos:cleanup` command and
 * Admin\WorkspaceController::destroyDemo (manual deletion), so the two
 * paths can never drift apart on what "cleaning up a demo" means.
 *
 * Requires is_demo === true server-side (never trusts a caller's own
 * check), deletes only demo users linked to that workspace (never a real
 * user, even if somehow referenced), keeps the DB deletion transactional,
 * and deletes private attachment files only after that transaction commits
 * successfully (best-effort, non-fatal).
 */
class DemoWorkspaceCleanupService
{
    use CollectsLocalAttachmentPaths;

    /**
     * @throws \InvalidArgumentException if $workspace is not a demo workspace
     */
    public function delete(Workspace $workspace): void
    {
        if (! $workspace->is_demo) {
            throw new \InvalidArgumentException("Workspace {$workspace->id} is not a demo workspace — refusing to delete.");
        }

        // Collected before the transaction — file deletion isn't
        // transactional, so we only want to act on it once the DB rows are
        // confirmed gone, not before.
        $attachmentPaths = $this->localAttachmentPaths($workspace->id);
        $invoicePdfPaths = $this->salesDocumentPdfPaths($workspace->id);
        $invoiceLogoPath = InvoiceSettings::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->value('logo_path');

        DB::transaction(function () use ($workspace) {
            // Capture the demo user ids before deleting the workspace —
            // workspace_members cascades away with it, so the linkage
            // would otherwise be lost.
            $userIds = WorkspaceMember::where('workspace_id', $workspace->id)
                ->pluck('user_id');

            // Cascades Channels, Customers (+identities), Conversations
            // (+Messages), Orders, Appointments, CatalogItems, FollowUps,
            // ActivityLogs, Integrations, WorkspaceMembers, InvoiceSettings,
            // SalesDocuments.
            $workspace->delete();

            // Never touches a real (non-demo) user, even if somehow
            // referenced — is_demo is checked explicitly.
            User::whereIn('id', $userIds)->where('is_demo', true)->delete();
        });

        $this->deleteAttachmentFiles($attachmentPaths, 'demos.cleanup');
        $this->deleteAttachmentFiles($invoicePdfPaths, 'demos.cleanup');

        if ($invoiceLogoPath) {
            Storage::disk('public')->delete($invoiceLogoPath);
        }
    }
}
