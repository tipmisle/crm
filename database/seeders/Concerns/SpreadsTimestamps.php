<?php

namespace Database\Seeders\Concerns;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Demo data needs activity on (almost) every day of the last ~90 days so the
 * Analytics date-range picker actually has something to show for every
 * preset — without this, every seeded row defaults to "whenever the seeder
 * ran" and every range looks identical.
 */
trait SpreadsTimestamps
{
    /**
     * $count timestamps evenly spread across the last $days days (today
     * included), shuffled so the spread doesn't correlate with the order
     * records are created in (e.g. status groups).
     *
     * @return array<int, Carbon>
     */
    private function spreadDates(int $count, int $days = 90): array
    {
        if ($count <= 0) {
            return [];
        }

        $dates = [];
        for ($i = 0; $i < $count; $i++) {
            $dayOffset = $count > 1 ? (int) round($i * ($days - 1) / ($count - 1)) : 0;
            $dates[] = Carbon::now()->subDays($days - 1 - $dayOffset)->setTime(rand(8, 20), rand(0, 59), rand(0, 59));
        }

        shuffle($dates);

        return $dates;
    }

    private function backdate(Model $model, Carbon $timestamp): void
    {
        $model->timestamps = false;
        $model->created_at = $timestamp;
        $model->updated_at = $timestamp;
        $model->save();
        $model->timestamps = true;
    }
}
