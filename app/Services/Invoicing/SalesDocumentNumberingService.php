<?php

namespace App\Services\Invoicing;

use App\Models\InvoiceSettings;
use Illuminate\Support\Facades\DB;

/**
 * The single place that consumes an invoice/proforma number. Must always be
 * called from inside the same DB transaction that creates the SalesDocument
 * row (see Invoicing\SalesDocumentController::store()) — the row lock taken
 * here (`lockForUpdate`) serializes concurrent issuance for a workspace, and
 * the unique index on sales_documents(workspace_id, type, prefix,
 * sequence_number) is the belt-and-suspenders backstop.
 *
 * Never call this for a preview, a draft, or an external upload — those
 * must only ever read InvoiceSettings::nextNumberPreview(), which does not
 * touch invoice_next_number/proforma_next_number.
 */
class SalesDocumentNumberingService
{
    /**
     * @return array{prefix: string, number: int}
     */
    public function issueNumber(InvoiceSettings $settings, string $type): array
    {
        return DB::transaction(function () use ($settings, $type) {
            /** @var InvoiceSettings $locked */
            $locked = InvoiceSettings::query()->whereKey($settings->id)->lockForUpdate()->firstOrFail();

            $column = $type === 'proforma' ? 'proforma_next_number' : 'invoice_next_number';
            $prefixColumn = $type === 'proforma' ? 'proforma_prefix' : 'invoice_prefix';

            $number = $locked->$column;

            $locked->update([$column => $number + 1]);

            return ['prefix' => $locked->$prefixColumn, 'number' => $number];
        });
    }
}
