<?php

namespace App\Http\Controllers;

use App\Enums\ChannelType;
use App\Models\Appointment;
use App\Models\AppointmentStatus;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Services\RevenueStatsService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class AnalyticsController extends Controller
{
    /** Hard cap on a custom range so a typo'd "from" doesn't scan years of rows. */
    private const MAX_RANGE_DAYS = 366;

    /** Channel breakdown cards focus on social media for now — email/website excluded. */
    private const SOCIAL_CHANNELS = [
        ChannelType::Instagram,
        ChannelType::FacebookMessenger,
        ChannelType::TikTok,
        ChannelType::WhatsApp,
    ];

    public function index(Request $request, RevenueStatsService $stats): Response
    {
        $workspace = $request->user()->currentWorkspace;

        [$start, $end] = $this->resolveRange($request, $workspace);
        [$compareKey, $compareStart, $compareEnd, $compareLabel] = $this->resolveCompare($request, $start, $end, $stats);

        $revenueSeries = $this->dailyRevenueSeries($workspace, $start, $end);
        $compareRevenueSeries = $compareStart
            ? $this->dailyRevenueSeries($workspace, $compareStart, $compareEnd)
            : null;

        return Inertia::render('Analytics/Index', [
            'range' => [
                'from' => $start->format('Y-m-d'),
                'to' => $end->format('Y-m-d'),
            ],
            'compare' => [
                'key' => $compareKey,
                'label' => $compareLabel,
                'range' => $compareStart ? ['from' => $compareStart->format('Y-m-d'), 'to' => $compareEnd->format('Y-m-d')] : null,
            ],
            'stats' => $stats->summary($workspace, $start->copy()->setTimezone(config('app.timezone')), $end->copy()->setTimezone(config('app.timezone')), $compareStart?->copy()->setTimezone(config('app.timezone')), $compareEnd?->copy()->setTimezone(config('app.timezone'))),
            'revenueSeries' => $revenueSeries,
            'compareRevenueSeries' => $compareRevenueSeries,
            'channelInquiries' => $this->channelBreakdown(
                Conversation::whereBetween('created_at', [$start->copy()->setTimezone(config('app.timezone')), $end->copy()->setTimezone(config('app.timezone'))])->with('channel')->get(),
                fn () => 1,
                fn (string $type) => route('inbox.index', ['channel_type' => $type]),
            ),
            'channelRevenue' => $this->channelRevenueBreakdown($workspace, $start, $end),
            'topProducts' => $workspace->orders_enabled ? $this->topProducts($start, $end) : [],
            'topServices' => $workspace->appointments_enabled ? $this->topServices($start, $end) : [],
        ]);
    }

    /**
     * @return array{0: string, 1: ?Carbon, 2: ?Carbon, 3: ?string}
     */
    private function resolveCompare(Request $request, Carbon $start, Carbon $end, RevenueStatsService $stats): array
    {
        $key = $request->get('compare', 'previous');
        if (! in_array($key, ['previous', 'year', 'none'], true)) {
            $key = 'previous';
        }

        if ($key === 'none') {
            return ['none', null, null, null];
        }

        if ($key === 'year') {
            $compareStart = $start->copy()->subYear();
            $compareEnd = $end->copy()->subYear();

            return ['year', $compareStart, $compareEnd, 'glede na enako obdobje lani'];
        }

        [$compareStart, $compareEnd] = $stats->previousPeriod($start, $end);
        $days = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
        $label = $days === 1 ? 'glede na prejšnji dan' : "glede na prejšnjih {$days} dni";

        return ['previous', $compareStart, $compareEnd, $label];
    }

    /**
     * Returns Carbon instances in the WORKSPACE's local timezone — suitable
     * for display (format('Y-m-d') labels, route params) as-is. Callers
     * must ->copy()->setTimezone(config('app.timezone')) before using them
     * to bound a query against a stored datetime column (created_at etc) —
     * that's the timezone those columns are actually persisted/read under.
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request, $workspace): array
    {
        $timezone = $workspace->timezone;
        $today = Carbon::today($timezone);
        $defaultStart = $today->copy()->subDays(29)->startOfDay();
        $defaultEnd = $today->copy()->endOfDay();

        $from = $request->get('from');
        $to = $request->get('to');

        try {
            $start = $from ? Carbon::parse($from, $timezone)->startOfDay() : $defaultStart;
            $end = $to ? Carbon::parse($to, $timezone)->endOfDay() : $defaultEnd;
        } catch (\Exception) {
            return [$defaultStart, $defaultEnd];
        }

        if ($start->gt($end)) {
            [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
        }

        if ($end->gt($defaultEnd)) {
            $end = $defaultEnd;
        }

        if ($start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) > self::MAX_RANGE_DAYS) {
            $start = $end->copy()->subDays(self::MAX_RANGE_DAYS)->startOfDay();
        }

        return [$start, $end];
    }

    private function dailyRevenueSeries($workspace, Carbon $start, Carbon $end): Collection
    {
        // Grouped in PHP by the WORKSPACE-local calendar day, not a SQL
        // DATE(created_at) — that would group by the UTC calendar day the
        // timestamp happens to be stored under, misattributing rows near
        // local midnight (e.g. 00:30 Ljubljana in winter is still 23:30
        // UTC the previous day).
        $timezone = $workspace->timezone;
        $bounds = [$start->copy()->setTimezone(config('app.timezone')), $end->copy()->setTimezone(config('app.timezone'))];

        $orderDaily = $workspace->orders_enabled
            ? Order::whereBetween('created_at', $bounds)
                ->whereNotIn('status', [...OrderStatus::cancelledKeys(), ...OrderStatus::refundedKeys()])
                ->get(['created_at', 'price'])
                ->groupBy(fn (Order $o) => $o->created_at->copy()->setTimezone($timezone)->format('Y-m-d'))
                ->map(fn ($rows) => (float) $rows->sum('price'))
            : collect();

        $appointmentDaily = $workspace->appointments_enabled
            ? Appointment::whereBetween('created_at', $bounds)
                ->whereNotIn('status', [...AppointmentStatus::cancelledKeys(), ...AppointmentStatus::noShowKeys(), ...AppointmentStatus::refundedKeys()])
                ->whereNotNull('price')
                ->get(['created_at', 'price'])
                ->groupBy(fn (Appointment $a) => $a->created_at->copy()->setTimezone($timezone)->format('Y-m-d'))
                ->map(fn ($rows) => (float) $rows->sum('price'))
            : collect();

        $series = collect();
        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $key = $day->format('Y-m-d');
            $series->push([
                'date' => $key,
                'value' => round((float) ($orderDaily[$key] ?? 0) + (float) ($appointmentDaily[$key] ?? 0), 2),
            ]);
        }

        return $series;
    }

    /**
     * @param  Collection<int, mixed>  $models
     * @param  ?callable(string): ?string  $hrefFor  Drill-down link builder, given the channel type key.
     * @return array<int, array{type: string, label: string, color: string, value: float, href: ?string}>
     */
    private function channelBreakdown(Collection $models, callable $valueFor, ?callable $hrefFor = null): array
    {
        $totals = [];
        foreach ($models as $model) {
            $type = $model->channel?->type ?? ChannelType::Website;
            if (! in_array($type, self::SOCIAL_CHANNELS, true)) {
                continue;
            }
            $totals[$type->value] = ($totals[$type->value] ?? 0) + $valueFor($model);
        }

        return $this->presentChannelTotals($totals, $hrefFor);
    }

    private function channelRevenueBreakdown($workspace, Carbon $start, Carbon $end): array
    {
        $orderTotals = [];
        $appointmentTotals = [];
        $bounds = [$start->copy()->setTimezone(config('app.timezone')), $end->copy()->setTimezone(config('app.timezone'))];

        if ($workspace->orders_enabled) {
            foreach (Order::whereBetween('created_at', $bounds)
                ->whereNotIn('status', [...OrderStatus::cancelledKeys(), ...OrderStatus::refundedKeys()])
                ->with('channel')->get() as $order) {
                $type = $order->channel?->type ?? ChannelType::Website;
                if (! in_array($type, self::SOCIAL_CHANNELS, true)) {
                    continue;
                }
                $orderTotals[$type->value] = ($orderTotals[$type->value] ?? 0) + (float) $order->price;
            }
        }

        if ($workspace->appointments_enabled) {
            foreach (Appointment::whereBetween('created_at', $bounds)
                ->whereNotIn('status', [...AppointmentStatus::cancelledKeys(), ...AppointmentStatus::noShowKeys(), ...AppointmentStatus::refundedKeys()])
                ->with('channel')->get() as $appointment) {
                $type = $appointment->channel?->type ?? ChannelType::Website;
                if (! in_array($type, self::SOCIAL_CHANNELS, true)) {
                    continue;
                }
                $appointmentTotals[$type->value] = ($appointmentTotals[$type->value] ?? 0) + (float) ($appointment->price ?? 0);
            }
        }

        $totals = [];
        foreach ([$orderTotals, $appointmentTotals] as $set) {
            foreach ($set as $type => $value) {
                $totals[$type] = ($totals[$type] ?? 0) + $value;
            }
        }

        $createdFrom = $start->format('Y-m-d');
        $createdTo = $end->format('Y-m-d');

        // A channel's revenue bar can be fed by orders, appointments, or
        // both. Only link out when exactly one module contributed — a
        // mixed total has no single list view that reproduces it exactly.
        $hrefFor = function (string $type) use ($orderTotals, $appointmentTotals, $createdFrom, $createdTo) {
            $inOrders = array_key_exists($type, $orderTotals);
            $inAppointments = array_key_exists($type, $appointmentTotals);

            if ($inOrders && ! $inAppointments) {
                return route('orders.index', [
                    'channel_type' => $type,
                    'status_scope' => 'not_cancelled',
                    'created_from' => $createdFrom,
                    'created_to' => $createdTo,
                ]);
            }

            if ($inAppointments && ! $inOrders) {
                return route('appointments.index', [
                    'channel_type' => $type,
                    'status' => 'requested,confirmed,completed',
                    'created_from' => $createdFrom,
                    'created_to' => $createdTo,
                ]);
            }

            return null;
        };

        return $this->presentChannelTotals($totals, $hrefFor);
    }

    private function presentChannelTotals(array $totals, ?callable $hrefFor = null): array
    {
        arsort($totals);

        return collect($totals)
            ->map(fn ($value, $type) => [
                'type' => $type,
                'label' => ChannelType::from($type)->label(),
                'color' => ChannelType::from($type)->color(),
                'value' => round((float) $value, 2),
                'href' => $hrefFor ? $hrefFor($type) : null,
            ])
            ->values()
            ->all();
    }

    private function topProducts(Carbon $start, Carbon $end): array
    {
        $rows = [];

        foreach (Order::whereBetween('created_at', [$start->copy()->setTimezone(config('app.timezone')), $end->copy()->setTimezone(config('app.timezone'))])
            ->whereNotIn('status', [...OrderStatus::cancelledKeys(), ...OrderStatus::refundedKeys()])
            ->with('items')
            ->get() as $order) {
            // Group by the linked catalog product when there is one — that's
            // what makes an exact "Orders for this product" drill-down
            // possible. Items with no catalog link (one-off titles) are
            // grouped by title text instead and stay non-clickable.
            foreach ($order->items as $item) {
                $key = $item->catalog_item_id ? "product:{$item->catalog_item_id}" : "title:{$item->title}";
                $rows[$key] ??= ['name' => $item->title, 'revenue' => 0.0, 'product_id' => $item->catalog_item_id];
                $rows[$key]['revenue'] += (float) $item->quantity * (float) $item->unit_price;
            }
        }

        return $this->presentTopItems($rows, 'product_id', fn ($id) => route('orders.index', [
            'catalog_item_id' => $id,
            'status_scope' => 'not_cancelled',
            'created_from' => $start->format('Y-m-d'),
            'created_to' => $end->format('Y-m-d'),
        ]));
    }

    private function topServices(Carbon $start, Carbon $end): array
    {
        $rows = [];

        foreach (Appointment::whereBetween('created_at', [$start->copy()->setTimezone(config('app.timezone')), $end->copy()->setTimezone(config('app.timezone'))])
            ->whereNotIn('status', [...AppointmentStatus::cancelledKeys(), ...AppointmentStatus::noShowKeys(), ...AppointmentStatus::refundedKeys()])
            ->with('items')
            ->get() as $appointment) {
            foreach ($appointment->items as $item) {
                $key = $item->catalog_item_id ? "service:{$item->catalog_item_id}" : "title:{$item->title}";
                $rows[$key] ??= ['name' => $item->title, 'revenue' => 0.0, 'service_id' => $item->catalog_item_id];
                $rows[$key]['revenue'] += (float) $item->quantity * (float) $item->unit_price;
            }
        }

        return $this->presentTopItems($rows, 'service_id', fn ($id) => route('appointments.index', [
            'service_id' => $id,
            'status' => 'requested,confirmed,completed',
            'created_from' => $start->format('Y-m-d'),
            'created_to' => $end->format('Y-m-d'),
        ]));
    }

    /**
     * @param  array<string, array{name: string, revenue: float, ...}>  $rows
     */
    private function presentTopItems(array $rows, string $idField, callable $hrefFor): array
    {
        return collect($rows)
            ->sortByDesc('revenue')
            ->take(6)
            ->map(fn ($row) => [
                'name' => $row['name'],
                'revenue' => round($row['revenue'], 2),
                'href' => $row[$idField] ? $hrefFor($row[$idField]) : null,
            ])
            ->values()
            ->all();
    }
}
