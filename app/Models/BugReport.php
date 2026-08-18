<?php

namespace App\Models;

use App\Enums\BugReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BugReport extends Model
{
    protected $fillable = [
        'workspace_id',
        'user_id',
        'subject',
        'message',
        'page_url',
        'status',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BugReportStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
