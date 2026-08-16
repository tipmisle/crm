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
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_default' => 'boolean',
            'is_deposit_default' => 'boolean',
            'is_outstanding' => 'boolean',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    /** Keys flagged as "not yet fully paid" — used for the deposits-outstanding dashboard stat. */
    public static function outstandingKeys(): array
    {
        return static::query()->where('is_outstanding', true)->pluck('key')->all();
    }

    /** The status a new order/appointment with no deposit starts as. */
    public static function defaultKey(): ?string
    {
        return static::query()->where('is_default', true)->value('key')
            ?? static::query()->ordered()->value('key');
    }

    /** The status a new order/appointment with a deposit > 0 starts as — falls back to defaultKey() if no status is flagged as the deposit default (e.g. a business that doesn't accept deposits at all). */
    public static function depositDefaultKey(): ?string
    {
        return static::query()->where('is_deposit_default', true)->value('key')
            ?? static::defaultKey();
    }
}
