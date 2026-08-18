<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

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
        'delivery_method',
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

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
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

    /**
     * Resolves by BOTH `key` and this order's own workspace_id — a key-only
     * match would let an order resolve another workspace's status row with
     * the same key in an unauthenticated context (job/CLI/queue), where
     * BelongsToWorkspace's global scope doesn't apply. Lazy-access only
     * (see isOverdue()): the `where` below binds to $this->workspace_id at
     * call time, which is correct for lazy access on a real, hydrated
     * instance but would be null on the empty model Eloquent builds eager-
     * load queries from — do not eager-load this via with()/load().
     */
    public function orderStatus(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'status', 'key')
            ->withoutGlobalScopes()
            ->where('order_statuses.workspace_id', $this->workspace_id);
    }

    /** See orderStatus() for why this is scoped by workspace_id as well as key, and why it must stay lazy-only. */
    public function paymentStatusRecord(): BelongsTo
    {
        return $this->belongsTo(PaymentStatus::class, 'payment_status', 'key')
            ->withoutGlobalScopes()
            ->where('payment_statuses.workspace_id', $this->workspace_id);
    }

    public function isOverdue(): bool
    {
        // due_date is a calendar date, not an instant — "past" means
        // strictly before today's date in the workspace's own timezone,
        // not before the current instant in server (UTC) time.
        $timezone = $this->workspace?->timezone ?? config('app.timezone');

        return $this->due_date
            && $this->due_date->lt(Carbon::today($timezone))
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
