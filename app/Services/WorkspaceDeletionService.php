<?php

namespace App\Services;

use App\Enums\ChannelType;
use App\Models\Workspace;
use App\Models\WorkspaceExport;
use App\Services\Concerns\CollectsLocalAttachmentPaths;
use App\Services\Messaging\MetaMessagingProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The single place that knows how to safely permanently delete a real
 * (non-demo) workspace — used by both workspaces:purge-expired and
 * ProfileController::destroy's sole-owner-of-a-solo-workspace cascade, so
 * the two paths can never drift apart. See docs/data-lifecycle.md.
 *
 * Requires is_demo === false server-side. Unlike DemoWorkspaceCleanupService,
 * this never deletes any User — a real user may belong to other
 * workspaces; users.current_workspace_id is nullOnDelete and the app
 * tolerates a null current workspace.
 */
class WorkspaceDeletionService
{
    use CollectsLocalAttachmentPaths;

    public function __construct(private MetaMessagingProvider $meta) {}

    /**
     * @throws \InvalidArgumentException if $workspace is a demo workspace
     */
    public function delete(Workspace $workspace): void
    {
        if ($workspace->is_demo) {
            throw new \InvalidArgumentException("Workspace {$workspace->id} is a demo workspace — use DemoWorkspaceCleanupService instead.");
        }

        // Best-effort webhook unsubscribe BEFORE anything is deleted — it
        // needs the channel row (incl. access_token), which cascade would
        // otherwise remove first. Never blocks deletion, never logs tokens.
        $this->unsubscribeChannelWebhooks($workspace);

        // Best-effort, immediate Stripe cancellation — the one place in the
        // whole system where cancelNow() (not the usual cancel-at-period-end)
        // is correct: the workspace and all its data are about to be
        // permanently destroyed regardless, so a lingering canceling-but-
        // still-billing subscription would silently keep charging for
        // nothing. Direction matters: purge cancels Stripe here; a Stripe
        // cancellation webhook must NEVER trigger workspace deletion. See
        // docs/billing.md.
        $this->cancelStripeSubscription($workspace);

        // Collected before the transaction — file deletion isn't
        // transactional, so we only act on it once the DB rows are
        // confirmed gone.
        $attachmentPaths = $this->localAttachmentPaths($workspace->id);
        $exportPaths = WorkspaceExport::where('workspace_id', $workspace->id)->pluck('disk_path')->all();

        DB::transaction(function () use ($workspace) {
            // Cascades Channels, Integrations, Customers (+identities),
            // Conversations (+Messages), Orders (+OrderNotes), Appointments,
            // CatalogItems, FollowUps, ActivityLogs, WorkspaceMembers,
            // SupportAccessGrants (+SupportSessions), WorkspaceExports.
            // Any remaining member's users.current_workspace_id is
            // nullOnDelete, not cascade — the app tolerates that null.
            $workspace->delete();
        });

        $this->deleteAttachmentFiles($attachmentPaths, 'workspace.purge');
        $this->deleteAttachmentFiles($exportPaths, 'workspace.purge.export');
    }

    private function cancelStripeSubscription(Workspace $workspace): void
    {
        if (! $workspace->stripe_id) {
            return;
        }

        try {
            $workspace->subscription(config('billing.subscription_name'))?->cancelNow();
        } catch (Throwable $e) {
            Log::warning('workspace.purge.cancel_subscription_failed', ['workspace_id' => $workspace->id]);
        }
    }

    private function unsubscribeChannelWebhooks(Workspace $workspace): void
    {
        $channels = $workspace->channels()
            ->whereIn('type', [ChannelType::Instagram->value, ChannelType::FacebookMessenger->value])
            ->get();

        foreach ($channels as $channel) {
            try {
                $this->meta->unsubscribeWebhooks($channel);
            } catch (Throwable $e) {
                Log::warning('workspace.purge.unsubscribe_webhooks_failed', ['channel_id' => $channel->id]);
            }
        }
    }
}
