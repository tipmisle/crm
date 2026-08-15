<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CleanupExpiredDemos extends Command
{
    protected $signature = 'demos:cleanup';

    protected $description = 'Delete expired ephemeral demo workspaces and their demo users.';

    public function handle(): void
    {
        $expired = Workspace::where('is_demo', true)
            ->where('demo_expires_at', '<=', now())
            ->get();

        $deleted = 0;

        foreach ($expired as $workspace) {
            // Collected before the transaction — file deletion isn't
            // transactional, so we only want to act on it once the DB rows
            // are confirmed gone, not before.
            $attachmentPaths = $this->localAttachmentPaths($workspace->id);

            try {
                DB::transaction(function () use ($workspace) {
                    // Capture the demo user ids before deleting the workspace —
                    // workspace_members cascades away with it, so the linkage
                    // would otherwise be lost.
                    $userIds = WorkspaceMember::where('workspace_id', $workspace->id)
                        ->pluck('user_id');

                    // Cascades Channels, Customers (+identities), Conversations
                    // (+Messages), Orders, Appointments, CatalogItems,
                    // FollowUps, ActivityLogs, Integrations, WorkspaceMembers.
                    $workspace->delete();

                    // Never touches a real (non-demo) user, even if somehow
                    // referenced — is_demo is checked explicitly.
                    User::whereIn('id', $userIds)->where('is_demo', true)->delete();
                });

                $this->deleteAttachmentFiles($attachmentPaths);

                $deleted++;
            } catch (Throwable $e) {
                $this->error("Failed to clean up demo workspace {$workspace->id}: {$e->getMessage()}");
            }
        }

        $this->info("Cleaned up {$deleted} expired demo workspace(s).");
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
