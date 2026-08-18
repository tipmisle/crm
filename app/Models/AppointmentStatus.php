<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A workspace-owned, fully editable appointment status (rename/recolor/
 * reorder/add/remove) — replaces the fixed App\Enums\AppointmentStatus as
 * the source of truth for Appointment.status. `key` is generated once at
 * creation and never changes on rename, so existing appointments stay
 * valid across a relabel. See App\Services\WorkspaceStatusDefaults for the
 * seeded starting set and docs on why is_completed/is_cancelled/is_no_show/
 * is_refunded exist (they replace hardcoded enum-case checks in revenue/
 * dashboard/upcoming logic).
 */
class AppointmentStatus extends Model
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
        'is_completed',
        'is_cancelled',
        'is_no_show',
        'is_refunded',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_default' => 'boolean',
            'is_completed' => 'boolean',
            'is_cancelled' => 'boolean',
            'is_no_show' => 'boolean',
            'is_refunded' => 'boolean',
        ];
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class, 'status', 'key');
    }

    /**
     * Keys that should be excluded from "active"/"upcoming" appointment
     * queries — anything flagged completed, cancelled, no-show, or
     * refunded. See Customer::upcomingAppointment(), TodayController.
     */
    public static function openExclusionKeys(): array
    {
        return static::query()
            ->where(fn (Builder $q) => $q->where('is_completed', true)
                ->orWhere('is_cancelled', true)
                ->orWhere('is_no_show', true)
                ->orWhere('is_refunded', true))
            ->pluck('key')
            ->all();
    }

    public static function completedKeys(): array
    {
        return static::query()->where('is_completed', true)->pluck('key')->all();
    }

    public static function cancelledKeys(): array
    {
        return static::query()->where('is_cancelled', true)->pluck('key')->all();
    }

    public static function noShowKeys(): array
    {
        return static::query()->where('is_no_show', true)->pluck('key')->all();
    }

    /**
     * Keys flagged refunded — a payment was taken and is being returned,
     * distinct from is_cancelled (never paid). Excluded from revenue math
     * alongside cancelledKeys()/noShowKeys() — see RevenueStatsService.
     */
    public static function refundedKeys(): array
    {
        return static::query()->where('is_refunded', true)->pluck('key')->all();
    }

    /**
     * The status a newly created appointment starts as. Falls back to the
     * first status by sort_order if nothing is flagged default (shouldn't
     * happen given seeding, but a workspace could theoretically unflag
     * every row).
     */
    public static function defaultKey(): ?string
    {
        return static::query()->where('is_default', true)->value('key')
            ?? static::query()->ordered()->value('key');
    }
}
