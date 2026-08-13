<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Conversation;
use App\Models\FollowUp;
use App\Models\Order;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TodayController extends Controller
{
    public function __invoke(): Response
    {
        $openStatuses = array_map(
            fn (OrderStatus $s) => $s->value,
            array_filter(
                OrderStatus::board(),
                fn (OrderStatus $s) => ! in_array($s, [OrderStatus::Completed, OrderStatus::Cancelled], true)
            )
        );

        $waitingReplyCount = Conversation::query()
            ->where('unread_count', '>', 0)
            ->count();

        $dueTodayOrders = Order::query()
            ->with(['customer', 'channel'])
            ->whereDate('due_date', Carbon::today())
            ->whereIn('status', $openStatuses)
            ->orderBy('due_time')
            ->get();

        $quotesWaitingCount = Order::query()->where('status', OrderStatus::QuoteSent->value)->count();

        $depositsUnpaidCount = Order::query()
            ->whereIn('status', $openStatuses)
            ->whereIn('payment_status', [PaymentStatus::Unpaid->value, PaymentStatus::DepositDue->value])
            ->where('deposit_amount', '>', 0)
            ->count();

        $overdueOrders = Order::query()
            ->with(['customer'])
            ->whereIn('status', $openStatuses)
            ->whereDate('due_date', '<', Carbon::today())
            ->get();

        $attention = collect([
            [
                'key' => 'waiting_reply',
                'label' => $waitingReplyCount === 1
                    ? '1 conversation waiting for a reply'
                    : "{$waitingReplyCount} conversations waiting for a reply",
                'count' => $waitingReplyCount,
                'href' => route('inbox.index'),
            ],
            [
                'key' => 'due_today',
                'label' => $dueTodayOrders->count() === 1
                    ? '1 order due today'
                    : "{$dueTodayOrders->count()} orders due today",
                'count' => $dueTodayOrders->count(),
                'href' => route('orders.index', ['due' => 'today']),
            ],
            [
                'key' => 'quotes_waiting',
                'label' => $quotesWaitingCount === 1
                    ? '1 quote waiting for confirmation'
                    : "{$quotesWaitingCount} quotes waiting for confirmation",
                'count' => $quotesWaitingCount,
                'href' => route('orders.index', ['status' => 'quote_sent']),
            ],
            [
                'key' => 'deposits_unpaid',
                'label' => $depositsUnpaidCount === 1
                    ? '1 deposit still unpaid'
                    : "{$depositsUnpaidCount} deposits still unpaid",
                'count' => $depositsUnpaidCount,
                'href' => route('orders.index', ['payment' => 'deposit_due']),
            ],
            [
                'key' => 'overdue',
                'label' => $overdueOrders->count() === 1
                    ? '1 overdue order'
                    : "{$overdueOrders->count()} overdue orders",
                'count' => $overdueOrders->count(),
                'href' => route('orders.index', ['due' => 'overdue']),
            ],
        ])->filter(fn ($item) => $item['count'] > 0)->values();

        $followUps = FollowUp::query()
            ->with('followable')
            ->pending()
            ->where('due_at', '<=', Carbon::now()->endOfDay())
            ->orderBy('due_at')
            ->limit(6)
            ->get()
            ->map(fn (FollowUp $followUp) => $this->presentFollowUp($followUp));

        $upcoming = Order::query()
            ->with(['customer', 'channel'])
            ->whereIn('status', $openStatuses)
            ->whereDate('due_date', '>', Carbon::today())
            ->whereDate('due_date', '<=', Carbon::today()->addDays(7))
            ->orderBy('due_date')
            ->limit(8)
            ->get();

        return Inertia::render('Today', [
            'attention' => $attention,
            'todaysOrders' => $dueTodayOrders,
            'followUps' => $followUps,
            'upcoming' => $upcoming,
        ]);
    }

    private function presentFollowUp(FollowUp $followUp): array
    {
        $followable = $followUp->followable;
        $href = match (true) {
            $followable instanceof \App\Models\Customer => route('customers.show', $followable),
            $followable instanceof \App\Models\Order => route('orders.show', $followable),
            $followable instanceof \App\Models\Conversation => route('inbox.show', $followable),
            default => '#',
        };

        return [
            'id' => $followUp->id,
            'note' => $followUp->note,
            'due_at' => $followUp->due_at,
            'is_overdue' => $followUp->due_at->isPast(),
            'href' => $href,
        ];
    }
}
