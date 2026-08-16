<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\OrderStatus;
use App\Models\PaymentStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatusesController extends Controller
{
    public function edit(Request $request): Response
    {
        $orderStatuses = OrderStatus::query()->ordered()->withCount('orders')->get()
            ->map(fn (OrderStatus $status) => [
                'id' => $status->id,
                'key' => $status->key,
                'label' => $status->label,
                'color' => $status->color,
                'bg' => $status->bg,
                'sort_order' => $status->sort_order,
                'is_default' => $status->is_default,
                'is_completed' => $status->is_completed,
                'is_cancelled' => $status->is_cancelled,
                'in_use' => $status->orders_count > 0,
            ]);

        $paymentStatuses = PaymentStatus::query()->ordered()->get()
            ->map(function (PaymentStatus $status) {
                $inUse = \App\Models\Order::where('payment_status', $status->key)->exists()
                    || Appointment::where('payment_status', $status->key)->exists();

                return [
                    'id' => $status->id,
                    'key' => $status->key,
                    'label' => $status->label,
                    'color' => $status->color,
                    'bg' => $status->bg,
                    'sort_order' => $status->sort_order,
                    'is_default' => $status->is_default,
                    'is_deposit_default' => $status->is_deposit_default,
                    'is_outstanding' => $status->is_outstanding,
                    'in_use' => $inUse,
                ];
            });

        return Inertia::render('Settings/Statuses', [
            'orderStatuses' => $orderStatuses,
            'paymentStatuses' => $paymentStatuses,
        ]);
    }
}
