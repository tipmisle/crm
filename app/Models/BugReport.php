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
            // Free-text bug descriptions can contain customer-sensitive
            // details — encrypted at rest like Customer.notes/Order/
            // Appointment notes (see docs/data-security.md). `subject`
            // stays plain so admins can filter/search on it.
            'message' => 'encrypted',
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
