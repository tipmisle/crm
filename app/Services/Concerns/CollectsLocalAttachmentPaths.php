<?php

namespace App\Services\Concerns;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Shared by every service that permanently deletes a workspace (demo or
 * real) — collects local attachment paths before the DB transaction (so
 * file deletion only happens once the rows are confirmed gone) and deletes
 * them afterwards, best-effort. Kept as one implementation so the demo and
 * real-workspace deletion paths can never drift on what "attachment
 * cleanup" means.
 */
trait CollectsLocalAttachmentPaths
{
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

    /**
     * @param  array<int, string>  $paths
     */
    private function deleteAttachmentFiles(array $paths, string $logChannel): void
    {
        foreach ($paths as $path) {
            try {
                Storage::disk('local')->delete($path);
            } catch (Throwable $e) {
                // Never fatal — the DB rows are already gone, an orphaned
                // file is a cleanup nuisance, not a data-integrity problem.
                Log::warning("{$logChannel}.attachment_delete_failed", ['path_hash' => md5($path)]);
            }
        }
    }
}
