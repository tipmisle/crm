<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use LogicException;

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

    /**
     * Exposes the (server-authoritative) correction eligibility/labels to
     * the frontend so Orders/Show.vue and Appointments/Show.vue never have
     * to reimplement these rules — see canBeCancelled()/canBeStornoed().
     */
    protected $appends = ['can_be_cancelled', 'can_be_stornoed', 'status_label'];

    protected $fillable = [
        'workspace_id',
        'order_id',
        'appointment_id',
        'corrects_document_id',
        'customer_id',
        'type',
        'source',
        'status',
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
        'cancelled_at',
        'cancellation_reason',
        'created_by',
    ];

    /**
     * Every column except the small "lifecycle" set below is part of the
     * immutable issued snapshot and must never change after creation — see
     * booted(). pdf_path is set once right after create() (the PDF embeds
     * the DB-assigned document_number, so it can't be rendered before
     * insert); sent_at is set by SalesDocumentSendController; status/
     * cancelled_at/cancellation_reason are set by
     * SalesDocumentCorrectionController when a proforma is cancelled or an
     * invoice is storno'd.
     */
    private const MUTABLE_AFTER_CREATE = ['pdf_path', 'sent_at', 'status', 'cancelled_at', 'cancellation_reason'];

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
            'cancelled_at' => 'datetime',
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

    /**
     * Enforces "issued documents are never edited or deleted" at the model
     * layer, not just by omitting edit/delete routes: any attempt to
     * change a snapshot/amount/identity column after creation, or to
     * delete a row outright, throws. Only the small lifecycle fields in
     * MUTABLE_AFTER_CREATE (status, cancelled_at, cancellation_reason,
     * pdf_path, sent_at) may change post-issue.
     */
    protected static function booted(): void
    {
        static::updating(function (SalesDocument $document) {
            foreach ($document->getDirty() as $field => $value) {
                if (! in_array($field, self::MUTABLE_AFTER_CREATE, true)) {
                    throw new LogicException("SalesDocument.{$field} is part of the immutable issued snapshot and cannot be changed after creation.");
                }
            }
        });

        static::deleting(function () {
            throw new LogicException('SalesDocument rows are immutable and can never be deleted.');
        });
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

    /**
     * The original invoice this document corrects — only set on a
     * type=storno document.
     */
    public function correctsDocument(): BelongsTo
    {
        return $this->belongsTo(SalesDocument::class, 'corrects_document_id');
    }

    /**
     * The storno document that reverses this one, if any — only ever set
     * on a type=invoice document (the unique index on corrects_document_id
     * guarantees at most one).
     */
    public function correction(): HasOne
    {
        return $this->hasOne(SalesDocument::class, 'corrects_document_id');
    }

    public function isExternal(): bool
    {
        return $this->source === 'external';
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    public function isProforma(): bool
    {
        return $this->type === 'proforma';
    }

    public function isInvoice(): bool
    {
        return $this->type === 'invoice';
    }

    public function isStorno(): bool
    {
        return $this->type === 'storno';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isReversed(): bool
    {
        return $this->status === 'reversed';
    }

    public function isActive(): bool
    {
        return $this->status === 'issued';
    }

    /**
     * A proforma may be voided while it's still an active payment
     * request — natively-issued and not already cancelled.
     */
    public function canBeCancelled(): bool
    {
        return ! $this->isExternal() && $this->isProforma() && $this->isActive();
    }

    /**
     * An invoice may be storno'd once — natively-issued, still active
     * (never storno'd before), and not itself a correction document (a
     * storno is never itself reversible in V1).
     */
    public function canBeStornoed(): bool
    {
        return ! $this->isExternal() && $this->isInvoice() && $this->isActive();
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
            'storno' => 'Dobropis (storno)',
            default => 'Drugo',
        };
    }

    public function statusLabel(): ?string
    {
        return match ($this->status) {
            'cancelled' => 'Preklican',
            'reversed' => 'Storniran',
            default => null,
        };
    }

    public function getCanBeCancelledAttribute(): bool
    {
        return $this->canBeCancelled();
    }

    public function getCanBeStornoedAttribute(): bool
    {
        return $this->canBeStornoed();
    }

    public function getStatusLabelAttribute(): ?string
    {
        return $this->statusLabel();
    }
}
