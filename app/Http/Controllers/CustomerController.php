<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;

        $query = Customer::query()->with('primaryChannel');

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Aggregated in the list query itself (withCount/withSum) rather
        // than via the per-instance Customer helper methods used on the
        // Show page — those would be an N+1 (one pair of queries per row)
        // across a full page of customers.
        if ($workspace->orders_enabled) {
            $openExclusionKeys = OrderStatus::openExclusionKeys();
            $query->withCount('orders')
                ->withSum('orders as lifetime_spend', 'amount_paid')
                ->withCount(['orders as open_orders_count' => fn ($q) => $q->whereNotIn('status', $openExclusionKeys)]);
        }

        if ($workspace->appointments_enabled) {
            $query->withCount('appointments')
                ->withSum('appointments as appointments_lifetime_spend', 'amount_paid');
        }

        $customers = $query->orderBy('full_name')->paginate(24)->withQueryString();

        // One extra query total (not one per row) to find each customer's
        // next upcoming appointment on this page.
        $upcomingByCustomer = collect();
        if ($workspace->appointments_enabled) {
            $upcomingByCustomer = Appointment::query()
                ->whereIn('customer_id', $customers->getCollection()->pluck('id'))
                ->whereIn('status', ['requested', 'confirmed'])
                ->where('appointment_date', '>=', now()->toDateString())
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->get(['id', 'customer_id', 'service_name', 'appointment_date', 'start_time'])
                ->groupBy('customer_id')
                ->map(fn ($group) => $group->first());
        }

        $customers->getCollection()->transform(function (Customer $c) use ($workspace, $upcomingByCustomer) {
            $row = [
                'id' => $c->id,
                'full_name' => $c->full_name,
                'email' => $c->email,
                'phone' => $c->phone,
                'primary_channel' => $c->primaryChannel,
                'last_interaction_at' => $c->last_interaction_at,
            ];

            if ($workspace->orders_enabled) {
                $row['orders_count'] = $c->orders_count;
                $row['lifetime_spend'] = (float) ($c->lifetime_spend ?? 0);
                $row['open_orders_count'] = $c->open_orders_count;
            }

            if ($workspace->appointments_enabled) {
                $row['appointments_count'] = $c->appointments_count;
                $row['appointments_lifetime_spend'] = (float) ($c->appointments_lifetime_spend ?? 0);

                $upcoming = $upcomingByCustomer->get($c->id);
                $row['upcoming_appointment'] = $upcoming ? [
                    'id' => $upcoming->id,
                    'service_name' => $upcoming->service_name,
                    'appointment_date' => $upcoming->appointment_date->format('Y-m-d'),
                    'start_time' => $upcoming->start_time,
                ] : null;
            }

            return $row;
        });

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
            'address_line' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:120',
            'country' => 'nullable|string|max:120',
            'tax_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
        ]);

        $customer = Customer::create([
            ...$data,
            'first_contacted_at' => now(),
            'last_interaction_at' => now(),
        ]);

        ActivityLog::record('customer_created', "Stranka {$customer->full_name} je bila dodana", $customer);

        return redirect()->route('customers.show', $customer);
    }

    public function show(Customer $customer): Response
    {
        $customer->load([
            'identities',
            'primaryChannel',
            'orders' => fn ($q) => $q->orderByDesc('created_at'),
            'orders.channel',
            'orders.salesDocuments:id,order_id,type',
            'appointments' => fn ($q) => $q->orderByDesc('appointment_date')->orderByDesc('start_time'),
            'appointments.channel',
            'appointments.service',
            'conversations' => fn ($q) => $q->orderByDesc('last_message_at'),
            'conversations.channel',
        ]);

        $activity = ActivityLog::where(function ($q) use ($customer) {
            $q->where('subject_type', Customer::class)->where('subject_id', $customer->id);
        })->orWhere(function ($q) use ($customer) {
            $q->where('subject_type', Order::class)
                ->whereIn('subject_id', $customer->orders->pluck('id'));
        })->orWhere(function ($q) use ($customer) {
            $q->where('subject_type', Appointment::class)
                ->whereIn('subject_id', $customer->appointments->pluck('id'));
        })->orderByDesc('created_at')->limit(30)->get();

        return Inertia::render('Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'address_line' => $customer->address_line,
                'postal_code' => $customer->postal_code,
                'city' => $customer->city,
                'country' => $customer->country,
                'tax_number' => $customer->tax_number,
                'notes' => $customer->notes,
                'tags' => $customer->tags,
                'primary_channel_id' => $customer->primary_channel_id,
                'identities' => $customer->identities,
                'primary_channel' => $customer->primaryChannel,
                'first_contacted_at' => $customer->first_contacted_at,
                'last_interaction_at' => $customer->last_interaction_at,
                'orders' => $customer->orders,
                'appointments' => $customer->appointments,
                'conversations' => $customer->conversations,
                'lifetime_spend' => $customer->lifetimeSpend(),
                'open_orders_count' => $customer->openOrdersCount(),
                'completed_orders_count' => $customer->completedOrdersCount(),
                'appointments_lifetime_spend' => $customer->appointmentsLifetimeSpend(),
                'previous_appointments_count' => $customer->previousAppointmentsCount(),
                'no_show_appointments_count' => $customer->noShowAppointmentsCount(),
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
            'address_line' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:120',
            'country' => 'nullable|string|max:120',
            'tax_number' => 'nullable|string|max:50',
            'notes' => 'nullable|string|max:2000',
        ]);

        $customer->update($data);

        return back()->with('success', 'Stranka posodobljena.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()->route('customers.index');
    }
}
