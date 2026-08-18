<?php

namespace App\Http\Controllers\Invoicing;

use App\Http\Controllers\Controller;
use App\Models\SalesDocument;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Dokumenti" — a read-only, workspace-wide overview of every SalesDocument
 * (invoice/proforma/storno/external), regardless of whether it hangs off
 * an Order or an Appointment. Pure overview: issuing/cancelling/storno-ing
 * still only happens from the owning Order/Appointment (see
 * Invoicing\SalesDocumentController / SalesDocumentCorrectionController) —
 * this page never creates or mutates a document, so workspace scoping is
 * inherited entirely from SalesDocument's BelongsToWorkspace global scope.
 */
class DocumentsController extends Controller
{
    private const FILTER_KEYS = ['search', 'type', 'sent', 'source', 'issued_from', 'issued_to'];

    public function index(Request $request): Response
    {
        $timezone = $request->user()->currentWorkspace->timezone;

        $query = SalesDocument::query()->with([
            'customer:id,full_name,company_name,is_business',
            'order:id,order_number,title',
            'appointment:id,appointment_number,service_name',
            'correctsDocument:id,document_number,issued_at,type',
            'correction:id,document_number,corrects_document_id,type',
        ]);

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                    ->orWhere('external_document_number', 'like', "%{$search}%")
                    ->orWhereHas('customer', fn ($c) => $c->where('full_name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%"))
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'like', "%{$search}%"))
                    ->orWhereHas('appointment', fn ($a) => $a->where('appointment_number', 'like', "%{$search}%"));
            });
        }

        if ($type = $request->get('type')) {
            $query->where('type', $type);
        }

        if ($source = $request->get('source')) {
            $query->where('source', $source);
        }

        if ($request->get('sent') === 'sent') {
            $query->whereNotNull('sent_at');
        } elseif ($request->get('sent') === 'not_sent') {
            $query->whereNull('sent_at');
        }

        if ($issuedFrom = $request->get('issued_from')) {
            // issued_at is a real timestamp — a plain whereDate() truncates
            // using the DB/server timezone instead of the workspace's,
            // silently shifting the boundary (same class of bug fixed in
            // OrderController/AppointmentController's created_from/to).
            $query->where('issued_at', '>=', Carbon::parse($issuedFrom, $timezone)->startOfDay()->setTimezone(config('app.timezone')));
        }

        if ($issuedTo = $request->get('issued_to')) {
            $query->where('issued_at', '<=', Carbon::parse($issuedTo, $timezone)->endOfDay()->setTimezone(config('app.timezone')));
        }

        $documents = $query->orderByDesc('issued_at')->orderByDesc('id')->paginate(25)->withQueryString();

        return Inertia::render('Documents/Index', [
            'documents' => $documents,
            'filters' => $request->only(self::FILTER_KEYS),
        ]);
    }
}
