<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A workspace-owned, fully editable payment status — shared by both Order
 * and Appointment (see the migration for why this isn't two lists).
 * Replaces the fixed App\Enums\PaymentStatus as the source of truth.
 */
class PaymentStatus extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'key',
        'label',
        'color',
        'bg',
        'sort_order',
        'is_default',
        'is_deposit_default',
        'is_outstanding',
        'is_paid',
        'is_refunded',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_default' => 'boolean',
            'is_deposit_default' => 'boolean',
            'is_outstanding' => 'boolean',
            'is_paid' => 'boolean',
            'is_refunded' => 'boolean',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /** See OrderStatus::scoped() for why this exists. */
    private static function scoped(?int $workspaceId): Builder
    {
        return $workspaceId === null
            ? static::query()
            : static::withoutGlobalScopes()->where('workspace_id', $workspaceId);
    }

    /** Keys flagged as "not yet fully paid" — used for the deposits-outstanding dashboard stat. */
    public static function outstandingKeys(?int $workspaceId = null): array
    {
        return static::scoped($workspaceId)->where('is_outstanding', true)->pluck('key')->all();
    }

    /** The status a new order/appointment with no deposit starts as. */
    public static function defaultKey(?int $workspaceId = null): ?string
    {
        return static::scoped($workspaceId)->where('is_default', true)->value('key')
            ?? static::scoped($workspaceId)->ordered()->value('key');
    }

    /** The status a new order/appointment with a deposit > 0 starts as — falls back to defaultKey() if no status is flagged as the deposit default (e.g. a business that doesn't accept deposits at all). */
    public static function depositDefaultKey(?int $workspaceId = null): ?string
    {
        return static::scoped($workspaceId)->where('is_deposit_default', true)->value('key')
            ?? static::defaultKey($workspaceId);
    }
}
