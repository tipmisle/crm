<?php

namespace App\Services\Invoicing;

use App\Models\InvoiceSettings;
use App\Models\SalesDocument;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
     * @param  array{prefix: string, number: int}|null  $override  Lets the
     *         person issuing the document override the auto-assigned
     *         prefix/number (e.g. to match a number already used outside
     *         Beležka). Still consumed under the same settings-row lock, so
     *         it's race-safe; the counter only ever moves forward from it
     *         (never backward), so a lower manual number never reopens
     *         already-issued ground.
     * @return array{prefix: string, number: int}
     */
    public function issueNumber(InvoiceSettings $settings, string $type, ?array $override = null): array
    {
        return DB::transaction(function () use ($settings, $type, $override) {
            /** @var InvoiceSettings $locked */
            $locked = InvoiceSettings::query()->whereKey($settings->id)->lockForUpdate()->firstOrFail();

            $column = match ($type) {
                'proforma' => 'proforma_next_number',
                'storno' => 'storno_next_number',
                default => 'invoice_next_number',
            };
            $prefixColumn = match ($type) {
                'proforma' => 'proforma_prefix',
                'storno' => 'storno_prefix',
                default => 'invoice_prefix',
            };

            if ($override !== null) {
                $prefix = $override['prefix'];
                $number = $override['number'];

                $taken = SalesDocument::query()
                    ->where('workspace_id', $locked->workspace_id)
                    ->where('type', $type)
                    ->where('prefix', $prefix)
                    ->where('sequence_number', $number)
                    ->exists();

                if ($taken) {
                    throw ValidationException::withMessages([
                        'document_number' => 'Ta številka je že uporabljena za drug dokument.',
                    ]);
                }

                $locked->update([$column => max($locked->$column, $number + 1)]);

                return ['prefix' => $prefix, 'number' => $number];
            }

            $number = $locked->$column;

            $locked->update([$column => $number + 1]);

            return ['prefix' => $locked->$prefixColumn, 'number' => $number];
        });
    }
}
