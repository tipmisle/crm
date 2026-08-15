<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SupportAccessScope;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Workspace;
use App\Support\SupportSessionManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Every action here requires an ACTIVE support session with the
 * workspace_content scope (see SupportSessionManager::require) — there is
 * no other path in the admin area to customer content. Each view records a
 * single support.content_access audit row per resource, never per message.
 */
class SupportContentController extends Controller
{
    public function conversation(Request $request, Workspace $workspace, int $conversation, SupportSessionManager $manager): Response
    {
        $manager->require($request, $workspace, SupportAccessScope::WorkspaceContent);

        $conversation = Conversation::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($conversation);

        AuditLog::record('support.content_access', $request, $workspace->id, $conversation, [
            'resource' => 'conversation',
        ]);

        $conversation->load(['customer', 'channel', 'messages' => fn ($q) => $q->orderBy('sent_at')]);

        return Inertia::render('Admin/Support/Conversation', [
            'workspace' => $workspace->only(['id', 'name']),
            'conversation' => $conversation,
        ]);
    }

    public function customer(Request $request, Workspace $workspace, int $customer, SupportSessionManager $manager): Response
    {
        $manager->require($request, $workspace, SupportAccessScope::WorkspaceContent);

        $customer = Customer::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($customer);

        AuditLog::record('support.content_access', $request, $workspace->id, $customer, [
            'resource' => 'customer',
        ]);

        return Inertia::render('Admin/Support/Customer', [
            'workspace' => $workspace->only(['id', 'name']),
            'customer' => $customer,
        ]);
    }

    public function order(Request $request, Workspace $workspace, int $order, SupportSessionManager $manager): Response
    {
        $manager->require($request, $workspace, SupportAccessScope::WorkspaceContent);

        $order = Order::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($order);

        AuditLog::record('support.content_access', $request, $workspace->id, $order, [
            'resource' => 'order',
        ]);

        $order->load(['customer', 'notes']);

        return Inertia::render('Admin/Support/Order', [
            'workspace' => $workspace->only(['id', 'name']),
            'order' => $order,
        ]);
    }
}
