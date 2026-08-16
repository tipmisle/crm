<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Workspace;
use App\Services\WorkspaceDeletionService;
use Illuminate\Console\Command;
use Throwable;

class PurgeExpiredWorkspaces extends Command
{
    protected $signature = 'workspaces:purge-expired';

    protected $description = 'Permanently delete real workspaces past their scheduled deletion date.';

    public function handle(WorkspaceDeletionService $service): void
    {
        // is_demo = false guards against ever touching a demo workspace,
        // even if scheduled_deletion_at were somehow set on one — demo
        // cleanup is a wholly separate system (demos:cleanup).
        $expired = Workspace::where('is_demo', false)
            ->whereNotNull('scheduled_deletion_at')
            ->where('scheduled_deletion_at', '<=', now())
            ->get();

        $deleted = 0;

        foreach ($expired as $workspace) {
            try {
                AuditLog::record('privacy.workspace.purge_attempted', null, $workspace->id, $workspace, [
                    'workspace_id' => $workspace->id,
                    'deletion_requested_at' => optional($workspace->deletion_requested_at)->toIso8601String(),
                ]);

                $service->delete($workspace);

                AuditLog::record('privacy.workspace.purged', null, null, null, [
                    'workspace_id' => $workspace->id,
                ]);

                $deleted++;
            } catch (Throwable $e) {
                $this->error("Failed to purge workspace {$workspace->id}: {$e->getMessage()}");
            }
        }

        $this->info("Purged {$deleted} expired workspace(s).");
    }
}
