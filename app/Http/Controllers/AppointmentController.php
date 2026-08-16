<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\InvoiceSettings;
use App\Models\PaymentStatus;
use App\Models\Service;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class AppointmentController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Appointment::query()->with(['customer', 'channel', 'service']);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('appointment_number', 'like', "%{$search}%")
                    ->orWhere('service_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        if ($payment = $request->get('payment')) {
            $query->where('payment_status', $payment);
        }

        $due = $request->get('due');
        if ($due === 'today') {
            $query->whereDate('appointment_date', Carbon::today());
        } elseif ($due === 'week') {
            $query->whereBetween('appointment_date', [Carbon::today(), Carbon::today()->addDays(7)]);
        } elseif ($due === 'overdue') {
            $query->whereDate('appointment_date', '<', Carbon::today())
                ->whereNotIn('status', [AppointmentStatus::Completed->value, AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value]);
        }

        $view = $request->get('view', 'list');

        if ($view === 'list') {
            $appointments = $query->orderByDesc('appointment_date')->orderByDesc('start_time')->paginate(20)->withQueryString();

            return Inertia::render('Appointments/Index', [
                'appointments' => $appointments,
                'filters' => $request->only(['search', 'status', 'payment', 'due']),
            ]);
        }

        $weekStart = $request->get('week')
            ? Carbon::parse($request->get('week'))->startOfWeek(Carbon::MONDAY)
            : Carbon::today()->startOfWeek(Carbon::MONDAY);

        $appointments = (clone $query)
            ->whereBetween('appointment_date', [$weekStart->copy(), $weekStart->copy()->endOfWeek(Carbon::SUNDAY)])
            ->orderBy('start_time')
            ->get();

        $appointmentsByDate = $appointments->groupBy(fn (Appointment $a) => $a->appointment_date->format('Y-m-d'));

        return Inertia::render('Appointments/Calendar', [
            'appointmentsByDate' => $appointmentsByDate,
            'weekStart' => $weekStart->format('Y-m-d'),
            'filters' => $request->only(['search', 'status', 'payment', 'due']),
        ]);
    }

    public function create(Request $request): Response
    {
        $customer = $request->get('customer_id') ? Customer::find($request->get('customer_id')) : null;
        $conversation = $request->get('conversation_id') ? Conversation::with(['customer', 'channel'])->find($request->get('conversation_id')) : null;

        return Inertia::render('Appointments/Create', [
            'customer' => $customer,
            'conversation' => $conversation,
            'services' => Service::where('active', true)->orderBy('name')->get(),
            'customers' => $customer || $conversation ? [] : Customer::orderBy('full_name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'service_id' => 'nullable|exists:catalog_items,id',
            'service_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'customer_id' => 'nullable|exists:customers,id',
            'customer_name' => 'required_without_all:customer_id,conversation_id|nullable|string|max:255',
            'conversation_id' => 'nullable|exists:conversations,id',
            'appointment_date' => 'required|date',
            'start_time' => 'required',
            'duration_minutes' => 'required|integer|min:5',
            'price' => 'nullable|numeric|min:0',
            'deposit_amount' => 'nullable|numeric|min:0',
            'internal_notes' => 'nullable|string|max:2000',
            'customer_notes' => 'nullable|string|max:2000',
        ], [
            'customer_name.required_without_all' => 'Termin potrebuje stranko.',
        ]);

        $conversation = isset($data['conversation_id']) ? Conversation::with('channel')->find($data['conversation_id']) : null;
        $customer = isset($data['customer_id']) ? Customer::find($data['customer_id']) : $conversation?->customer;

        // Same auto-create-and-link logic used by Order::store — an
        // appointment must never force the owner to create a customer first.
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

        abort_unless($customer, 422, 'Termin potrebuje stranko.');

        $deposit = (float) ($data['deposit_amount'] ?? 0);
        $paymentStatus = $deposit > 0 ? PaymentStatus::depositDefaultKey() : PaymentStatus::defaultKey();

        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'conversation_id' => $conversation?->id,
            'channel_id' => $conversation?->channel_id ?? $customer->primary_channel_id,
            'service_id' => $data['service_id'] ?? null,
            'service_name' => $data['service_name'],
            'description' => $data['description'] ?? null,
            'appointment_date' => $data['appointment_date'],
            'start_time' => $data['start_time'],
            'duration_minutes' => $data['duration_minutes'],
            'price' => $data['price'] ?? null,
            'deposit_amount' => $deposit,
            'amount_paid' => 0,
            'payment_status' => $paymentStatus,
            'status' => AppointmentStatus::Requested,
            'internal_notes' => $data['internal_notes'] ?? null,
            'customer_notes' => $data['customer_notes'] ?? null,
        ]);

        ActivityLog::record(
            'appointment_created',
            "Termin {$appointment->appointment_number} ({$appointment->service_name}) ustvarjen za {$customer->full_name}",
            $appointment
        );

        return redirect()->route('appointments.show', $appointment)->with('success', 'Termin ustvarjen.');
    }

    public function show(Appointment $appointment): Response
    {
        $appointment->load(['customer.appointments', 'customer.primaryChannel', 'conversation.channel', 'channel', 'service', 'salesDocuments', 'workspace']);

        $mockNotificationsEnabled = app()->isLocal() || $appointment->workspace?->is_demo;
        $appointment->setAttribute('can_notify_customer', (bool) $appointment->conversation
            || ($mockNotificationsEnabled && (bool) ($appointment->channel || $appointment->customer?->primaryChannel)));

        $invoiceSettings = InvoiceSettings::where('workspace_id', $appointment->workspace_id)->first();

        return Inertia::render('Appointments/Show', [
            'appointment' => $appointment,
            'followUps' => $appointment->followUps()->orderBy('due_at')->get(),
            'activity' => ActivityLog::where('subject_type', Appointment::class)
                ->where('subject_id', $appointment->id)
                ->orderByDesc('created_at')
                ->get(),
            'invoiceSettingsConfigured' => $invoiceSettings?->isConfigured() ?? false,
        ]);
    }

    public function update(Request $request, Appointment $appointment): RedirectResponse
    {
        $data = $request->validate([
            'service_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'appointment_date' => 'sometimes|date',
            'start_time' => 'sometimes',
            'duration_minutes' => 'sometimes|integer|min:5',
            'price' => 'nullable|numeric|min:0',
            'deposit_amount' => 'sometimes|numeric|min:0',
            'amount_paid' => 'sometimes|numeric|min:0',
            'payment_status' => 'sometimes|string',
            'status' => 'sometimes|string',
            'internal_notes' => 'nullable|string|max:2000',
            'customer_notes' => 'nullable|string|max:2000',
        ]);

        $previousStatus = $appointment->status;
        $previousDate = $appointment->appointment_date->format('Y-m-d');
        $previousTime = $appointment->start_time;

        $appointment->update($data);

        if (isset($data['status']) && $data['status'] !== $previousStatus->value) {
            ActivityLog::record(
                'status_changed',
                "Termin {$appointment->appointment_number} označen kot {$appointment->status->label()}",
                $appointment
            );
        }

        $dateChanged = isset($data['appointment_date']) && $data['appointment_date'] !== $previousDate;
        $timeChanged = isset($data['start_time']) && $data['start_time'] !== $previousTime;

        if ($dateChanged || $timeChanged) {
            // Rescheduling is logged as an activity event rather than a
            // dedicated status — the appointment's current status (requested/
            // confirmed) still describes it accurately after a date change.
            ActivityLog::record(
                'appointment_rescheduled',
                "Termin {$appointment->appointment_number} prestavljen na ".$appointment->appointment_date->format('d.m.Y')." ob {$appointment->start_time}",
                $appointment
            );
        }

        return back()->with('success', 'Termin posodobljen.');
    }

    public function destroy(Appointment $appointment): RedirectResponse
    {
        $appointment->delete();

        return redirect()->route('appointments.index')->with('success', 'Termin izbrisan.');
    }
}
