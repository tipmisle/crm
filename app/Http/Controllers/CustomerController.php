<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Customer::query()->withCount('orders')->with('primaryChannel');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $customers = $query->orderBy('full_name')->paginate(24)->withQueryString();

        $customers->getCollection()->transform(fn (Customer $c) => [
            'id' => $c->id,
            'full_name' => $c->full_name,
            'email' => $c->email,
            'phone' => $c->phone,
            'primary_channel' => $c->primaryChannel,
            'last_interaction_at' => $c->last_interaction_at,
            'orders_count' => $c->orders_count,
            'lifetime_spend' => $c->lifetimeSpend(),
            'open_orders_count' => $c->openOrdersCount(),
        ]);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
        ]);

        $customer = Customer::create([
            ...$data,
            'first_contacted_at' => now(),
            'last_interaction_at' => now(),
        ]);

        ActivityLog::record('customer_created', "{$customer->full_name} added as a customer", $customer);

        return redirect()->route('customers.show', $customer);
    }

    public function show(Customer $customer): Response
    {
        $customer->load([
            'identities',
            'primaryChannel',
            'orders' => fn ($q) => $q->orderByDesc('created_at'),
            'orders.channel',
            'conversations' => fn ($q) => $q->orderByDesc('last_message_at'),
            'conversations.channel',
        ]);

        $activity = ActivityLog::where(function ($q) use ($customer) {
            $q->where('subject_type', Customer::class)->where('subject_id', $customer->id);
        })->orWhere(function ($q) use ($customer) {
            $q->where('subject_type', \App\Models\Order::class)
                ->whereIn('subject_id', $customer->orders->pluck('id'));
        })->orderByDesc('created_at')->limit(30)->get();

        return Inertia::render('Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'notes' => $customer->notes,
                'tags' => $customer->tags,
                'identities' => $customer->identities,
                'primary_channel' => $customer->primaryChannel,
                'first_contacted_at' => $customer->first_contacted_at,
                'last_interaction_at' => $customer->last_interaction_at,
                'orders' => $customer->orders,
                'conversations' => $customer->conversations,
                'lifetime_spend' => $customer->lifetimeSpend(),
                'open_orders_count' => $customer->openOrdersCount(),
                'completed_orders_count' => $customer->completedOrdersCount(),
            ],
            'followUps' => $customer->followUps()->orderBy('due_at')->get(),
            'activity' => $activity,
        ]);
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'full_name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
        ]);

        $customer->update($data);

        return back()->with('success', 'Customer updated.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()->route('customers.index');
    }
}
