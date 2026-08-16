<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\InvoiceSettings;
use App\Models\Order;
use App\Models\SalesDocument;
use App\Services\Invoicing\SalesDocumentCalculationService;
use App\Services\Invoicing\SalesDocumentNumberingService;
use App\Services\Invoicing\SalesDocumentPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Issues a Predračun/Račun from an Order — "Izstavi predračun"/"Izstavi
 * račun" in the spec. Any workspace member may issue (this mirrors
 * OrderController, which has no per-action role check either — issuing
 * from an order is an operational action, not an administrative one; only
 * InvoiceSettingsController's numbering/logo/company-details edits are
 * owner-only). See docs/plans numbering & concurrency notes.
 */
class SalesDocumentController extends Controller
{
    private ?Appointment $appointment = null;

    public function createForAppointment(Request $request, Appointment $appointment): Response
    {
        $this->appointment = $appointment->load('customer');

        return $this->create($request, $this->appointmentAsOrder($appointment));
    }

    public function storeForAppointment(Request $request, Appointment $appointment): RedirectResponse
    {
        $this->appointment = $appointment->load('customer');

        return $this->store($request, $this->appointmentAsOrder($appointment));
    }

    public function create(Request $request, Order $order): Response
    {
        $order->load('customer');
        $workspace = $request->user()->currentWorkspace;
        $settings = InvoiceSettings::forWorkspace($workspace->id);

        $type = $request->get('type') === 'proforma' ? 'proforma' : 'invoice';
        $customer = $order->customer;

        return Inertia::render('Invoicing/Create', [
            'order' => $order,
            'documentableType' => $this->appointment ? 'appointment' : 'order',
            'type' => $type,
            'settingsConfigured' => $settings->isConfigured(),
            'nextNumberPreview' => $settings->nextNumberPreview($type),
            'vatRegistered' => $settings->vat_registered,
            'pricesIncludeVat' => $settings->prices_include_vat,
            'defaultDueDate' => now()->addDays($settings->default_payment_deadline_days)->format('Y-m-d'),
            'recipient' => [
                'name' => $customer?->full_name,
                'email' => $customer?->email,
                'address_line' => $customer?->address_line,
                'postal_code' => $customer?->postal_code,
                'city' => $customer?->city,
                'country' => $customer?->country,
                'tax_number' => $customer?->tax_number,
            ],
            'defaultLineItems' => [[
                'description' => $order->title,
                'quantity' => 1,
                'unit' => 'kos',
                'unit_price' => (float) $order->price,
                'vat_rate' => $settings->vat_registered ? 22 : 0,
            ]],
        ]);
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        $settings = InvoiceSettings::forWorkspace($workspace->id);

        abort_unless($settings->isConfigured(), 422, 'Najprej nastavi podatke o računih v nastavitvah delovnega prostora.');

        $data = $request->validate([
            'type' => 'required|in:proforma,invoice',
            'issued_at' => 'required|date',
            'service_date' => 'nullable|date',
            'due_date' => 'nullable|date',
            'recipient.name' => 'required|string|max:255',
            'recipient.email' => 'nullable|email|max:255',
            'recipient.address_line' => 'nullable|string|max:255',
            'recipient.postal_code' => 'nullable|string|max:20',
            'recipient.city' => 'nullable|string|max:120',
            'recipient.country' => 'nullable|string|max:120',
            'recipient.tax_number' => 'nullable|string|max:60',
            'save_recipient_to_customer' => 'boolean',
            'line_items' => 'required|array|min:1',
            'line_items.*.description' => 'required|string|max:255',
            'line_items.*.quantity' => 'required|numeric|min:0.01',
            'line_items.*.unit' => 'nullable|string|max:20',
            'line_items.*.unit_price' => 'required|numeric|min:0',
            'line_items.*.vat_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string|max:2000',
        ]);

        $customer = $order->customer;

        if (($data['save_recipient_to_customer'] ?? false) && $customer) {
            $customer->update([
                'full_name' => $data['recipient']['name'],
                'email' => $data['recipient']['email'] ?? $customer->email,
                'address_line' => $data['recipient']['address_line'] ?? null,
                'postal_code' => $data['recipient']['postal_code'] ?? null,
                'city' => $data['recipient']['city'] ?? null,
                'country' => $data['recipient']['country'] ?? null,
                'tax_number' => $data['recipient']['tax_number'] ?? null,
            ]);
        }

        $calculation = app(SalesDocumentCalculationService::class)->calculate(
            $data['line_items'],
            $settings->vat_registered,
            $settings->prices_include_vat,
        );

        $lineItems = $calculation['lines'];
        $subtotal = $calculation['subtotal'];
        $vatTotal = $calculation['vat_total'];
        $total = $calculation['total'];

        $sellerSnapshot = [
            'company_name' => $settings->company_name,
            'address_line' => $settings->address_line,
            'postal_code' => $settings->postal_code,
            'city' => $settings->city,
            'country' => $settings->country,
            'tax_number' => $settings->tax_number,
            'email' => $settings->email,
            'phone' => $settings->phone,
            'iban' => $settings->iban,
            'bank_name' => $settings->bank_name,
            'place_of_issue' => $settings->place_of_issue,
            'footer_text' => $settings->footer_text,
            'logo_path' => $settings->logo_path,
        ];

        $customerSnapshot = [
            'name' => $data['recipient']['name'],
            'email' => $data['recipient']['email'] ?? null,
            'address_line' => $data['recipient']['address_line'] ?? null,
            'postal_code' => $data['recipient']['postal_code'] ?? null,
            'city' => $data['recipient']['city'] ?? null,
            'country' => $data['recipient']['country'] ?? null,
            'tax_number' => $data['recipient']['tax_number'] ?? null,
        ];

        $paymentSnapshot = [
            'method' => 'bank_transfer',
            'iban' => $settings->iban,
            'due_date' => $data['due_date'] ?? null,
            'notes' => $data['notes'] ?? null,
        ];

        $document = DB::transaction(function () use (
            $data, $order, $workspace, $settings, $customer,
            $sellerSnapshot, $customerSnapshot, $lineItems, $paymentSnapshot,
            $subtotal, $vatTotal, $total, $request,
        ) {
            $numbering = app(SalesDocumentNumberingService::class);
            $issued = $numbering->issueNumber($settings, $data['type']);

            return SalesDocument::create([
                'workspace_id' => $workspace->id,
                'order_id' => $this->appointment ? null : $order->id,
                'appointment_id' => $this->appointment?->id,
                'customer_id' => $customer?->id,
                'type' => $data['type'],
                'source' => 'issued',
                'prefix' => $issued['prefix'],
                'sequence_number' => $issued['number'],
                'document_number' => $issued['prefix'].$issued['number'],
                'issued_at' => $data['issued_at'],
                'service_date' => $data['service_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'currency' => $workspace->currency ?? 'EUR',
                'vat_registered' => $settings->vat_registered,
                'subtotal' => $subtotal,
                'vat_total' => $vatTotal,
                'total' => $total,
                'seller_snapshot' => $sellerSnapshot,
                'customer_snapshot' => $customerSnapshot,
                'line_items_snapshot' => $lineItems,
                'payment_snapshot' => $paymentSnapshot,
                'created_by' => $request->user()->id,
            ]);
        });

        $pdfService = app(SalesDocumentPdfService::class);

        $qrDataUri = $document->payment_snapshot['iban'] ?? null
            ? $pdfService->renderUpnQr([
                'recipient_iban' => $document->payment_snapshot['iban'],
                'recipient_city' => $sellerSnapshot['city'] ?? '',
                'recipient_name' => $sellerSnapshot['company_name'] ?? null,
                'recipient_street_address' => $sellerSnapshot['address_line'] ?? null,
                'recipient_reference' => null,
                'amount' => $total,
                'payment_purpose' => "Plačilo po {$document->document_number}",
                'payment_due_date' => $document->due_date?->format('Y-m-d'),
            ])
            : null;

        $bytes = $pdfService->render([
            'type' => $document->type,
            'type_label' => $document->typeLabel(),
            'document_number' => $document->document_number,
            'issued_at' => $document->issued_at->format('d.m.Y'),
            'service_date' => $document->service_date?->format('d.m.Y'),
            'due_date' => $document->due_date?->format('d.m.Y'),
            'currency' => $document->currency,
            'vat_registered' => $document->vat_registered,
            'prices_include_vat' => $settings->prices_include_vat,
            'vat_exempt_note' => $settings->vat_exempt_note,
            'seller' => array_merge($sellerSnapshot, [
                'logo_url' => $sellerSnapshot['logo_path'] ? Storage::disk('public')->url($sellerSnapshot['logo_path']) : null,
            ]),
            'customer' => $customerSnapshot,
            'line_items' => $lineItems,
            'tax_breakdown' => $calculation['tax_breakdown'],
            'subtotal' => $subtotal,
            'vat_total' => $vatTotal,
            'total' => $total,
            'payment' => [
                'iban' => $paymentSnapshot['iban'],
                'purpose' => "Plačilo po {$document->document_number}",
            ],
            'qr_data_uri' => $qrDataUri,
        ]);

        $pdfPath = "invoices/{$workspace->id}/".Str::random(40).'.pdf';
        Storage::disk('local')->put($pdfPath, $bytes);
        $document->update(['pdf_path' => $pdfPath]);

        $subject = $this->appointment ?? $order;
        $subjectLabel = $this->appointment
            ? "termin {$this->appointment->appointment_number}"
            : "naročilo {$order->order_number}";

        ActivityLog::record(
            'sales_document_issued',
            "{$document->typeLabel()} {$document->document_number} izdan za {$subjectLabel}",
            $subject
        );

        $redirectRoute = $this->appointment ? 'appointments.show' : 'orders.show';

        return redirect()->route($redirectRoute, $subject)->with('success', "{$document->typeLabel()} {$document->document_number} izdan.");
    }

    private function appointmentAsOrder(Appointment $appointment): Order
    {
        $order = new Order([
            'customer_id' => $appointment->customer_id,
            'title' => $appointment->service_name,
            'price' => $appointment->price,
        ]);
        $order->id = $appointment->id;
        $order->order_number = $appointment->appointment_number;
        $order->setRelation('customer', $appointment->customer);

        return $order;
    }
}
