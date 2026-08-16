<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Predračun (proforma) or Račun (invoice), either issued by Beležka
 * (source=issued, numbered via InvoiceSettings/SalesDocumentNumberingService)
 * or uploaded from an external tool like Minimax/Pantheon/Apollo
 * (source=external — never touches Beležka's numbering, see
 * external_document_number vs. document_number).
 *
 * Issued rows are immutable snapshots: everything needed to reproduce the
 * document (seller/customer/line-items/payment) is copied into the
 * *_snapshot columns at issue time, so later edits to the Order, Customer,
 * or InvoiceSettings can never change a document that's already out.
 */
class SalesDocument extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'order_id',
        'appointment_id',
        'customer_id',
        'type',
        'source',
        'prefix',
        'sequence_number',
        'document_number',
        'external_document_number',
        'issued_at',
        'service_date',
        'due_date',
        'currency',
        'vat_registered',
        'subtotal',
        'vat_total',
        'total',
        'seller_snapshot',
        'customer_snapshot',
        'line_items_snapshot',
        'payment_snapshot',
        'pdf_path',
        'sent_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'service_date' => 'date',
            'due_date' => 'date',
            'vat_registered' => 'boolean',
            'subtotal' => 'decimal:2',
            'vat_total' => 'decimal:2',
            'total' => 'decimal:2',
            'sent_at' => 'datetime',
            // Snapshots may contain customer PII (name, address, tax id) —
            // see docs/data-security.md.
            'seller_snapshot' => 'encrypted:array',
            'customer_snapshot' => 'encrypted:array',
            'line_items_snapshot' => 'encrypted:array',
            'payment_snapshot' => 'encrypted:array',
        ];
    }

    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isExternal(): bool
    {
        return $this->source === 'external';
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    public function displayNumber(): string
    {
        return $this->isExternal()
            ? ($this->external_document_number ?: 'Zunanji dokument')
            : (string) $this->document_number;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            'proforma' => 'Predračun',
            'invoice' => 'Račun',
            default => 'Drugo',
        };
    }
}
