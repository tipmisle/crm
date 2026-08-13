<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->get('q', ''));

        if ($query === '') {
            return response()->json(['results' => []]);
        }

        $results = collect();

        Customer::query()
            ->where('full_name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->each(function (Customer $customer) use ($results) {
                $results->push([
                    'type' => 'customer',
                    'id' => $customer->id,
                    'title' => $customer->full_name,
                    'subtitle' => $customer->email ?? 'Customer',
                    'href' => route('customers.show', $customer),
                ]);
            });

        Order::query()
            ->with('customer')
            ->where('order_number', 'like', "%{$query}%")
            ->orWhere('title', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->each(function (Order $order) use ($results) {
                $results->push([
                    'type' => 'order',
                    'id' => $order->id,
                    'title' => "{$order->order_number} · {$order->title}",
                    'subtitle' => $order->customer->full_name,
                    'href' => route('orders.show', $order),
                ]);
            });

        Conversation::query()
            ->with('customer')
            ->where('customer_display_name', 'like', "%{$query}%")
            ->orWhere('customer_username', 'like', "%{$query}%")
            ->orWhereHas('customer', fn ($q) => $q->where('full_name', 'like', "%{$query}%"))
            ->limit(5)
            ->get()
            ->each(function (Conversation $conversation) use ($results) {
                $results->push([
                    'type' => 'conversation',
                    'id' => $conversation->id,
                    'title' => $conversation->displayName(),
                    'subtitle' => $conversation->last_message_preview ?? 'Conversation',
                    'href' => route('inbox.show', $conversation),
                ]);
            });

        return response()->json(['results' => $results->values()]);
    }
}
