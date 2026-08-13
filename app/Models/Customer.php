<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Customer extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'full_name',
        'email',
        'phone',
        'notes',
        'tags',
        'primary_channel_id',
        'first_contacted_at',
        'last_interaction_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'first_contacted_at' => 'datetime',
            'last_interaction_at' => 'datetime',
        ];
    }

    public function identities(): HasMany
    {
        return $this->hasMany(CustomerIdentity::class);
    }

    public function primaryChannel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'primary_channel_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function followUps(): MorphMany
    {
        return $this->morphMany(FollowUp::class, 'followable');
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    public function lifetimeSpend(): float
    {
        return (float) $this->orders()->sum('amount_paid');
    }

    public function openOrdersCount(): int
    {
        $openStatuses = array_map(
            fn (OrderStatus $s) => $s->value,
            array_filter(
                OrderStatus::board(),
                fn (OrderStatus $s) => ! in_array($s, [OrderStatus::Completed, OrderStatus::Cancelled], true)
            )
        );

        return $this->orders()->whereIn('status', $openStatuses)->count();
    }

    public function completedOrdersCount(): int
    {
        return $this->orders()->where('status', OrderStatus::Completed->value)->count();
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->full_name));
        $letters = array_map(fn ($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2));

        return mb_strtoupper(implode('', $letters));
    }
}
