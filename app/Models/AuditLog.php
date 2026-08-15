<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Append-only platform security/admin audit trail. Distinct from
 * ActivityLog (a workspace-facing, user-editable activity feed) — this
 * table backs Admin > Audit log and must never contain private customer
 * content (message bodies, notes, attachments). Log identifiers only.
 *
 * @see docs/admin-security.md
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'actor_user_id',
        'actor_type',
        'workspace_id',
        'event',
        'target_type',
        'target_id',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public static function record(
        string $event,
        ?Request $request = null,
        ?int $workspaceId = null,
        ?Model $target = null,
        array $metadata = [],
        ?User $actor = null,
    ): self {
        $actor = $actor ?? (auth()->check() ? auth()->user() : null);

        return self::create([
            'actor_user_id' => $actor?->id,
            'actor_type' => $actor ? 'user' : 'system',
            'workspace_id' => $workspaceId,
            'event' => $event,
            'target_type' => $target ? $target::class : null,
            'target_id' => $target?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata,
        ]);
    }
}
