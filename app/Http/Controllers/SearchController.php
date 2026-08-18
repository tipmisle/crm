<?php

namespace App\Http\Controllers;

use App\Models\Appointment;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesDocument;
use App\Models\Service;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $query = trim((string) $request->get('q', ''));

        if ($query === '') {
            return response()->json(['results' => []]);
        }

        $workspace = $request->user()->currentWorkspace;
        $results = collect();

        Customer::query()
            ->where('full_name', 'like', "%{$query}%")
            ->orWhere('company_name', 'like', "%{$query}%")
            ->orWhere('email', 'like', "%{$query}%")
            ->orWhere('phone', 'like', "%{$query}%")
            ->orWhere('tax_number', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->each(function (Customer $customer) use ($results) {
                // A business customer is presented with the company name up
                // front (what most searches will match on for a B2B
                // customer) — full_name stays visible as the contact person,
                // never replaced by it. Customer remains the one source of
                // truth; this is presentation only.
                $title = $customer->is_business && $customer->company_name
                    ? $customer->company_name
                    : $customer->full_name;
                $subtitle = $customer->is_business && $customer->company_name
                    ? $customer->full_name
                    : ($customer->email ?? 'Stranka');

                $results->push([
                    'type' => 'customer',
                    'id' => $customer->id,
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'href' => route('customers.show', $customer),
                ]);
            });

        if ($workspace->orders_enabled) {
            Order::query()
                ->with('customer')
                ->where('order_number', 'like', "%{$query}%")
                ->orWhere('title', 'like', "%{$query}%")
                ->orWhereHas('customer', fn ($q) => $q->where('full_name', 'like', "%{$query}%")
                    ->orWhere('company_name', 'like', "%{$query}%"))
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
        }

        if ($workspace->appointments_enabled) {
            Appointment::query()
                ->with('customer')
                ->where('appointment_number', 'like', "%{$query}%")
                ->orWhere('service_name', 'like', "%{$query}%")
                ->orWhereHas('customer', fn ($q) => $q->where('full_name', 'like', "%{$query}%")
                    ->orWhere('company_name', 'like', "%{$query}%"))
                ->limit(5)
                ->get()
                ->each(function (Appointment $appointment) use ($results) {
                    $results->push([
                        'type' => 'appointment',
                        'id' => $appointment->id,
                        'title' => "{$appointment->appointment_number} · {$appointment->service_name}",
                        'subtitle' => $appointment->customer->full_name,
                        'href' => route('appointments.show', $appointment),
                    ]);
                });
        }

        if ($workspace->orders_enabled) {
            Product::where('name', 'like', "%{$query}%")
                ->limit(5)
                ->get()
                ->each(function (Product $product) use ($results) {
                    $results->push([
                        'type' => 'product',
                        'id' => $product->id,
                        'title' => $product->name,
                        'subtitle' => 'Produkt',
                        'href' => route('catalog.index'),
                    ]);
                });
        }

        if ($workspace->appointments_enabled) {
            Service::where('name', 'like', "%{$query}%")
                ->limit(5)
                ->get()
                ->each(function (Service $service) use ($results) {
                    $results->push([
                        'type' => 'service',
                        'id' => $service->id,
                        'title' => $service->name,
                        'subtitle' => 'Storitev',
                        'href' => route('catalog.index'),
                    ]);
                });
        }

        SalesDocument::query()
            ->where('document_number', 'like', "%{$query}%")
            ->orWhere('external_document_number', 'like', "%{$query}%")
            ->limit(5)
            ->get()
            ->each(function (SalesDocument $document) use ($results) {
                $href = match (true) {
                    (bool) $document->order_id => route('orders.show', $document->order_id),
                    (bool) $document->appointment_id => route('appointments.show', $document->appointment_id),
                    default => '#',
                };

                $sourceLabel = $document->isExternal() ? 'zunanji dokument' : 'izdan';
                $subjectLabel = match (true) {
                    (bool) $document->order_id => 'naročilo',
                    (bool) $document->appointment_id => 'termin',
                    default => null,
                };

                $results->push([
                    'type' => 'sales_document',
                    'id' => $document->id,
                    'title' => $document->displayNumber(),
                    'subtitle' => $subjectLabel
                        ? "{$document->typeLabel()} · {$sourceLabel} · {$subjectLabel}"
                        : "{$document->typeLabel()} · {$sourceLabel}",
                    'href' => $href,
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
                    'subtitle' => $conversation->last_message_preview ?? 'Pogovor',
                    'href' => route('inbox.show', $conversation),
                ]);
            });

        return response()->json(['results' => $results->values()]);
    }
}
