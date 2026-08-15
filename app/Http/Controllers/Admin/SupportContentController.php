<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\AuditLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\Order;
use App\Models\Workspace;
use App\Support\AttachmentResolver;
use App\Support\SupportSessionManager;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every action here requires an ACTIVE support session for the exact
 * workspace being viewed (see SupportSessionManager::require) — there is no
 * other path in the admin area to customer content, and admin.current_
 * workspace_id is never touched (no impersonation). Each detail view
 * records a single support.content_access audit row per resource, never
 * per message. browse() itself is not audited — it's the read-only
 * equivalent of an index/list page, deliberately kept to minimal metadata
 * (never decrypting full message bodies) so browsing doesn't create audit
 * noise; opening an individual resource is what gets logged.
 */
class SupportContentController extends Controller
{
    public function browse(Request $request, Workspace $workspace, SupportSessionManager $manager): InertiaResponse
    {
        $manager->require($request, $workspace);

        $withoutScope = fn (string $model) => $model::withoutGlobalScopes()->where('workspace_id', $workspace->id);

        return Inertia::render('Admin/Support/Browse', [
            'workspace' => $workspace->only(['id', 'name']),
            // Every eager-loaded 'customer' relation below must explicitly
            // bypass Customer's BelongsToWorkspace scope the same way
            // $withoutScope() does for the top-level query — a platform
            // admin has no current_workspace_id, so that scope otherwise
            // silently resolves the relation to null for every row (the
            // same class of bug fixed in Admin\DashboardController).
            'conversations' => $withoutScope(Conversation::class)
                ->with(['customer' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'full_name')])
                ->orderByDesc('last_message_at')
                ->limit(100)
                ->get(['id', 'customer_id', 'customer_display_name', 'customer_username', 'status', 'last_message_at']),
            'customers' => $withoutScope(Customer::class)
                ->orderBy('full_name')
                ->limit(100)
                ->get(['id', 'full_name', 'email', 'phone']),
            'orders' => $withoutScope(Order::class)
                ->with(['customer' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'full_name')])
                ->orderByDesc('created_at')
                ->limit(100)
                ->get(['id', 'order_number', 'title', 'status', 'customer_id', 'created_at']),
            'appointments' => $withoutScope(Appointment::class)
                ->with(['customer' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'full_name')])
                ->orderByDesc('appointment_date')
                ->limit(100)
                ->get(['id', 'appointment_number', 'service_name', 'status', 'appointment_date', 'customer_id']),
        ]);
    }

    public function conversation(Request $request, Workspace $workspace, int $conversation, SupportSessionManager $manager): InertiaResponse
    {
        $manager->require($request, $workspace);

        $conversation = Conversation::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($conversation);

        AuditLog::record('support.content_access', $request, $workspace->id, $conversation, [
            'resource' => 'conversation',
        ]);

        // 'customer' and 'channel' both carry BelongsToWorkspace — must be
        // bypassed explicitly, same reasoning as browse() above.
        $conversation->load([
            'customer' => fn ($q) => $q->withoutGlobalScopes(),
            'channel' => fn ($q) => $q->withoutGlobalScopes(),
            'messages' => fn ($q) => $q->orderBy('sent_at'),
        ]);

        return Inertia::render('Admin/Support/Conversation', [
            'workspace' => $workspace->only(['id', 'name']),
            'conversation' => $conversation,
        ]);
    }

    public function customer(Request $request, Workspace $workspace, int $customer, SupportSessionManager $manager): InertiaResponse
    {
        $manager->require($request, $workspace);

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

    public function order(Request $request, Workspace $workspace, int $order, SupportSessionManager $manager): InertiaResponse
    {
        $manager->require($request, $workspace);

        $order = Order::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($order);

        AuditLog::record('support.content_access', $request, $workspace->id, $order, [
            'resource' => 'order',
        ]);

        $order->load(['customer' => fn ($q) => $q->withoutGlobalScopes(), 'notes']);

        return Inertia::render('Admin/Support/Order', [
            'workspace' => $workspace->only(['id', 'name']),
            'order' => $order,
        ]);
    }

    public function appointment(Request $request, Workspace $workspace, int $appointment, SupportSessionManager $manager): InertiaResponse
    {
        $manager->require($request, $workspace);

        $appointment = Appointment::withoutGlobalScopes()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($appointment);

        AuditLog::record('support.content_access', $request, $workspace->id, $appointment, [
            'resource' => 'appointment',
        ]);

        $appointment->load(['customer' => fn ($q) => $q->withoutGlobalScopes()]);

        return Inertia::render('Admin/Support/Appointment', [
            'workspace' => $workspace->only(['id', 'name']),
            'appointment' => $appointment,
        ]);
    }

    public function attachment(
        Request $request,
        Workspace $workspace,
        int $message,
        int $index,
        SupportSessionManager $manager,
        AttachmentResolver $resolver,
    ): StreamedResponse {
        $manager->require($request, $workspace);

        $message = Message::with(['conversation' => fn ($q) => $q->withoutGlobalScopes()])->findOrFail($message);

        abort_unless($message->conversation && $message->conversation->workspace_id === $workspace->id, 404);

        AuditLog::record('support.content_access', $request, $workspace->id, $message, [
            'resource' => 'attachment',
        ]);

        return $resolver->respond($message, $index);
    }
}
