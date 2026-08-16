<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Order extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'order_number',
        'customer_id',
        'conversation_id',
        'channel_id',
        'assigned_user_id',
        'catalog_item_id',
        'title',
        'description',
        'due_date',
        'due_time',
        'price',
        'deposit_amount',
        'amount_paid',
        'payment_status',
        'status',
        'internal_notes',
        'customer_notes',
        'tags',
        'tracking_number',
        'tracking_url',
        'shipped_at',
    ];

    protected function casts(): array
    {
        return [
            // status/payment_status are plain strings referencing a
            // workspace-editable OrderStatus/PaymentStatus row's `key` —
            // see orderStatus()/paymentStatusRecord() below and
            // docs on WorkspaceStatusDefaults. No enum cast: the set of
            // valid values is per-workspace, not fixed.
            'due_date' => 'date',
            'price' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'tags' => 'array',
            'shipped_at' => 'datetime',
            // Application-encrypted — see docs/data-security.md.
            'description' => 'encrypted',
            'internal_notes' => 'encrypted',
            'customer_notes' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Order $order) {
            if (! $order->order_number) {
                $order->forceFill([
                    'order_number' => 'BC-'.str_pad((string) $order->id, 4, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'catalog_item_id');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class)->latest();
    }

    public function salesDocuments(): HasMany
    {
        return $this->hasMany(SalesDocument::class)->latest('issued_at')->latest('id');
    }

    public function followUps(): MorphMany
    {
        return $this->morphMany(FollowUp::class, 'followable');
    }

    public function orderStatus(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'status', 'key');
    }

    public function paymentStatusRecord(): BelongsTo
    {
        return $this->belongsTo(PaymentStatus::class, 'payment_status', 'key');
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && ! ($this->orderStatus?->is_completed || $this->orderStatus?->is_cancelled);
    }

    // Carbon's default JSON output ("2026-08-20T00:00:00.000000Z") doesn't
    // match what an <input type="date"> can bind to — it needs plain Y-m-d.
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    public function balanceDue(): float
    {
        return max(0, (float) $this->price - (float) $this->amount_paid);
    }
}
