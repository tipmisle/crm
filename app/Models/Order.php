<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
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
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'payment_status' => PaymentStatus::class,
            'due_date' => 'date',
            'price' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'tags' => 'array',
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

    public function notes(): HasMany
    {
        return $this->hasMany(OrderNote::class)->latest();
    }

    public function followUps(): MorphMany
    {
        return $this->morphMany(FollowUp::class, 'followable');
    }

    public function isOverdue(): bool
    {
        return $this->due_date
            && $this->due_date->isPast()
            && ! in_array($this->status, [OrderStatus::Completed, OrderStatus::Cancelled], true);
    }

    public function balanceDue(): float
    {
        return max(0, (float) $this->price - (float) $this->amount_paid);
    }
}
