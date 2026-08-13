<?php

namespace App\Http\Controllers\Inbox;

use App\Enums\ConversationStatus;
use App\Enums\MessageSenderType;
use App\Enums\MessageType;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Inbox/Index', [
            'conversations' => $this->conversationList(),
            'conversation' => null,
        ]);
    }

    public function show(Conversation $conversation): Response
    {
        $conversation->load(['channel', 'customer.orders', 'customer.identities', 'messages.senderUser']);

        if ($conversation->unread_count > 0) {
            $conversation->update(['unread_count' => 0]);
        }

        return Inertia::render('Inbox/Index', [
            'conversations' => $this->conversationList(),
            'conversation' => $this->presentConversation($conversation),
        ]);
    }

    public function sendMessage(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate([
            'body' => 'required|string|max:5000',
        ]);

        $message = $conversation->messages()->create([
            'sender_type' => MessageSenderType::Business,
            'body' => $data['body'],
            'message_type' => MessageType::Text,
            'sent_at' => Carbon::now(),
        ]);

        $conversation->update([
            'last_message_preview' => str($data['body'])->limit(120),
            'last_message_at' => $message->sent_at,
        ]);

        return back();
    }

    public function update(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate([
            'status' => 'required|string|in:'.implode(',', array_map(fn ($c) => $c->value, ConversationStatus::cases())),
        ]);

        $conversation->update(['status' => $data['status']]);

        return back();
    }

    public function createCustomer(Conversation $conversation): RedirectResponse
    {
        if ($conversation->customer_id) {
            return back();
        }

        $customer = Customer::create([
            'full_name' => $conversation->customer_display_name ?? 'Unknown customer',
            'primary_channel_id' => $conversation->channel_id,
            'first_contacted_at' => $conversation->created_at,
            'last_interaction_at' => $conversation->last_message_at ?? $conversation->created_at,
        ]);

        CustomerIdentity::create([
            'customer_id' => $customer->id,
            'workspace_id' => $customer->workspace_id,
            'channel_type' => $conversation->channel->type,
            'username' => $conversation->customer_username,
            'display_name' => $conversation->customer_display_name,
        ]);

        $conversation->update(['customer_id' => $customer->id]);

        ActivityLog::record('customer_created', "{$customer->full_name} added as a customer from a conversation", $customer);

        return back();
    }

    public function addNote(Request $request, Conversation $conversation): RedirectResponse
    {
        $data = $request->validate(['note' => 'required|string|max:2000']);

        if ($conversation->customer_id && $conversation->customer) {
            $existing = $conversation->customer->notes;
            $conversation->customer->update([
                'notes' => trim(($existing ? $existing."\n\n" : '').$data['note']),
            ]);
        }

        return back();
    }

    private function conversationList()
    {
        return Conversation::query()
            ->with(['channel', 'customer'])
            ->orderByDesc('last_message_at')
            ->get()
            ->map(fn (Conversation $c) => [
                'id' => $c->id,
                'customer_id' => $c->customer_id,
                'display_name' => $c->displayName(),
                'channel' => $c->channel,
                'status' => $c->status,
                'last_message_preview' => $c->last_message_preview,
                'last_message_at' => $c->last_message_at,
                'unread_count' => $c->unread_count,
            ]);
    }

    private function presentConversation(Conversation $conversation): array
    {
        $customer = $conversation->customer;

        return [
            'id' => $conversation->id,
            'status' => $conversation->status,
            'channel' => $conversation->channel,
            'customer_display_name' => $conversation->customer_display_name,
            'customer_username' => $conversation->customer_username,
            'messages' => $conversation->messages,
            'customer' => $customer ? [
                'id' => $customer->id,
                'full_name' => $customer->full_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'notes' => $customer->notes,
                'identities' => $customer->identities,
                'total_orders_count' => $customer->orders->count(),
                'lifetime_spend' => $customer->lifetimeSpend(),
                'open_orders_count' => $customer->openOrdersCount(),
                'current_open_order' => $customer->orders
                    ->filter(fn ($o) => ! in_array($o->status->value, ['completed', 'cancelled'], true))
                    ->sortByDesc('created_at')
                    ->first(),
                'last_order_date' => $customer->orders->max('created_at'),
            ] : null,
        ];
    }
}
