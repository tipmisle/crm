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
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AppointmentController extends Controller
{
    /** See OrderController::FILTER_KEYS for why this list is centralized. */
    private const FILTER_KEYS = [
        'search', 'status', 'payment', 'due', 'payment_scope', 'deposit',
        'customer_id', 'service_id', 'channel_type',
        'created_from', 'created_to',
    ];

    /**
     * The exact filter logic index() applies — also reused verbatim by
     * exportCsv() so the CSV can never drift from what's on screen. See
     * OrderController::applyFilters() for the same pattern.
     */
    private function applyFilters(Builder $query, Request $request): Builder
    {
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('appointment_number', 'like', "%{$search}%")
                    ->orWhere('service_name', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', "%{$search}%"));
            });
        }

        if ($status = $request->get('status')) {
            // Supports a comma-separated list (e.g. "requested,confirmed")
            // so a link can target the same active-status set the Today
            // attention counts use, without a single-status constraint.
            $query->whereIn('status', explode(',', $status));
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

        if ($serviceId = $request->get('service_id')) {
            $query->where('service_id', $serviceId);
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
            $query->whereDate('appointment_date', Carbon::today());
        } elseif ($due === 'week') {
            $query->whereBetween('appointment_date', [Carbon::today(), Carbon::today()->addDays(7)]);
        } elseif ($due === 'overdue') {
            $query->whereDate('appointment_date', '<', Carbon::today())
                ->whereNotIn('status', [AppointmentStatus::Completed->value, AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value]);
        }

        return $query;
    }

    public function index(Request $request): Response
    {
        $query = $this->applyFilters(Appointment::query()->with(['customer', 'channel', 'service']), $request);

        $view = $request->get('view', 'list');

        if ($view === 'list') {
            $appointments = $query->orderByDesc('appointment_date')->orderByDesc('start_time')->paginate(20)->withQueryString();

            return Inertia::render('Appointments/Index', [
                'appointments' => $appointments,
                'filters' => $request->only(self::FILTER_KEYS),
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
            'filters' => $request->only(self::FILTER_KEYS),
        ]);
    }

    /**
     * "Izvozi CSV" — see OrderController::exportCsv() for the same
     * pattern: same applyFilters() as index(), exports every matching
     * row (not just the current page), streamed via lazy().
     */
    public function exportCsv(Request $request): StreamedResponse
    {
        $query = $this->applyFilters(
            Appointment::query()->with(['customer:id,full_name,email,phone', 'channel:id,type', 'service:id,name']),
            $request
        )->orderByDesc('appointment_date')->orderByDesc('start_time');

        $paymentStatusLabels = PaymentStatus::query()->pluck('label', 'key');

        $headers = [
            'Št. termina', 'Datum termina', 'Ura', 'Trajanje', 'Stranka', 'Email', 'Telefon',
            'Storitev', 'Status', 'Status plačila', 'Cena', 'Ara', 'Plačano', 'Preostanek', 'Vir/kanal',
        ];

        $rows = $query->lazy(200)->map(fn (Appointment $appointment) => [
            $appointment->appointment_number,
            $appointment->appointment_date?->format('Y-m-d'),
            $appointment->start_time,
            $appointment->duration_minutes,
            $appointment->customer?->full_name,
            $appointment->customer?->email,
            $appointment->customer?->phone,
            $appointment->service?->name ?? $appointment->service_name,
            $appointment->status->label(),
            $paymentStatusLabels[$appointment->payment_status] ?? $appointment->payment_status,
            $appointment->price,
            $appointment->deposit_amount,
            $appointment->amount_paid,
            $appointment->remainingBalance(),
            $appointment->channel?->type,
        ]);

        return Csv::streamDownload('termini-'.now()->format('Y-m-d').'.csv', $headers, $rows);
    }

    public function create(Request $request): Response
    {
        $customer = $request->get('customer_id') ? Customer::find($request->get('customer_id')) : null;
        $conversation = $request->get('conversation_id') ? Conversation::with(['customer', 'channel'])->find($request->get('conversation_id')) : null;
        $service = $request->get('service_id') ? Service::find($request->get('service_id')) : null;

        return Inertia::render('Appointments/Create', [
            'customer' => $customer,
            'conversation' => $conversation,
            'services' => Service::where('active', true)->orderBy('name')->get(),
            'customers' => $customer || $conversation ? [] : Customer::orderBy('full_name')->get(),
            'selectedServiceId' => $service?->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $workspaceId = $request->user()->current_workspace_id;

        $data = $request->validate([
            // A service_id must be a Service (not a Product — both share
            // the catalog_items table) belonging to THIS workspace; a plain
            // exists:catalog_items,id would accept either type from any
            // workspace.
            'service_id' => ['nullable', Rule::exists('catalog_items', 'id')->where(fn ($q) => $q->where('workspace_id', $workspaceId)->where('type', 'service'))],
            'service_name' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('workspace_id', $workspaceId)],
            'customer_name' => 'required_without_all:customer_id,conversation_id|nullable|string|max:255',
            'conversation_id' => ['nullable', Rule::exists('conversations', 'id')->where('workspace_id', $workspaceId)],
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
        $appointment->load(['customer.appointments', 'customer.primaryChannel', 'conversation.channel', 'channel', 'service', 'salesDocuments.correctsDocument:id,document_number,issued_at,type', 'salesDocuments.correction:id,document_number,corrects_document_id,type', 'workspace']);

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
        // payment_status supports fully-custom workspace keys (checked
        // against the CURRENT workspace's PaymentStatus rows); status is
        // the fixed App\Enums\AppointmentStatus set — appointments don't
        // have a workspace-editable status list.
        $data = $request->validate([
            'service_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string|max:2000',
            'appointment_date' => 'sometimes|date',
            'start_time' => 'sometimes',
            'duration_minutes' => 'sometimes|integer|min:5',
            'price' => 'nullable|numeric|min:0',
            'deposit_amount' => 'sometimes|numeric|min:0',
            'amount_paid' => 'sometimes|numeric|min:0',
            'payment_status' => ['sometimes', 'string', Rule::exists('payment_statuses', 'key')->where('workspace_id', $appointment->workspace_id)],
            'status' => ['sometimes', Rule::enum(AppointmentStatus::class)],
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
        // sales_documents.appointment_id is nullOnDelete, not cascade —
        // deleting an appointment with issued financial documents would
        // silently orphan them (severing the audit trail) instead of
        // blocking outright.
        abort_if($appointment->salesDocuments()->exists(), 422, 'Termina z izdanimi dokumenti ni mogoče izbrisati.');

        $appointment->delete();

        return redirect()->route('appointments.index')->with('success', 'Termin izbrisan.');
    }
}
