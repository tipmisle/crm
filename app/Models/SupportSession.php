<?php

namespace App\Models;

use App\Enums\SupportAccessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A live, bounded window in which a specific platform admin is operating
 * against a specific workspace under a specific grant/scope. Every
 * protected read of workspace content during "support mode" must go
 * through App\Support\SupportSessionManager, which re-checks the
 * underlying grant on every request rather than trusting this row alone.
 */
class SupportSession extends Model
{
    protected $fillable = [
        'support_access_grant_id',
        'workspace_id',
        'admin_user_id',
        'scope',
        'started_at',
        'expires_at',
        'ended_at',
        'ended_reason',
    ];

    protected function casts(): array
    {
        return [
            'scope' => SupportAccessScope::class,
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function grant(): BelongsTo
    {
        return $this->belongsTo(SupportAccessGrant::class, 'support_access_grant_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function adminUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_user_id');
    }

    public function isActive(): bool
    {
        return $this->ended_at === null && $this->expires_at->isFuture();
    }
}
