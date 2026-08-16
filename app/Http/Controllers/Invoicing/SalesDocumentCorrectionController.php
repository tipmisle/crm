<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\InvoiceSettings;
use App\Models\SalesDocument;
use App\Services\Invoicing\SalesDocumentNumberingService;
use App\Services\Invoicing\SalesDocumentPdfService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * V1 issued-document correction: void a proforma outright ("Prekliči
 * predračun" — it was never a final tax document, so no correction
 * document is needed) or fully reverse an issued invoice with a storno
 * ("Storniraj račun" — a brand-new immutable SalesDocument that mirrors
 * and negates the original's persisted snapshot, per
 * App\Models\SalesDocument::MUTABLE_AFTER_CREATE / booted()). Neither
 * action ever edits or deletes the original document.
 */
class SalesDocumentCorrectionController extends Controller
{
    public function cancelProforma(Request $request, SalesDocument $document): RedirectResponse
    {
        abort_unless($document->workspace_id === $request->user()->current_workspace_id, 404);
        abort_unless($document->canBeCancelled(), 422, 'Ta predračun ni mogoče preklicati.');

        $document->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        $document->loadMissing(['order', 'appointment']);
        $subject = $document->order ?? $document->appointment;

        ActivityLog::record(
            'sales_document_cancelled',
            "Predračun {$document->document_number} preklican",
            $subject
        );

        return back()->with('success', "Predračun {$document->document_number} je preklican.");
    }

    public function storno(
        Request $request,
        SalesDocument $document,
        SalesDocumentNumberingService $numbering,
        SalesDocumentPdfService $pdfService,
    ): RedirectResponse {
        abort_unless($document->workspace_id === $request->user()->current_workspace_id, 404);
        abort_unless($document->canBeStornoed(), 422, 'Ta račun ni mogoče stornirati.');

        $data = $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $workspace = $request->user()->currentWorkspace;
        $settings = InvoiceSettings::forWorkspace($workspace->id);

        // Reverse EVERY figure from the original's own persisted snapshot —
        // never recomputed from the current Order/Appointment/Customer/
        // InvoiceSettings, which may have changed since issuance.
        $reversedLineItems = collect($document->line_items_snapshot)->map(fn (array $item) => [
            'description' => $item['description'],
            'quantity' => $item['quantity'],
            'unit' => $item['unit'] ?? null,
            'unit_price' => -1 * (float) $item['unit_price'],
            'vat_rate' => $item['vat_rate'] ?? 0,
            'net' => -1 * (float) ($item['net'] ?? 0),
            'vat' => -1 * (float) ($item['vat'] ?? 0),
            'gross' => -1 * (float) ($item['gross'] ?? $item['line_total'] ?? 0),
            'line_total' => -1 * (float) ($item['line_total'] ?? 0),
        ])->all();

        $paymentSnapshot = [
            'method' => 'bank_transfer',
            'iban' => $document->payment_snapshot['iban'] ?? null,
            'due_date' => null,
            'notes' => "Storno računa {$document->document_number} z dne {$document->issued_at->format('d.m.Y')}. Razlog: {$data['reason']}",
        ];

        $correction = DB::transaction(function () use ($document, $settings, $numbering, $reversedLineItems, $paymentSnapshot, $data, $request) {
            // Re-lock and re-check under the transaction: the unique index
            // on corrects_document_id is the hard backstop, but this closes
            // the race window before it ever reaches the DB.
            /** @var SalesDocument $locked */
            $locked = SalesDocument::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->canBeStornoed(), 422, 'Ta račun ni mogoče stornirati.');

            $issued = $numbering->issueNumber($settings, 'storno');

            $subtotal = -1 * (float) $locked->subtotal;
            $vatTotal = -1 * (float) $locked->vat_total;
            $total = -1 * (float) $locked->total;

            $storno = SalesDocument::create([
                'workspace_id' => $locked->workspace_id,
                'order_id' => $locked->order_id,
                'appointment_id' => $locked->appointment_id,
                'customer_id' => $locked->customer_id,
                'corrects_document_id' => $locked->id,
                'type' => 'storno',
                'source' => 'issued',
                'status' => 'issued',
                'prefix' => $issued['prefix'],
                'sequence_number' => $issued['number'],
                'document_number' => $issued['prefix'].$issued['number'],
                'issued_at' => now(),
                'currency' => $locked->currency,
                'vat_registered' => $locked->vat_registered,
                'subtotal' => $subtotal,
                'vat_total' => $vatTotal,
                'total' => $total,
                'seller_snapshot' => $locked->seller_snapshot,
                'customer_snapshot' => $locked->customer_snapshot,
                'line_items_snapshot' => $reversedLineItems,
                'payment_snapshot' => $paymentSnapshot,
                'cancellation_reason' => $data['reason'],
                'created_by' => $request->user()->id,
            ]);

            $locked->update(['status' => 'reversed', 'cancelled_at' => now()]);

            return $storno;
        });

        $sellerSnapshot = $correction->seller_snapshot;

        $bytes = $pdfService->render([
            'type' => $correction->type,
            'type_label' => $correction->typeLabel(),
            'document_number' => $correction->document_number,
            'issued_at' => $correction->issued_at->format('d.m.Y'),
            'service_date' => null,
            'due_date' => null,
            'currency' => $correction->currency,
            'vat_registered' => $correction->vat_registered,
            'prices_include_vat' => $settings->prices_include_vat,
            'vat_exempt_note' => $settings->vat_exempt_note,
            'seller' => array_merge($sellerSnapshot, [
                'logo_url' => $sellerSnapshot['logo_path'] ? Storage::disk('public')->url($sellerSnapshot['logo_path']) : null,
            ]),
            'customer' => $correction->customer_snapshot,
            'line_items' => $correction->line_items_snapshot,
            'tax_breakdown' => [],
            'subtotal' => $correction->subtotal,
            'vat_total' => $correction->vat_total,
            'total' => $correction->total,
            'payment' => [
                'iban' => $correction->payment_snapshot['iban'] ?? null,
                'purpose' => "Storno računa {$document->document_number}",
            ],
            'qr_data_uri' => null,
            'is_correction' => true,
            'corrects_document_number' => $document->document_number,
            'corrects_issued_at' => $document->issued_at->format('d.m.Y'),
            'correction_reason' => $data['reason'],
        ]);

        $pdfPath = "invoices/{$workspace->id}/".Str::random(40).'.pdf';
        Storage::disk('local')->put($pdfPath, $bytes);
        $correction->update(['pdf_path' => $pdfPath]);

        $document->loadMissing(['order', 'appointment']);
        $subject = $document->order ?? $document->appointment;

        ActivityLog::record(
            'sales_document_storno_issued',
            "Izdan storno {$correction->document_number} za račun {$document->document_number}",
            $subject
        );

        return back()->with('success', "Storno {$correction->document_number} je izdan.");
    }
}
