<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\FollowUp;
use App\Models\Order;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use App\Services\RevenueStatsService;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class TodayController extends Controller
{
    public function __invoke(RevenueStatsService $stats): Response
    {
        $workspace = auth()->user()->currentWorkspace;

        $waitingReplyCount = Conversation::query()
            ->where('unread_count', '>', 0)
            ->count();

        $attention = collect([
            [
                'key' => 'waiting_reply',
                'label' => $waitingReplyCount === 1
                    ? '1 pogovor čaka na odgovor'
                    : "{$waitingReplyCount} pogovorov čaka na odgovor",
                'count' => $waitingReplyCount,
                'href' => route('inbox.index'),
            ],
        ]);

        $todaysOrders = collect();
        $upcomingOrders = collect();

        if ($workspace->orders_enabled) {
            $openStatuses = array_diff(
                OrderStatus::query()->pluck('key')->all(),
                OrderStatus::openExclusionKeys()
            );

            $todaysOrders = Order::query()
                ->with(['customer', 'channel', 'salesDocuments:id,order_id,type'])
                ->whereDate('due_date', Carbon::today())
                ->whereIn('status', $openStatuses)
                ->orderBy('due_time')
                ->get();

            $depositsUnpaidCount = Order::query()
                ->whereIn('status', $openStatuses)
                ->whereIn('payment_status', PaymentStatus::outstandingKeys())
                ->where('deposit_amount', '>', 0)
                ->count();

            $overdueOrders = Order::query()
                ->with(['customer'])
                ->whereIn('status', $openStatuses)
                ->whereDate('due_date', '<', Carbon::today())
                ->get();

            $attention->push(
                [
                    'key' => 'due_today',
                    'label' => $todaysOrders->count() === 1
                        ? '1 naročilo zapade danes'
                        : "{$todaysOrders->count()} naročil zapade danes",
                    'count' => $todaysOrders->count(),
                    'href' => route('orders.index', ['due' => 'today', 'status_scope' => 'open']),
                ],
                [
                    'key' => 'deposits_unpaid',
                    'label' => $depositsUnpaidCount === 1
                        ? '1 ara še ni plačana'
                        : "{$depositsUnpaidCount} ar še ni plačanih",
                    'count' => $depositsUnpaidCount,
                    'href' => route('orders.index', [
                        'status_scope' => 'open',
                        'payment_scope' => 'outstanding',
                        'deposit' => 'unpaid',
                    ]),
                ],
                [
                    'key' => 'overdue',
                    'label' => $overdueOrders->count() === 1
                        ? '1 zamujeno naročilo'
                        : "{$overdueOrders->count()} zamujenih naročil",
                    'count' => $overdueOrders->count(),
                    'href' => route('orders.index', ['due' => 'overdue']),
                ],
            );

            $upcomingOrders = Order::query()
                ->with(['customer', 'channel', 'salesDocuments:id,order_id,type'])
                ->whereIn('status', $openStatuses)
                ->whereDate('due_date', '>', Carbon::today())
                ->whereDate('due_date', '<=', Carbon::today()->addDays(7))
                ->orderBy('due_date')
                ->limit(8)
                ->get();
        }

        $todaysAppointments = collect();
        $upcomingAppointments = collect();

        if ($workspace->appointments_enabled) {
            $activeStatuses = [AppointmentStatus::Requested->value, AppointmentStatus::Confirmed->value];

            $todaysAppointments = Appointment::query()
                ->with(['customer', 'channel'])
                ->whereDate('appointment_date', Carbon::today())
                ->whereIn('status', $activeStatuses)
                ->orderBy('start_time')
                ->get();

            $awaitingConfirmationCount = Appointment::query()->where('status', AppointmentStatus::Requested->value)->count();

            $appointmentDepositsUnpaidCount = Appointment::query()
                ->whereIn('status', $activeStatuses)
                ->whereIn('payment_status', PaymentStatus::outstandingKeys())
                ->where('deposit_amount', '>', 0)
                ->count();

            $attention->push(
                [
                    'key' => 'appointments_today',
                    'label' => $todaysAppointments->count() === 1
                        ? '1 termin danes'
                        : "{$todaysAppointments->count()} terminov danes",
                    'count' => $todaysAppointments->count(),
                    'href' => route('appointments.index', [
                        'view' => 'list',
                        'due' => 'today',
                        'status' => implode(',', $activeStatuses),
                    ]),
                ],
                [
                    'key' => 'awaiting_confirmation',
                    'label' => $awaitingConfirmationCount === 1
                        ? '1 termin čaka na potrditev'
                        : "{$awaitingConfirmationCount} terminov čaka na potrditev",
                    'count' => $awaitingConfirmationCount,
                    'href' => route('appointments.index', [
                        'view' => 'list',
                        'status' => AppointmentStatus::Requested->value,
                    ]),
                ],
                [
                    'key' => 'appointment_deposits_unpaid',
                    'label' => $appointmentDepositsUnpaidCount === 1
                        ? '1 ara za termin še ni plačana'
                        : "{$appointmentDepositsUnpaidCount} ar za termine še ni plačanih",
                    'count' => $appointmentDepositsUnpaidCount,
                    'href' => route('appointments.index', [
                        'view' => 'list',
                        'status' => implode(',', $activeStatuses),
                        'payment_scope' => 'outstanding',
                        'deposit' => 'unpaid',
                    ]),
                ],
            );

            $upcomingAppointments = Appointment::query()
                ->with(['customer', 'channel'])
                ->whereIn('status', $activeStatuses)
                ->whereDate('appointment_date', '>', Carbon::today())
                ->whereDate('appointment_date', '<=', Carbon::today()->addDays(7))
                ->orderBy('appointment_date')
                ->orderBy('start_time')
                ->limit(8)
                ->get();
        }

        $attention = $attention->filter(fn ($item) => $item['count'] > 0)->values();

        $unansweredConversations = Conversation::query()
            ->with('customer')
            ->where('unread_count', '>', 0)
            ->orderByDesc('last_message_at')
            ->limit(5)
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'display_name' => $c->displayName(),
                'last_message_preview' => $c->last_message_preview,
                'last_message_at' => $c->last_message_at,
                'unread_count' => $c->unread_count,
                'href' => route('inbox.show', $c->id),
            ]);

        $followUps = FollowUp::query()
            ->with('followable')
            ->pending()
            ->where('due_at', '<=', Carbon::now()->endOfDay())
            ->orderBy('due_at')
            ->limit(6)
            ->get()
            ->map(fn (FollowUp $followUp) => $this->presentFollowUp($followUp));

        return Inertia::render('Today', [
            'attention' => $attention,
            'todaysOrders' => $todaysOrders,
            'todaysAppointments' => $todaysAppointments,
            'followUps' => $followUps,
            'unansweredConversations' => $unansweredConversations,
            'upcoming' => $upcomingOrders,
            'upcomingAppointments' => $upcomingAppointments,
            'stats' => $stats->summary(
                $workspace,
                Carbon::today()->startOfDay(),
                Carbon::today()->endOfDay(),
                Carbon::yesterday()->startOfDay(),
                Carbon::yesterday()->endOfDay(),
            ),
        ]);
    }

    private function presentFollowUp(FollowUp $followUp): array
    {
        $followable = $followUp->followable;
        $href = match (true) {
            $followable instanceof Customer => route('customers.show', $followable),
            $followable instanceof Order => route('orders.show', $followable),
            $followable instanceof Appointment => route('appointments.show', $followable),
            $followable instanceof Conversation => route('inbox.show', $followable),
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
