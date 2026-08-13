<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'user_id',
        'subject_type',
        'subject_id',
        'type',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public static function record(string $type, string $description, ?Model $subject = null, array $metadata = []): self
    {
        return self::create([
            'workspace_id' => auth()->user()?->current_workspace_id,
            'user_id' => auth()->id(),
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->id,
            'type' => $type,
            'description' => $description,
            'metadata' => $metadata,
        ]);
    }
}
