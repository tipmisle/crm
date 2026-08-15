<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

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

        DB::transaction(function () use ($workspace) {
            // Capture the demo user ids before deleting the workspace —
            // workspace_members cascades away with it, so the linkage
            // would otherwise be lost.
            $userIds = WorkspaceMember::where('workspace_id', $workspace->id)
                ->pluck('user_id');

            // Cascades Channels, Customers (+identities), Conversations
            // (+Messages), Orders, Appointments, CatalogItems, FollowUps,
            // ActivityLogs, Integrations, WorkspaceMembers.
            $workspace->delete();

            // Never touches a real (non-demo) user, even if somehow
            // referenced — is_demo is checked explicitly.
            User::whereIn('id', $userIds)->where('is_demo', true)->delete();
        });

        $this->deleteAttachmentFiles($attachmentPaths);
    }

    /**
     * @return array<int, string>
     */
    private function localAttachmentPaths(int $workspaceId): array
    {
        $conversationIds = Conversation::withoutGlobalScopes()
            ->where('workspace_id', $workspaceId)
            ->pluck('id');

        return Message::whereIn('conversation_id', $conversationIds)
            ->whereNotNull('metadata')
            ->get(['metadata'])
            ->flatMap(fn (Message $message) => $message->metadata['attachments'] ?? [])
            ->filter(fn (array $attachment) => ($attachment['source'] ?? null) === 'local' && ! empty($attachment['path']))
            ->pluck('path')
            ->all();
    }

    private function deleteAttachmentFiles(array $paths): void
    {
        foreach ($paths as $path) {
            try {
                Storage::disk('local')->delete($path);
            } catch (Throwable $e) {
                // Never fatal — the DB rows are already gone, an orphaned
                // file is a cleanup nuisance, not a data-integrity problem.
                Log::warning('demos.cleanup.attachment_delete_failed', ['path_hash' => md5($path)]);
            }
        }
    }
}
