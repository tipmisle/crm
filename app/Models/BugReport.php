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
            // Appointment notes (see docs/data-security.md). `subject` is
            // also encrypted — the admin UI only ever filters by `status`,
            // never by subject, so there's no SQL-search need to keep it
            // plain. Existing plaintext rows are migrated by
            // `security:encrypt-sensitive-data` — see
            // App\Console\Commands\EncryptSensitiveData.
            'subject' => 'encrypted',
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
