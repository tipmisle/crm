<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
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

                $deleted++;
            } catch (Throwable $e) {
                $this->error("Failed to clean up demo workspace {$workspace->id}: {$e->getMessage()}");
            }
        }

        $this->info("Cleaned up {$deleted} expired demo workspace(s).");
    }
}
