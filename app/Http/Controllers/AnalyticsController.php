<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\ChannelType;
use App\Models\Appointment;
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

        [$start, $end] = $this->resolveRange($request);
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
            'stats' => $stats->summary($workspace, $start, $end, $compareStart, $compareEnd),
            'revenueSeries' => $revenueSeries,
            'compareRevenueSeries' => $compareRevenueSeries,
            'channelInquiries' => $this->channelBreakdown(
                Conversation::whereBetween('created_at', [$start, $end])->with('channel')->get(),
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
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolveRange(Request $request): array
    {
        $today = Carbon::today();
        $defaultStart = $today->copy()->subDays(29)->startOfDay();
        $defaultEnd = $today->copy()->endOfDay();

        $from = $request->get('from');
        $to = $request->get('to');

        try {
            $start = $from ? Carbon::parse($from)->startOfDay() : $defaultStart;
            $end = $to ? Carbon::parse($to)->endOfDay() : $defaultEnd;
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
        $orderDaily = $workspace->orders_enabled
            ? Order::whereBetween('created_at', [$start, $end])
                ->whereNotIn('status', OrderStatus::cancelledKeys())
                ->selectRaw('DATE(created_at) as day, SUM(price) as total')
                ->groupBy('day')
                ->pluck('total', 'day')
            : collect();

        $appointmentDaily = $workspace->appointments_enabled
            ? Appointment::whereBetween('created_at', [$start, $end])
                ->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
                ->whereNotNull('price')
                ->selectRaw('DATE(created_at) as day, SUM(price) as total')
                ->groupBy('day')
                ->pluck('total', 'day')
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

        if ($workspace->orders_enabled) {
            foreach (Order::whereBetween('created_at', [$start, $end])
                ->whereNotIn('status', OrderStatus::cancelledKeys())
                ->with('channel')->get() as $order) {
                $type = $order->channel?->type ?? ChannelType::Website;
                if (! in_array($type, self::SOCIAL_CHANNELS, true)) {
                    continue;
                }
                $orderTotals[$type->value] = ($orderTotals[$type->value] ?? 0) + (float) $order->price;
            }
        }

        if ($workspace->appointments_enabled) {
            foreach (Appointment::whereBetween('created_at', [$start, $end])
                ->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
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

        foreach (Order::whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', OrderStatus::cancelledKeys())
            ->with('product')
            ->get() as $order) {
            // Group by the linked catalog product when there is one — that's
            // what makes an exact "Orders for this product" drill-down
            // possible. Orders with no catalog link (one-off titles) are
            // grouped by title text instead and stay non-clickable.
            $key = $order->catalog_item_id ? "product:{$order->catalog_item_id}" : "title:{$order->title}";
            $rows[$key] ??= ['name' => $order->product?->name ?? $order->title, 'revenue' => 0.0, 'product_id' => $order->catalog_item_id];
            $rows[$key]['revenue'] += (float) $order->price;
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

        foreach (Appointment::whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', [AppointmentStatus::Cancelled->value, AppointmentStatus::NoShow->value])
            ->with('service')
            ->get() as $appointment) {
            $key = $appointment->service_id ? "service:{$appointment->service_id}" : "title:{$appointment->service_name}";
            $rows[$key] ??= ['name' => $appointment->service?->name ?? $appointment->service_name, 'revenue' => 0.0, 'service_id' => $appointment->service_id];
            $rows[$key]['revenue'] += (float) ($appointment->price ?? 0);
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
