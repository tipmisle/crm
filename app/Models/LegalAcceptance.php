<?php

namespace App\Models;

use App\Enums\LegalDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\Request;

/**
 * Foundation for versioned Terms/DPA acceptance tracking — schema and
 * model only. Nothing calls record() yet: registration is not blocked on
 * acceptance until real legal documents with real versions exist (see
 * docs/data-lifecycle.md). Must never be recorded for is_demo=true users
 * if/when this is wired up.
 */
class LegalAcceptance extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'workspace_id',
        'document',
        'version',
        'accepted_at',
        'ip_address',
        'user_agent',
    ];

    protected function casts(): array
    {
        return [
            'document' => LegalDocument::class,
            'accepted_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public static function record(
        User $user,
        LegalDocument $document,
        string $version,
        ?Workspace $workspace = null,
        ?Request $request = null,
    ): self {
        return self::create([
            'user_id' => $user->id,
            'workspace_id' => $workspace?->id,
            'document' => $document,
            'version' => $version,
            'accepted_at' => now(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);
    }
}
