<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Order;
use App\Models\SalesDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * "Priloži obstoječ dokument" — a PDF created elsewhere (Minimax/Pantheon/
 * Apollo/...). Always source=external: prefix/sequence_number stay null,
 * so this can never consume Beležka's own invoice/proforma numbering (see
 * SalesDocumentNumberingService).
 */
class ExternalDocumentController extends Controller
{
    public function storeForAppointment(Request $request, Appointment $appointment): RedirectResponse
    {
        return $this->storeDocument($request, $appointment);
    }

    public function store(Request $request, Order $order): RedirectResponse
    {
        return $this->storeDocument($request, $order);
    }

    private function storeDocument(Request $request, Order|Appointment $subject): RedirectResponse
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:pdf|max:15360',
            'type' => 'required|in:proforma,invoice,other',
            'external_document_number' => 'nullable|string|max:100',
        ]);

        $workspace = $request->user()->currentWorkspace;
        $path = $request->file('file')->store("invoices/{$workspace->id}", 'local');

        $document = SalesDocument::create([
            'workspace_id' => $workspace->id,
            'order_id' => $subject instanceof Order ? $subject->id : null,
            'appointment_id' => $subject instanceof Appointment ? $subject->id : null,
            'customer_id' => $subject->customer_id,
            'type' => $data['type'],
            'source' => 'external',
            'external_document_number' => $data['external_document_number'] ?? null,
            'issued_at' => now(),
            'currency' => $workspace->currency ?? 'EUR',
            'pdf_path' => $path,
            'created_by' => $request->user()->id,
        ]);

        $subjectLabel = $subject instanceof Appointment
            ? "terminu {$subject->appointment_number}"
            : "naročilu {$subject->order_number}";

        ActivityLog::record(
            'sales_document_uploaded',
            "{$document->typeLabel()} (zunanji dokument) dodan {$subjectLabel}",
            $subject
        );

        return back()->with('success', 'Dokument je bil dodan.');
    }
}
