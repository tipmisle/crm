<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/**
 * Shared "how's business doing" math used by both the Today dashboard (today
 * vs. yesterday) and the Analytics page (arbitrary range vs. the equally
 * long period before it) — kept in one place so what counts as revenue/a
 * conversion never drifts between the two.
 */
class RevenueStatsService
{
    public function totalRevenue(Workspace $workspace, Carbon $start, Carbon $end): float
    {
        $orders = $workspace->orders_enabled
            ? (float) Order::whereBetween('created_at', [$start, $end])
                ->whereNotIn('status', OrderStatus::cancelledKeys())
                ->sum('price')
            : 0.0;

        $appointments = $workspace->appointments_enabled
            ? (float) Appointment::whereBetween('created_at', [$start, $end])
                ->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
                ->sum('price')
            : 0.0;

        return $orders + $appointments;
    }

    public function inquiriesCount(Carbon $start, Carbon $end): int
    {
        return Conversation::whereBetween('created_at', [$start, $end])->count();
    }

    public function bookingsCount(Workspace $workspace, Carbon $start, Carbon $end): int
    {
        $orders = $workspace->orders_enabled
            ? Order::whereBetween('created_at', [$start, $end])->count()
            : 0;

        $appointments = $workspace->appointments_enabled
            ? Appointment::whereBetween('created_at', [$start, $end])->count()
            : 0;

        return $orders + $appointments;
    }

    public function convertedCount(Workspace $workspace, Carbon $start, Carbon $end): int
    {
        $orders = $workspace->orders_enabled
            ? Order::whereBetween('created_at', [$start, $end])->whereNotNull('conversation_id')->count()
            : 0;

        $appointments = $workspace->appointments_enabled
            ? Appointment::whereBetween('created_at', [$start, $end])->whereNotNull('conversation_id')->count()
            : 0;

        return $orders + $appointments;
    }

    public function conversionRate(Workspace $workspace, Carbon $start, Carbon $end): ?float
    {
        $inquiries = $this->inquiriesCount($start, $end);
        if ($inquiries === 0) {
            return null;
        }

        return ($this->convertedCount($workspace, $start, $end) / $inquiries) * 100;
    }

    public function delta(?float $current, ?float $previous): ?float
    {
        if ($current === null || $previous === null || $previous == 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * The equally-long period immediately before $start — the default
     * "previous period" comparison basis.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    public function previousPeriod(Carbon $start, Carbon $end): array
    {
        // diffInDays() between a startOfDay() and an endOfDay() timestamp
        // (23:59:59.999999) is a hair under a whole number of days — e.g.
        // 6.9999999999884 instead of 7 — so it must be compared on the date
        // part only, or the float error silently shifts the range by a day.
        $days = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
        $prevEnd = $start->copy()->subSecond();
        $prevStart = $prevEnd->copy()->subDays($days - 1)->startOfDay();

        return [$prevStart, $prevEnd];
    }

    /**
     * A four-tile summary (value + % delta) for the given range. Pass
     * $compareStart/$compareEnd explicitly — e.g. the previous period, the
     * same range a year back, or omit both to skip comparison entirely
     * (deltas come back null so the UI can say "no comparison" honestly
     * instead of showing a number with no stated basis).
     *
     * @return array{revenue: array{value: float, delta: ?float}, inquiries: array{value: int, delta: ?float}, bookings: array{value: int, delta: ?float}, conversionRate: array{value: ?float, delta: ?float}}
     */
    public function summary(Workspace $workspace, Carbon $start, Carbon $end, ?Carbon $compareStart = null, ?Carbon $compareEnd = null): array
    {
        $hasCompare = $compareStart !== null && $compareEnd !== null;

        $currentRevenue = $this->totalRevenue($workspace, $start, $end);
        $previousRevenue = $hasCompare ? $this->totalRevenue($workspace, $compareStart, $compareEnd) : null;

        $currentInquiries = $this->inquiriesCount($start, $end);
        $previousInquiries = $hasCompare ? $this->inquiriesCount($compareStart, $compareEnd) : null;

        $currentBookings = $this->bookingsCount($workspace, $start, $end);
        $previousBookings = $hasCompare ? $this->bookingsCount($workspace, $compareStart, $compareEnd) : null;

        $currentConversion = $this->conversionRate($workspace, $start, $end);
        $previousConversion = $hasCompare ? $this->conversionRate($workspace, $compareStart, $compareEnd) : null;

        return [
            'revenue' => [
                'value' => round($currentRevenue, 2),
                'delta' => $this->delta($currentRevenue, $previousRevenue),
            ],
            'inquiries' => [
                'value' => $currentInquiries,
                'delta' => $this->delta($currentInquiries, $previousInquiries),
            ],
            'bookings' => [
                'value' => $currentBookings,
                'delta' => $this->delta($currentBookings, $previousBookings),
            ],
            'conversionRate' => [
                'value' => $currentConversion !== null ? round($currentConversion, 1) : null,
                'delta' => $this->delta($currentConversion, $previousConversion),
            ],
        ];
    }
}
