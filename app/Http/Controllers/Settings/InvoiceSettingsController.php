<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\InvoiceSettings;
use App\Models\WorkspaceMember;
use App\Services\Invoicing\SalesDocumentCalculationService;
use App\Services\Invoicing\SalesDocumentPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * "Nastavitve → Računi in plačila" — owner-only, matching the
 * WorkspaceMember::isOwnerOf() gate used by Settings\BillingController.
 * Numbering counters (invoice_next_number/proforma_next_number) are
 * deliberately NOT editable here beyond the prefix + a one-time "last
 * issued number" seed on first save — see update(). Once a workspace has
 * issued a document, the counter only ever moves forward via
 * SalesDocumentNumberingService.
 */
class InvoiceSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        $workspace = $request->user()->currentWorkspace;
        $settings = InvoiceSettings::forWorkspace($workspace->id);

        return Inertia::render('Settings/Invoicing', [
            'isOwner' => WorkspaceMember::isOwnerOf($request->user(), $workspace->id),
            'settings' => $settings,
            'logoUrl' => $settings->logo_path ? Storage::disk('public')->url($settings->logo_path) : null,
            'nextInvoicePreview' => $settings->nextNumberPreview('invoice'),
            'nextProformaPreview' => $settings->nextNumberPreview('proforma'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless(WorkspaceMember::isOwnerOf($request->user(), $workspace->id), 403, 'Samo lastnik delovnega prostora lahko ureja nastavitve računov.');

        $settings = InvoiceSettings::forWorkspace($workspace->id);

        $data = $request->validate([
            'company_name' => 'nullable|string|max:255',
            'address_line' => 'nullable|string|max:255',
            'postal_code' => 'nullable|string|max:20',
            'city' => 'nullable|string|max:120',
            'country' => 'nullable|string|max:120',
            'tax_number' => 'nullable|string|max:60',
            'vat_registered' => 'boolean',
            'prices_include_vat' => 'boolean',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:60',
            'iban' => 'nullable|string|max:40',
            'bank_name' => 'nullable|string|max:120',
            'default_payment_deadline_days' => 'required|integer|min:0|max:365',
            'place_of_issue' => 'nullable|string|max:120',
            'footer_text' => 'nullable|string|max:2000',
            'vat_exempt_note' => 'nullable|string|max:1000',
            'invoice_prefix' => 'required|string|max:30',
            'proforma_prefix' => 'required|string|max:30',
            // Only meaningful the first time a workspace configures
            // numbering (e.g. migrating from another tool mid-year) — once
            // documents have been issued this would let an owner rewind the
            // counter, so we only ever accept a value >= what's already
            // there.
            'invoice_last_issued_number' => 'nullable|integer|min:0',
            'proforma_last_issued_number' => 'nullable|integer|min:0',
        ]);

        if (array_key_exists('invoice_last_issued_number', $data) && $data['invoice_last_issued_number'] !== null) {
            $data['invoice_next_number'] = max($settings->invoice_next_number, $data['invoice_last_issued_number'] + 1);
        }
        unset($data['invoice_last_issued_number']);

        if (array_key_exists('proforma_last_issued_number', $data) && $data['proforma_last_issued_number'] !== null) {
            $data['proforma_next_number'] = max($settings->proforma_next_number, $data['proforma_last_issued_number'] + 1);
        }
        unset($data['proforma_last_issued_number']);

        $settings->update($data);

        return back()->with('success', 'Nastavitve računov posodobljene.');
    }

    public function updateLogo(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless(WorkspaceMember::isOwnerOf($request->user(), $workspace->id), 403, 'Samo lastnik delovnega prostora lahko ureja nastavitve računov.');

        $request->validate([
            'logo' => 'required|image|mimes:png,jpg,jpeg,webp|max:2048',
        ]);

        $settings = InvoiceSettings::forWorkspace($workspace->id);

        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
        }

        $settings->update(['logo_path' => $request->file('logo')->store('invoice-logos', 'public')]);

        return back()->with('success', 'Logotip posodobljen.');
    }

    public function destroyLogo(Request $request): RedirectResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless(WorkspaceMember::isOwnerOf($request->user(), $workspace->id), 403, 'Samo lastnik delovnega prostora lahko ureja nastavitve računov.');

        $settings = InvoiceSettings::forWorkspace($workspace->id);

        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
            $settings->update(['logo_path' => null]);
        }

        return back();
    }

    /**
     * A fully synthetic sample document — never touches
     * invoice_next_number/proforma_next_number and is never persisted to
     * disk or the database, only streamed directly.
     */
    public function preview(Request $request, SalesDocumentPdfService $pdf, SalesDocumentCalculationService $calculationService): HttpResponse
    {
        $workspace = $request->user()->currentWorkspace;
        abort_unless(WorkspaceMember::isOwnerOf($request->user(), $workspace->id), 403);

        $settings = InvoiceSettings::forWorkspace($workspace->id);
        $type = $request->get('type') === 'proforma' ? 'proforma' : 'invoice';

        $calculation = $calculationService->calculate([
            ['description' => 'Svetovalna ura', 'quantity' => 2, 'unit' => 'h', 'unit_price' => 45, 'vat_rate' => 22],
            ['description' => 'Materiali', 'quantity' => 1, 'unit' => 'kos', 'unit_price' => 15, 'vat_rate' => 22],
        ], $settings->vat_registered, $settings->prices_include_vat);

        $lineItems = $calculation['lines'];
        $subtotal = $calculation['subtotal'];
        $vatTotal = $calculation['vat_total'];
        $total = $calculation['total'];

        $seller = [
            'company_name' => $settings->company_name ?: 'Vaše podjetje d.o.o.',
            'address_line' => $settings->address_line ?: 'Vzorčna ulica 1',
            'postal_code' => $settings->postal_code ?: '1000',
            'city' => $settings->city ?: 'Ljubljana',
            'country' => $settings->country ?: 'Slovenija',
            'tax_number' => $settings->tax_number,
            'email' => $settings->email,
            'phone' => $settings->phone,
            'iban' => $settings->iban ?: 'SI56 0000 0000 0000 000',
            'bank_name' => $settings->bank_name,
            'place_of_issue' => $settings->place_of_issue ?: $settings->city,
            'footer_text' => $settings->footer_text,
            'logo_url' => $settings->logo_path ? Storage::disk('public')->url($settings->logo_path) : null,
        ];

        $dueDate = now()->addDays($settings->default_payment_deadline_days)->format('d.m.Y');
        $documentNumber = $settings->nextNumberPreview($type);

        $qrDataUri = $settings->iban ? $pdf->renderUpnQr([
            'recipient_iban' => $seller['iban'],
            'recipient_city' => $seller['city'],
            'recipient_name' => $seller['company_name'],
            'recipient_street_address' => $seller['address_line'],
            'recipient_reference' => null,
            'amount' => $total,
            'payment_purpose' => "Plačilo po {$documentNumber}",
            'payment_due_date' => now()->addDays($settings->default_payment_deadline_days)->format('Y-m-d'),
        ]) : null;

        $bytes = $pdf->render([
            'type' => $type,
            'type_label' => $type === 'proforma' ? 'Predračun' : 'Račun',
            'document_number' => $documentNumber,
            'issued_at' => now()->format('d.m.Y'),
            'service_date' => null,
            'due_date' => $dueDate,
            'currency' => 'EUR',
            'vat_registered' => $settings->vat_registered,
            'prices_include_vat' => $settings->prices_include_vat,
            'vat_exempt_note' => $settings->vat_exempt_note,
            'seller' => $seller,
            'customer' => [
                'name' => 'Vzorčna stranka d.o.o.',
                'address_line' => 'Testna cesta 5',
                'postal_code' => '2000',
                'city' => 'Maribor',
                'tax_number' => null,
            ],
            'line_items' => $lineItems,
            'tax_breakdown' => $calculation['tax_breakdown'],
            'subtotal' => $subtotal,
            'vat_total' => $vatTotal,
            'total' => $total,
            'payment' => [
                'iban' => $seller['iban'],
                'purpose' => "Plačilo po {$documentNumber}",
            ],
            'qr_data_uri' => $qrDataUri,
        ]);

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="predogled.pdf"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
