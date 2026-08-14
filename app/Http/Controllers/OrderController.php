<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class OrderController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Order::query()->with(['customer', 'channel']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('order_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($payment = $request->get('payment')) {
            $query->where('payment_status', $payment);
        }

        if ($customerId = $request->get('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $due = $request->get('due');
        if ($due === 'today') {
            $query->whereDate('due_date', Carbon::today());
        } elseif ($due === 'overdue') {
            $query->whereDate('due_date', '<', Carbon::today())
                ->whereNotIn('status', [OrderStatus::Completed->value, OrderStatus::Cancelled->value]);
        } elseif ($due === 'week') {
            $query->whereBetween('due_date', [Carbon::today(), Carbon::today()->addDays(7)]);
        }

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
                'filters' => $request->only(['search', 'status', 'payment']),
            ]);
        }

        if ($view === 'kanban') {
            $orders = $query->orderBy('due_date')->get()->groupBy(fn (Order $o) => $o->status->value);

            $board = collect(OrderStatus::board())->mapWithKeys(fn (OrderStatus $status) => [
                $status->value => $orders->get($status->value, collect())->values(),
            ]);

            return Inertia::render('Orders/Kanban', [
                'board' => $board,
                'filters' => $request->only(['search', 'status', 'payment', 'due']),
            ]);
        }

        $orders = $query->orderByDesc('created_at')->paginate(20)->withQueryString();

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'filters' => $request->only(['search', 'status', 'payment', 'due']),
        ]);
    }

    public function create(Request $request): Response
    {
        $customer = $request->get('customer_id') ? Customer::find($request->get('customer_id')) : null;
        $conversation = $request->get('conversation_id') ? Conversation::with('customer')->find($request->get('conversation_id')) : null;

        return Inertia::render('Orders/Create', [
            'customer' => $customer,
            'conversation' => $conversation,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'customer_id' => 'nullable|exists:customers,id',
            'conversation_id' => 'nullable|exists:conversations,id',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable',
            'price' => 'required|numeric|min:0',
            'deposit_amount' => 'nullable|numeric|min:0',
            'internal_notes' => 'nullable|string|max:2000',
            'customer_notes' => 'nullable|string|max:2000',
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

        abort_unless($customer, 422, 'Naročilo potrebuje stranko.');

        $deposit = (float) ($data['deposit_amount'] ?? 0);
        $paymentStatus = $deposit > 0 ? PaymentStatus::DepositDue : PaymentStatus::Unpaid;

        $order = Order::create([
            'customer_id' => $customer->id,
            'conversation_id' => $conversation?->id,
            'channel_id' => $conversation?->channel_id ?? $customer->primary_channel_id,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'due_time' => $data['due_time'] ?? null,
            'price' => $data['price'],
            'deposit_amount' => $deposit,
            'amount_paid' => 0,
            'payment_status' => $paymentStatus,
            'status' => OrderStatus::New,
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
        $order->load(['customer.orders', 'conversation.channel', 'channel', 'notes.user']);

        return Inertia::render('Orders/Show', [
            'order' => $order,
            'followUps' => $order->followUps()->orderBy('due_at')->get(),
            'activity' => \App\Models\ActivityLog::where('subject_type', Order::class)
                ->where('subject_id', $order->id)
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function update(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'due_date' => 'nullable|date',
            'due_time' => 'nullable',
            'price' => 'sometimes|numeric|min:0',
            'deposit_amount' => 'sometimes|numeric|min:0',
            'amount_paid' => 'sometimes|numeric|min:0',
            'payment_status' => 'sometimes|string',
            'status' => 'sometimes|string',
            'internal_notes' => 'nullable|string|max:2000',
            'customer_notes' => 'nullable|string|max:2000',
        ]);

        $previousStatus = $order->status;

        $order->update($data);

        if (isset($data['status']) && $data['status'] !== $previousStatus->value) {
            ActivityLog::record(
                'status_changed',
                "Naročilo {$order->order_number} označeno kot {$order->status->label()}",
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
