<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Integration;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\Billing\WorkspaceSubscriptionStateService;
use App\Services\DemoWorkspaceCleanupService;
use App\Support\SupportSessionManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkspaceController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Workspace::query()->withCount('members');

        if ($search = $request->string('q')->trim()->value()) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('id', $search)
                    ->orWhereHas('users', fn ($u) => $u->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
            });
        }

        if ($request->filled('type')) {
            $query->where('is_demo', $request->string('type')->value() === 'demo');
        }

        $workspaces = $query->orderByDesc('created_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (Workspace $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'is_demo' => $w->is_demo,
                'demo_expires_at' => $w->demo_expires_at,
                'members_count' => $w->members_count,
                'created_at' => $w->created_at,
            ]);

        return Inertia::render('Admin/Workspaces/Index', [
            'workspaces' => $workspaces,
            'filters' => $request->only(['q', 'type']),
        ]);
    }

    public function show(Request $request, Workspace $workspace): Response
    {
        AuditLog::record('admin.workspace.view', $request, $workspace->id, $workspace);

        $withoutScope = fn (string $model) => $model::withoutGlobalScopes()->where('workspace_id', $workspace->id);

        $grant = $workspace->currentSupportAccessGrant();

        $subscription = $workspace->subscription(config('billing.subscription_name'));
        $billingState = app(WorkspaceSubscriptionStateService::class);

        return Inertia::render('Admin/Workspaces/Show', [
            'workspace' => $workspace,
            'owner' => $workspace->users()->wherePivot('role', 'owner')->first(['users.id', 'users.name', 'users.email']),
            'usage' => [
                'members' => $withoutScope(WorkspaceMember::class)->count(),
                'customers' => $withoutScope(Customer::class)->count(),
                'conversations' => $withoutScope(Conversation::class)->count(),
                'messages' => Message::whereIn('conversation_id', $withoutScope(Conversation::class)->pluck('id'))->count(),
                'orders' => $withoutScope(Order::class)->count(),
                'appointments' => $withoutScope(Appointment::class)->count(),
                'products' => $withoutScope(Product::class)->count(),
                'services' => $withoutScope(Service::class)->count(),
                'follow_ups' => $withoutScope(FollowUp::class)->count(),
            ],
            'integrations' => Integration::withoutGlobalScopes()
                ->where('workspace_id', $workspace->id)
                ->get()
                ->map(fn (Integration $i) => $this->integrationSummary($i)),
            'supportAccess' => $grant ? [
                'expires_at' => $grant->expires_at,
                'granted_by' => $grant->grantedBy?->name,
            ] : null,
            // Read-only. No card details, no manual "mark as paid" — Stripe/
            // webhook state stays authoritative. See docs/billing.md.
            'billing' => $workspace->is_demo ? null : [
                'status' => $billingState->for($workspace)->value,
                'status_label' => $billingState->for($workspace)->label(),
                'stripe_customer_id' => $workspace->stripe_id,
                'stripe_subscription_id' => $subscription?->stripe_id,
                'cancel_at_period_end' => $subscription?->onGracePeriod() ?? false,
                'ends_at' => $subscription?->onGracePeriod() ? $subscription->ends_at : null,
                'has_payment_problem' => $subscription?->pastDue() ?? false,
            ],
        ]);
    }

    public function startSupportSession(Request $request, Workspace $workspace, SupportSessionManager $manager)
    {
        $manager->start($request->user(), $workspace, $request);

        return redirect()->route('admin.workspaces.support.browse', $workspace)
            ->with('success', 'Način podpore je aktiven.');
    }

    public function endSupportSession(Request $request, SupportSessionManager $manager)
    {
        $manager->end($request, 'left');

        return redirect()->route('admin.workspaces.index')->with('success', 'Zapustil si način podpore.');
    }

    public function destroyDemo(Request $request, Workspace $workspace, DemoWorkspaceCleanupService $cleanup)
    {
        // Server-side guard is the actual boundary — never trust that the
        // UI only offers this action for demo workspaces. The cleanup
        // service also re-checks is_demo itself before deleting anything.
        abort_unless($workspace->is_demo === true, 422, 'Samo demo delovne prostore je mogoče izbrisati na ta način.');

        AuditLog::record('admin.workspace.changed', $request, $workspace->id, $workspace, [
            'action' => 'delete_demo_workspace',
        ]);

        $cleanup->delete($workspace);

        return redirect()->route('admin.workspaces.index')->with('success', 'Demo delovni prostor je bil izbrisan.');
    }

    private function integrationSummary(Integration $integration): array
    {
        return [
            'id' => $integration->id,
            'provider' => $integration->provider->value,
            'status' => $integration->status,
            'display_name' => $integration->display_name,
            'external_account_id' => $integration->external_account_id,
            'connected_at' => $integration->connected_at,
            'last_synced_at' => $integration->last_synced_at,
            'token_expires_at' => $integration->token_expires_at,
            'scopes' => $integration->scopes,
            // access_token / refresh_token intentionally never included.
        ];
    }
}
