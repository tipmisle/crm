<?php

namespace App\Console\Commands;

use App\Models\Workspace;
use App\Services\DemoWorkspaceCleanupService;
use Illuminate\Console\Command;
use Throwable;

class CleanupExpiredDemos extends Command
{
    protected $signature = 'demos:cleanup';

    protected $description = 'Delete expired ephemeral demo workspaces and their demo users.';

    public function handle(DemoWorkspaceCleanupService $cleanup): void
    {
        $expired = Workspace::where('is_demo', true)
            ->where('demo_expires_at', '<=', now())
            ->get();

        $deleted = 0;

        foreach ($expired as $workspace) {
            try {
                $cleanup->delete($workspace);

                $deleted++;
            } catch (Throwable $e) {
                $this->error("Failed to clean up demo workspace {$workspace->id}: {$e->getMessage()}");
            }
        }

        $this->info("Cleaned up {$deleted} expired demo workspace(s).");
    }
}
