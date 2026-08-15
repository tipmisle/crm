<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class FollowUp extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'followable_type',
        'followable_id',
        'note',
        'due_at',
        'notified_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            // Application-encrypted — see docs/data-security.md.
            'note' => 'encrypted',
            'due_at' => 'datetime',
            'notified_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function followable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->pending()->where('due_at', '<', now());
    }

    public function scopeDueToday(Builder $query): Builder
    {
        return $query->pending()->whereDate('due_at', now()->toDateString());
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
