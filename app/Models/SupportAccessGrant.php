<?php

namespace App\Models;

use App\Enums\SupportAccessScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A workspace owner's explicit, time-boxed permission for Beležka support to
 * access their workspace. This is a *permission record*, not an active
 * session — an admin must still explicitly start a SupportSession against a
 * valid grant, and every request during that session re-verifies the grant.
 */
class SupportAccessGrant extends Model
{
    protected $fillable = [
        'workspace_id',
        'granted_by_user_id',
        'granted_at',
        'expires_at',
        'revoked_at',
        'scope',
    ];

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
            'scope' => SupportAccessScope::class,
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function grantedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by_user_id');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(SupportSession::class);
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return ! $this->isRevoked() && ! $this->isExpired();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('expires_at', '>', now());
    }
}
