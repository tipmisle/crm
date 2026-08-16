<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks a generated workspace-data export ZIP living on the private
 * 'local' disk (never public storage). Single-use and short-lived — see
 * config('retention.export_hours') and Console\Commands\PurgeExpiredExports.
 */
class WorkspaceExport extends Model
{
    protected $fillable = [
        'workspace_id',
        'requested_by_user_id',
        'disk_path',
        'status',
        'expires_at',
        'downloaded_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'downloaded_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Single-use: once downloaded, it can't be downloaded again.
     */
    public function isDownloadable(): bool
    {
        return ! $this->isExpired() && $this->downloaded_at === null;
    }
}
