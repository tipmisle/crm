<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\InvoiceSettings;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Models\Product;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OrderController extends Controller
{
    /**
     * Every filter key recognized by index() — kept in one place and reused
     * for every view's `filters` prop so switching between list/calendar/
     * kanban never silently drops an active filter, and so cross-page links
     * (Today, Customer, Catalog, Analytics, Inbox) stay consistent.
     */
    private const FILTER_KEYS = [
        'search', 'status', 'payment', 'due',
        'status_scope', 'payment_scope', 'deposit',
        'customer_id', 'catalog_item_id', 'channel_type',
        'created_from', 'created_to',
    ];

    /**
     * The exact filter logic index() (and every view it renders) applies —
     * also reused verbatim by exportCsv() so the CSV can never drift from
     * whatever the user is currently looking at in the UI.
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', "%{$search}%"));
            });
        }

        if ($request->get('status_scope') === 'open') {
            $query->whereNotIn('status', OrderStatus::openExclusionKeys());
        } elseif ($request->get('status_scope') === 'not_cancelled') {
            // Matches the exclusion Analytics uses for revenue math (only
            // cancelled orders drop out — completed ones still count) so a
            // drill-down link from a revenue figure shows the same set.
            $query->whereNotIn('status', OrderStatus::cancelledKeys());
        } elseif ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($request->get('payment_scope') === 'outstanding') {
            $query->whereIn('payment_status', PaymentStatus::outstandingKeys());
        } elseif ($payment = $request->get('payment')) {
            $query->where('payment_status', $payment);
        }

        if ($request->get('deposit') === 'unpaid') {
            $query->where('deposit_amount', '>', 0);
        }

        if ($customerId = $request->get('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        if ($catalogItemId = $request->get('catalog_item_id')) {
            $query->where('catalog_item_id', $catalogItemId);
        }

        if ($channelType = $request->get('channel_type')) {
            $query->whereHas('channel', fn ($q) => $q->where('type', $channelType));
        }

        if ($createdFrom = $request->get('created_from')) {
            $query->whereDate('created_at', '>=', $createdFrom);
        }

        if ($createdTo = $request->get('created_to')) {
            $query->whereDate('created_at', '<=', $createdTo);
        }

        $due = $request->get('due');
        if ($due === 'today') {
            $query->whereDate('due_date', Carbon::today());
        } elseif ($due === 'overdue') {
            $query->whereDate('due_date', '<', Carbon::today())
                ->whereNotIn('status', OrderStatus::openExclusionKeys());
        } elseif ($due === 'week') {
            $query->whereBetween('due_date', [Carbon::today(), Carbon::today()->addDays(7)]);
        }

        return $query;
    }

    public function index(Request $request): Response
    {
        $query = $this->applyFilters(
            Order::query()->with(['customer', 'channel', 'salesDocuments:id,order_id,type']),
            $request
        );

        $view = $request->get('view', 'list');

        if ($view === 'calendar') {
            $month = Carbon::createFromFormat('Y-m', $request->get('month', Carbon::today()->format('Y-m')))
                ->startOfMonth();

            $orders = (clone $query)
                ->whereBetween('due_date', [$month->copy()->startOfMonth(), $month->copy()->endOfMonth()])
                ->orderBy('due_time')
                ->get();

            $ordersByDate = $orders->groupBy(fn (Order $o) => $o->due_date->format('Y-m-d'));

            return Inertia::render('Orders/Calendar', [
                'ordersByDate' => $ordersByDate,
                'month' => $month->format('Y-m'),
                'filters' => $request->only(self::FILTER_KEYS),
            ]);
        }

        if ($view === 'kanban') {
            $orders = $query->orderBy('due_date')->get()->groupBy('status');

            $board = OrderStatus::query()->ordered()->pluck('key')->mapWithKeys(fn (string $key) => [
                $key => $orders->get($key, collect())->values(),
            ]);

            return Inertia::render('Orders/Kanban', [
                'board' => $board,
                'filters' => $request->only(self::FILTER_KEYS),
            ]);
        }

        $orders = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(self::FILTER_KEYS),
        ]);
    }

    /**
     * "Izvozi CSV" — exports every Order matching the CURRENT filters (via
     * the same applyFilters() index() uses, so the two can never drift),
     * not just the current pagination page. Streams row-by-row via
     * lazy() (chunked reads, eager loads still apply — unlike cursor())
     * so a large export never holds the full result set in memory.
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $query = $this->applyFilters(
            Order::query()->with(['customer:id,full_name,email,phone', 'channel:id,type', 'product:id,name']),
            $request
        )->orderByDesc('created_at');

        $orderStatusLabels = OrderStatus::query()->pluck('label', 'key');
        $paymentStatusLabels = PaymentStatus::query()->pluck('label', 'key');

        $headers = [
            'Št. naročila', 'Datum ustvarjanja', 'Stranka', 'Email', 'Telefon', 'Produkt',
            'Naslov naročila', 'Status', 'Status plačila', 'Rok', 'Ura', 'Cena', 'Ara',
            'Plačano', 'Preostanek', 'Način dostave', 'Tracking številka', 'Vir/kanal',
        ];

        $rows = $query->lazy(200)->map(fn (Order $order) => [
            $order->order_number,
            $order->created_at?->format('Y-m-d H:i'),
            $order->customer?->full_name,
            $order->customer?->email,
            $order->customer?->phone,
            $order->product?->name,
            $order->title,
            $orderStatusLabels[$order->status] ?? $order->status,
            $paymentStatusLabels[$order->payment_status] ?? $order->payment_status,
            $order->due_date?->format('Y-m-d'),
            $order->due_time,
            $order->price,
            $order->deposit_amount,
            $order->amount_paid,
            $order->balanceDue(),
            $order->delivery_method,
            $order->tracking_number,
            $order->channel?->type,
        ]);

        return Csv::streamDownload('narocila-'.now()->format('Y-m-d').'.csv', $headers, $rows);
    }

    public function create(Request $request): Response
    {
        $customer = $request->get('customer_id') ? Customer::find($request->get('customer_id')) : null;
        $conversation = $request->get('conversation_id') ? Conversation::with('customer')->find($request->get('conversation_id')) : null;
        $product = $request->get('product_id') ? Product::find($request->get('product_id')) : null;

        return Inertia::render('Orders/Create', [
            'customer' => $customer,
            'conversation' => $conversation,
            'products' => Product::where('active', true)->orderBy('name')->get(),
            'customers' => $customer || $conversation ? [] : Customer::orderBy('full_name')->get(),
            'selectedProductId' => $product?->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = $request->user()->current_workspace_id;

        $data = $request->validate([
            // A catalog_item_id must be a Product (not a Service — both
            // share the catalog_items table) belonging to THIS workspace;
            // a plain exists:catalog_items,id would accept either type from
            // any workspace.
            'catalog_item_id' => ['nullable', Rule::exists('catalog_items', 'id')->where(fn ($q) => $q->where('workspace_id', $workspaceId)->where('type', 'product'))],
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('workspace_id', $workspaceId)],
            'customer_name' => 'required_without_all:customer_id,conversation_id|nullable|string|max:255',
            'conversation_id' => ['nullable', Rule::exists('conversations', 'id')->where('workspace_id', $workspaceId)],
            'due_date' => 'nullable|date',
            'due_time' => 'nullable',
            'price' => 'required|numeric|min:0',
            'deposit_amount' => 'nullable|numeric|min:0',
            'internal_notes' => 'nullable|string|max:2000',
            'customer_notes' => 'nullable|string|max:2000',
        ], [
            'customer_name.required_without_all' => 'Naročilo potrebuje stranko.',
        ]);

        $conversation = isset($data['conversation_id']) ? Conversation::with('channel')->find($data['conversation_id']) : null;
        $customer = isset($data['customer_id']) ? Customer::find($data['customer_id']) : $conversation?->customer;

        if (! $customer && $conversation) {
            $customer = Customer::create([
                'full_name' => $conversation->customer_display_name ?? 'Neznana stranka',
                'primary_channel_id' => $conversation->channel_id,
                'first_contacted_at' => $conversation->created_at,
                'last_interaction_at' => $conversation->last_message_at ?? $conversation->created_at,
            ]);

            CustomerIdentity::create([
                'customer_id' => $customer->id,
                'workspace_id' => $customer->workspace_id,
                'channel_type' => $conversation->channel->type,
                'username' => $conversation->customer_username,
                'display_name' => $conversation->customer_display_name,
            ]);

            $conversation->update(['customer_id' => $customer->id]);
        }

        if (! $customer && ! empty($data['customer_name'])) {
            $customer = Customer::create([
                'full_name' => $data['customer_name'],
                'first_contacted_at' => now(),
                'last_interaction_at' => now(),
            ]);
        }

        abort_unless($customer, 422, 'Naročilo potrebuje stranko.');

        $deposit = (float) ($data['deposit_amount'] ?? 0);
        $paymentStatus = $deposit > 0 ? PaymentStatus::depositDefaultKey() : PaymentStatus::defaultKey();

        $order = Order::create([
            'customer_id' => $customer->id,
            'conversation_id' => $conversation?->id,
            'channel_id' => $conversation?->channel_id ?? $customer->primary_channel_id,
            'catalog_item_id' => $data['catalog_item_id'] ?? null,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'due_time' => $data['due_time'] ?? null,
            'price' => $data['price'],
            'deposit_amount' => $deposit,
            'amount_paid' => 0,
            'payment_status' => $paymentStatus,
            'status' => OrderStatus::defaultKey(),
            'internal_notes' => $data['internal_notes'] ?? null,
            'customer_notes' => $data['customer_notes'] ?? null,
        ]);

        if ($conversation) {
            $conversation->update(['status' => 'order_confirmed']);
        }

        ActivityLog::record('order_created', "Naročilo {$order->order_number} ustvarjeno za {$customer->full_name}", $order);

        return redirect()->route('orders.show', $order)->with('success', 'Naročilo ustvarjeno.');
    }

    public function show(Order $order): Response
    {
        $order->load(['customer.orders', 'customer.primaryChannel', 'conversation.channel', 'channel', 'notes.user', 'salesDocuments.correctsDocument:id,document_number,issued_at,type', 'salesDocuments.correction:id,document_number,corrects_document_id,type', 'workspace', 'product']);

        $mockNotificationsEnabled = app()->isLocal() || $order->workspace?->is_demo;
        $order->setAttribute('can_notify_customer', (bool) $order->conversation
            || ($mockNotificationsEnabled && (bool) ($order->channel || $order->customer?->primaryChannel)));

        $invoiceSettings = InvoiceSettings::where('workspace_id', $order->workspace_id)->first();

        return Inertia::render('Orders/Show', [
            'order' => $order,
            'followUps' => $order->followUps()->orderBy('due_at')->get(),
            'activity' => ActivityLog::where('subject_type', Order::class)
                ->where('subject_id', $order->id)
                ->orderByDesc('created_at')
                ->get(),
            'invoiceSettingsConfigured' => $invoiceSettings?->isConfigured() ?? false,
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        // Custom keys are fully supported — this checks against the CURRENT
        // workspace's OrderStatus/PaymentStatus rows (any key that exists),
        // not a fixed whitelist of canonical names.
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable',
            'price' => 'sometimes|numeric|min:0',
            'deposit_amount' => 'sometimes|numeric|min:0',
            'amount_paid' => 'sometimes|numeric|min:0',
            'payment_status' => ['sometimes', 'string', Rule::exists('payment_statuses', 'key')->where('workspace_id', $order->workspace_id)],
            'status' => ['sometimes', 'string', Rule::exists('order_statuses', 'key')->where('workspace_id', $order->workspace_id)],
            'internal_notes' => 'nullable|string|max:2000',
            'customer_notes' => 'nullable|string|max:2000',
            'delivery_method' => 'nullable|in:mail,pickup',
            'tracking_number' => 'nullable|string|max:100',
            'tracking_url' => 'nullable|url|max:2048',
        ]);

        $previousStatus = $order->status;

        $order->update($data);

        if (isset($data['status']) && $data['status'] !== $previousStatus) {
            $label = OrderStatus::where('key', $order->status)->value('label') ?? $order->status;

            ActivityLog::record(
                'status_changed',
                "Naročilo {$order->order_number} označeno kot {$label}",
                $order
            );
        }

        return back()->with('success', 'Naročilo posodobljeno.');
    }

    public function destroy(Order $order): RedirectResponse
    {
        $order->delete();

        return redirect()->route('orders.index')->with('success', 'Naročilo izbrisano.');
    }
}
