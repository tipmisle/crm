<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One row per workspace — the "RAČUNI IN PLAČILA" settings for the
 * customer-invoicing module: seller/company details, VAT status, IBAN, and
 * the two independent numbering counters (invoice vs. proforma). See
 * App\Services\Invoicing\SalesDocumentNumberingService for how
 * *_next_number is locked and incremented at issue time — nothing here
 * should ever increment those columns directly.
 */
class InvoiceSettings extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'company_name',
        'address_line',
        'postal_code',
        'city',
        'country',
        'tax_number',
        'vat_registered',
        'prices_include_vat',
        'email',
        'phone',
        'iban',
        'bank_name',
        'default_payment_deadline_days',
        'place_of_issue',
        'footer_text',
        'vat_exempt_note',
        'logo_path',
        'invoice_prefix',
        'invoice_next_number',
        'proforma_prefix',
        'proforma_next_number',
        'storno_prefix',
        'storno_next_number',
    ];

    protected function casts(): array
    {
        return [
            'vat_registered' => 'boolean',
            'prices_include_vat' => 'boolean',
            'default_payment_deadline_days' => 'integer',
            'invoice_next_number' => 'integer',
            'proforma_next_number' => 'integer',
            'storno_next_number' => 'integer',
        ];
    }

    /**
     * The DB columns have sensible defaults (see the migration), but
     * Eloquent's create() doesn't read them back into the in-memory model
     * after an INSERT — so callers must go through here rather than
     * `InvoiceSettings::firstOrCreate(['workspace_id' => ...])` directly,
     * or a freshly-created row would show blank prefixes/next-numbers until
     * the next full reload from the DB.
     */
    public static function forWorkspace(int $workspaceId): self
    {
        return static::firstOrCreate(['workspace_id' => $workspaceId], [
            'invoice_prefix' => Carbon::now()->format('Y').'-',
            'invoice_next_number' => 1,
            'proforma_prefix' => 'P-'.Carbon::now()->format('Y').'-',
            'proforma_next_number' => 1,
            'storno_prefix' => 'S-'.Carbon::now()->format('Y').'-',
            'storno_next_number' => 1,
            'default_payment_deadline_days' => 8,
            'prices_include_vat' => true,
        ]);
    }

    public function isConfigured(): bool
    {
        return filled($this->company_name) && filled($this->iban);
    }

    /**
     * Read-only preview of the next number for the given type — never
     * mutates invoice_next_number/proforma_next_number. Used by the
     * settings page and the order-side "create document" form so opening
     * either never consumes a number; only
     * SalesDocumentNumberingService::issueNumber() does that.
     */
    public function nextNumberPreview(string $type): string
    {
        return match ($type) {
            'proforma' => $this->proforma_prefix.$this->proforma_next_number,
            'storno' => $this->storno_prefix.$this->storno_next_number,
            default => $this->invoice_prefix.$this->invoice_next_number,
        };
    }
}
